<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RequestStatusEnum;
use App\Models\User;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\RequestRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * 勤務実績Excel出力サービス
 */
class AttendanceExcelExportService
{
    private const TEMPLATE_PATH = 'app/public/templates/attendance_template.xlsx';

    private const HEADER_ROW = 4;

    private const DATA_START_ROW = 7;

    /**
     * テンプレートで内容のある最終行（39〜40行目は余白）
     *
     * テンプレートは1000行目まで書式だけの空セルを持っている（5,700セル以上）。
     * スタッフごとにシートを複製する際、これが人数分に膨れて時間とメモリを
     * 大きく消費する（65名で約75秒・570MB）ため、これより下の行は取り除く。
     */
    private const TEMPLATE_LAST_ROW = 40;

    /**
     * 開いたときの選択セルであり、ウィンドウ枠の固定位置
     */
    private const VIEW_ANCHOR_CELL = 'B7';

    /**
     * 曜日の表記（日曜始まり）
     */
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    public function __construct(
        private readonly RawStampTimeService $rawStampTimeService,
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly RequestRepositoryInterface $requestRepository,
        private readonly LateEarlyLeaveDisplay $lateEarlyLeaveDisplay,
        private readonly WorkTypeAndNoteService $workTypeAndNoteService,
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly AutoBreakFillService $autoBreakFillService,
    ) {}

    /**
     * 勤務実績をExcelファイルとして生成（1人分）
     *
     * @param  int  $companyId  会社ID
     * @param  User  $user  ユーザー
     * @param  string  $periodStart  開始日（Y-m-d形式）
     * @param  string  $periodEnd  終了日（Y-m-d形式）
     * @return string 一時ファイルパス
     */
    public function generate(int $companyId, User $user, string $periodStart, string $periodEnd): string
    {
        return $this->generateForUsers($companyId, collect([$user]), $periodStart, $periodEnd);
    }

    /**
     * 複数のスタッフの勤務実績を、1つのExcelファイルにスタッフごとのシートで出力する
     *
     * 以前は1人1ファイルのZIPで出力していたため、全員分を印刷するのに
     * ファイルを1つずつ開く必要があった（クライアント要望: 一つのファイルに
     * シートを全員分で出力できないか。印刷するのが非常に手間）。
     * 各シートはテンプレートと同じ体裁・数式で、シート名はスタッフ名にする。
     *
     * @param  int  $companyId  会社ID
     * @param  Collection<int, User>  $users  対象スタッフ（この順にシートを並べる）
     * @param  string  $periodStart  開始日（Y-m-d形式）
     * @param  string  $periodEnd  終了日（Y-m-d形式）
     * @return string 一時ファイルパス
     */
    public function generateForUsers(int $companyId, Collection $users, string $periodStart, string $periodEnd): string
    {
        $startDate = CarbonImmutable::parse($periodStart);
        $endDate = CarbonImmutable::parse($periodEnd);

        $spreadsheet = $this->loadTemplate();
        // テンプレートのシートは書き込まずに雛形として残し、スタッフごとに複製して使う
        $templateSheet = $spreadsheet->getSheet(0);
        if ($templateSheet->getHighestRow() > self::TEMPLATE_LAST_ROW) {
            $templateSheet->removeRow(
                self::TEMPLATE_LAST_ROW + 1,
                $templateSheet->getHighestRow() - self::TEMPLATE_LAST_ROW
            );
        }
        $usedTitles = [];

        foreach ($users as $user) {
            $sheet = clone $templateSheet;
            $sheet->setTitle($this->uniqueSheetTitle($user, $usedTitles));
            $spreadsheet->addSheet($sheet);

            $this->fillSheet($sheet, $companyId, $user, $startDate, $endDate);
        }

        $spreadsheet->removeSheetByIndex(0);
        // ブックから外した雛形のシートは、disconnectWorksheets()の対象外になる。
        // セルを手放さないと、出力のたびに約1MBずつ解放されずに残る
        $templateSheet->disconnectCells();
        $spreadsheet->setActiveSheetIndex(0);

        // 一時ファイルとして保存
        return $this->saveToTempFile($spreadsheet);
    }

    /**
     * 1人分の勤務実績を、スタッフのシートに書き込む
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $sheet
     */
    private function fillSheet($sheet, int $companyId, User $user, CarbonImmutable $startDate, CarbonImmutable $endDate): void
    {
        // 勤務実績データを取得
        $summaries = $this->dailyWorkSummaryRepository->findByUserIdAndDateRange(
            $companyId,
            $user->id,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        // 承認済み申請データを取得（日付をキーにしたマップ）
        $approvedRequests = $this->requestRepository->findByUserIdAndDateRange(
            $companyId,
            $user->id,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            RequestStatusEnum::APPROVED->value
        );
        $requestMap = $approvedRequests->groupBy(fn ($r) => $r->target_date->format('Y-m-d'));

        // 会社の1日所定勤務時間（分）を取得
        $user->loadMissing('companies');
        $primaryCompany = $user->companies->firstWhere('pivot.is_primary', true) ?? $user->companies->first();
        $dailyWorkingMinutes = (int) (($primaryCompany?->daily_working_hours ?? 8) * 60);

        // 休憩の自動入力で補われた休憩を出すために、その日のシフトを使う
        $shiftMap = $this->shiftRepository
            ->findByUserIdAndDateRange($companyId, $user->id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'))
            ->keyBy(fn ($shift) => $shift->shift_date->format('Y-m-d'));

        // ヘッダー部分を設定
        $this->setHeaderData($sheet, $user, $endDate);

        // 表示は実打刻を使う。集計テーブルには丸め後の時刻が入っているため引き直す。
        $rawTimes = $this->rawStampTimeService->mapByDate(
            $companyId,
            $user->id,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
        );

        // 明細部分を設定
        $this->setDetailData($sheet, $summaries, $startDate, $endDate, $requestMap, $dailyWorkingMinutes, $rawTimes, $shiftMap);

        // 合計行（38行目）はテンプレート側のSUM数式（例: M38=SUM(M7:M37)）が
        // そのまま計算する。ここでは明細行と同じ[h]:mm書式を当てて、数式が
        // 実際に計算した値が崩れず表示されるようにするだけでよい
        // （数式自体は書き換えない。テンプレートのnumFmtIdが未設定のGeneral
        // のままだと、計算結果が小数のシリアル値のまま表示されてしまうため）。
        foreach (['M', 'N', 'O', 'P', 'Q'] as $column) {
            $sheet->getStyle($column.'38')->getNumberFormat()->setFormatCode('[h]:mm');
        }

        // 集計欄の有給日数（H4）も、テンプレートのnumFmtIdがGeneralのため、
        // 計算結果が3.000のような小数でも末尾のゼロが省略されて「3」と
        // 表示されてしまう（クライアント報告 #74: 有給の桁数が0.000の
        // 小数点以下3桁で反映されていない）。数式自体は書き換えず、
        // 明細行の値（task#74でWorkTypeAndNoteServiceが小数点以下3桁で
        // 統一した）に合わせて表示だけ3桁固定にする。
        $sheet->getStyle('H4')->getNumberFormat()->setFormatCode('0.000');

        $this->setSheetView($sheet);
    }

    /**
     * 開いたときの表示位置と、ウィンドウ枠の固定を設定する
     *
     * テンプレートは保存時のカーソル位置（集計欄の長い数式のセル）で開いてしまい、
     * 数式バーに長い数式が表示されていた（クライアント要望: B7を
     * デフォルトの位置にして、B7でウィンドウ枠も固定してほしい）。
     * B7は明細の先頭行・日付列の右隣で、上のヘッダー・集計欄（1〜6行目）と
     * 日付列（A列）を固定する。
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $sheet
     */
    private function setSheetView($sheet): void
    {
        $sheet->setTopLeftCell('A1');
        $sheet->freezePane(self::VIEW_ANCHOR_CELL);
        $sheet->setSelectedCells(self::VIEW_ANCHOR_CELL);
    }

    /**
     * シート名を決める（スタッフ名。Excelの制約に合わせて整える）
     *
     * シート名は31文字まで、: \ / ? * [ ] は使えず、大文字小文字を区別せず
     * 重複できない。同じ名前のスタッフがいる場合は個人コードを付けて区別する。
     *
     * @param  array<string, true>  $usedTitles  使用済みのシート名（小文字）
     */
    private function uniqueSheetTitle(User $user, array &$usedTitles): string
    {
        $name = trim((string) preg_replace('#[\\\\/?*\[\]:]#u', '', (string) $user->name), "' ");
        $base = $name !== '' ? $name : (string) ($user->employee_code ?: 'スタッフ');
        $title = mb_substr($base, 0, 31);

        if (isset($usedTitles[mb_strtolower($title)])) {
            $suffix = '_'.($user->employee_code ?: $user->id);
            $title = mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
        }

        $counter = 2;
        while (isset($usedTitles[mb_strtolower($title)])) {
            $suffix = '_'.$counter++;
            $title = mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
        }

        $usedTitles[mb_strtolower($title)] = true;

        return $title;
    }

    /**
     * テンプレートファイルを読み込む
     */
    private function loadTemplate(): Spreadsheet
    {
        $templatePath = storage_path(self::TEMPLATE_PATH);

        return IOFactory::load($templatePath);
    }

    /**
     * 月間サマリーを計算
     *
     * @param  Collection  $summaries  勤務実績コレクション
     * @return array<string, int|float>
     */
    private function calculateMonthlySummary(Collection $summaries, Collection $approvedRequests, int $dailyWorkingMinutes): array
    {
        // 有給・特休・欠勤日数を承認済み申請から集計
        $paidLeaveDays = 0.0;
        $specialLeaveDays = 0.0;
        $absenceDays = 0.0;

        foreach ($approvedRequests as $request) {
            $typeId = $request->type;
            $days = (float) $this->workTypeAndNoteService->calculateLeaveDays($typeId, $request, $dailyWorkingMinutes);

            match (true) {
                in_array($typeId, [1, 9, 10], true) => $paidLeaveDays += $days,
                $typeId === 5 => $specialLeaveDays += $days,
                $typeId === 6 => $absenceDays += $days,
                default => null,
            };
        }

        return [
            'work_days' => $summaries->filter(fn ($s) => $s->work_start !== null)->count(),
            'paid_leave_days' => $paidLeaveDays,
            'special_leave_days' => $specialLeaveDays,
            'absence_days' => $absenceDays,
            'total_work_minutes' => $summaries->sum('work_minutes'),
            'total_break_minutes' => $summaries->sum('break_minutes'),
            'total_net_work_minutes' => $summaries->sum('net_work_minutes'),
            'total_overtime_minutes' => $summaries->sum('overtime_minutes'),
            'total_night_minutes' => $summaries->sum('night_minutes'),
            'total_holiday_minutes' => $summaries->sum('holiday_minutes'),
            'total_late_minutes' => $summaries->sum('late_minutes'),
            'total_early_leave_minutes' => $summaries->sum('early_leave_minutes'),
            'late_count' => $summaries->where('late_minutes', '>', 0)->count(),
            'early_leave_count' => $summaries->where('early_leave_minutes', '>', 0)->count(),
        ];
    }

    /**
     * ヘッダー部分にデータを設定
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $sheet
     * @param  array<string, int|float>  $monthlySummary
     */
    private function setHeaderData($sheet, User $user, CarbonImmutable $targetMonth): void
    {
        // 月度
        $sheet->setCellValue('G1', $targetMonth->month);

        // スタッフの識別情報
        $sheet->setCellValue('A'.self::HEADER_ROW, $user->employee_code ?? '');
        $sheet->setCellValue('B'.self::HEADER_ROW, $user->name);

        $user->loadMissing('departments');
        $primaryDepartment = $user->departments->where('pivot.is_primary', true)->first();
        $sheet->setCellValue('D'.self::HEADER_ROW, $primaryDepartment?->name ?? '');

        // 集計欄（H4〜U4）はテンプレート側の数式が算出するため書き込まない。
        // 値を入れると数式が失われ、シート側で組まれた集計が動かなくなる。
    }

    /**
     * 明細部分にデータを設定
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $sheet
     * @param  Collection  $requestMap  日付をキーにした承認済み申請マップ
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     */
    private function setDetailData($sheet, Collection $summaries, CarbonImmutable $startDate, CarbonImmutable $endDate, Collection $requestMap, int $dailyWorkingMinutes, array $rawTimes = [], ?Collection $shiftMap = null): void
    {
        // 日付をキーにしたマップを作成
        $summaryMap = $summaries->keyBy(fn ($s) => $s->work_date->format('Y-m-d'));

        $currentDate = $startDate;
        $row = self::DATA_START_ROW;

        while ($currentDate->lte($endDate)) {
            $dateKey = $currentDate->format('Y-m-d');
            $summary = $summaryMap->get($dateKey);
            $raw = $rawTimes[$dateKey] ?? null;

            // 出退勤済みかどうかの判定・G列表示のどちらにも使う実効的な出勤時刻。
            // summaryのwork_startだけを見ると、実打刻はあるのに集計バッチが
            // まだ反映していない日に「打刻なし」と誤判定してしまう
            // （クライアント報告: シフト・出退勤があるのに欠勤で出力される）。
            // 実打刻を優先し、無ければ集計値にフォールバックする。
            $effectiveWorkStart = $raw['work_start'] ?? $summary?->work_start?->format('H:i');

            // A: 日付（例: 12/1(月)）
            $dateText = $currentDate->format('n/j').'('.self::WEEKDAYS[$currentDate->dayOfWeek].')';
            $sheet->setCellValue('A'.$row, $dateText);

            // B: 勤務区分
            $workType = $this->getWorkType($summary, $effectiveWorkStart);
            $sheet->setCellValue('B'.$row, $workType);

            if ($summary) {
                // C: シフト開始
                $sheet->setCellValue('C'.$row, $this->formatTimeToHM($summary->scheduled_start_time));

                // D: シフト終了
                $sheet->setCellValue('D'.$row, $this->formatTimeToHM($summary->scheduled_end_time));

                // E・F: シフト休憩の開始・終了
                $sheet->setCellValue('E'.$row, $this->formatTimeToHM($summary->scheduled_break_start ?? null));
                $sheet->setCellValue('F'.$row, $this->formatTimeToHM($summary->scheduled_break_end ?? null));

                // G・H: 出退勤（実打刻。丸め時刻は計算にのみ使う）
                $sheet->setCellValue('G'.$row, $effectiveWorkStart ?? '');
                $sheet->setCellValue('H'.$row, $raw['work_end'] ?? $summary->work_end?->format('H:i') ?? '');

                // I〜L: 休憩の入り・出（2枠。こちらも実打刻）
                $breaks = $raw['breaks'] ?? [];
                if ($breaks === []) {
                    // 休憩の打刻が無い日は、集計で補完された休憩（画面の薄い表示と同じ）を出す
                    $filledBreak = $this->autoBreakFillService->periodForOutput(
                        $shiftMap?->get($dateKey)?->shiftPattern,
                        $summary
                    );
                    $breaks = $filledBreak !== null ? [$filledBreak] : [];
                }
                $sheet->setCellValue('I'.$row, $breaks[0]['start'] ?? '');
                $sheet->setCellValue('J'.$row, $breaks[0]['end'] ?? '');
                $sheet->setCellValue('K'.$row, $breaks[1]['start'] ?? '');
                $sheet->setCellValue('L'.$row, $breaks[1]['end'] ?? '');

                // M〜Q: 労働時間・時間外・休日・深夜・遅刻早退
                // 文字列("6:30"など)のまま書き込むと表示は同じでも数値として
                // 扱われず、38行目の合計欄(=SUM(M7:M37)等)が常に0になってしまう
                // （クライアント報告: 合計値が計算されていない）。実際の時刻の
                // 端数として書き込み、テンプレート内の他セルと同じ[h]:mm書式を当てる。
                $this->writeMinutesAsTime($sheet, 'M'.$row, $summary->net_work_minutes ?? 0);
                $this->writeMinutesAsTime($sheet, 'N'.$row, $summary->overtime_minutes ?? 0);
                $this->writeMinutesAsTime($sheet, 'O'.$row, $summary->holiday_minutes ?? 0);
                $this->writeMinutesAsTime($sheet, 'P'.$row, $summary->night_minutes ?? 0);

                // Q: 遅刻早退（承認済みの休暇がある日は遅刻早退として扱わない）
                $lateEarlyMinutes = $this->lateEarlyLeaveDisplay->lateMinutes($summary)
                    + $this->lateEarlyLeaveDisplay->earlyLeaveMinutes($summary);
                $this->writeMinutesAsTime($sheet, 'Q'.$row, $lateEarlyMinutes);

                // R・S: 備考/申請（ラベルと数値を別の列に分ける）
                //
                // 集計欄の数式は R 列でラベルの位置を特定し、S 列の同じ位置にある
                // 数値を取り出す作りになっている。
                //   例: H4 = SUMPRODUCT(... FIND("有給休暇", $R$7:$R$37) ... $S$7:$S$37 ...)
                // 1セルにまとめると数式が値を拾えなくなるため、必ず2列に分けて書く。
                $dayRequests = $requestMap->get($dateKey, collect());
                [$noteLabel, $noteValue] = $this->buildNoteColumns($summary, $dayRequests, $dailyWorkingMinutes, $effectiveWorkStart);
                $sheet->setCellValue('R'.$row, $noteLabel);
                $sheet->setCellValueExplicit('S'.$row, $noteValue, DataType::TYPE_STRING);
            }

            $currentDate = $currentDate->addDay();
            $row++;
        }
    }

    /**
     * 勤務区分を判定する
     *
     * 判定基準はExcel・CSV共通のWorkTypeAndNoteServiceに一本化している
     * （task#70〜72の経緯: 判定ロジックを2箇所に別々に持っていたことが、
     * 片方だけ直して他方に反映し忘れる不具合の原因になっていた）。
     *
     * @param  string|null  $effectiveWorkStart  実打刻優先の出勤時刻（H:i形式）。
     *                                           summaryのwork_startだけで判定すると、実打刻はあるのに集計バッチが
     *                                           まだ反映していない日を「打刻なし」と誤判定してしまうため、
     *                                           呼び出し側で実打刻を優先した値を渡す。
     */
    private function getWorkType($summary, ?string $effectiveWorkStart): string
    {
        return $this->workTypeAndNoteService->resolveWorkType($summary, $effectiveWorkStart);
    }

    /**
     * 備考/申請列（R列: ラベル, S列: 数値）を生成する
     *
     * 集計欄の数式は R 列でラベルの位置を特定し、S 列の同じ位置にある数値を
     * 取り出す作りになっているため、必ず2列に分けて返す。項目の中身は
     * Excel・CSV共通のWorkTypeAndNoteServiceで判定する（理由はgetWorkType参照）。
     *
     * @param  mixed  $summary  daily_work_summaries レコード
     * @param  Collection  $dayRequests  当日の承認済み申請コレクション
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     * @param  string|null  $effectiveWorkStart  実打刻優先の出勤時刻（H:i形式）。getWorkTypeに渡すものと同じ
     * @return array{0: string, 1: string} [R列テキスト, S列テキスト]
     */
    private function buildNoteColumns($summary, Collection $dayRequests, int $dailyWorkingMinutes, ?string $effectiveWorkStart = null): array
    {
        $entries = $this->workTypeAndNoteService->buildNoteEntries($summary, $dayRequests, $dailyWorkingMinutes, $effectiveWorkStart);

        return [
            implode("\n", array_column($entries, 'label')),
            implode("\n", array_column($entries, 'value')),
        ];
    }

    /**
     * 一時ファイルとして保存
     *
     * @return string ファイルパス
     */
    private function saveToTempFile(Spreadsheet $spreadsheet): string
    {
        // 同時に複数の出力が走っても上書きし合わないよう、ファイル名は毎回変える
        // （ダウンロード時のファイル名は呼び出し側で付ける）
        $tempPath = storage_path('app/temp/attendance_'.bin2hex(random_bytes(8)).'.xlsx');

        // tempディレクトリがなければ作成
        $tempDir = dirname($tempPath);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        // 数式は評価せず、式のまま書き出す。
        // シート側の集計は SUMPRODUCT や配列数式を多用しており、
        // PhpSpreadsheet の計算エンジンでは評価できない。
        // 式のまま保存すれば Excel を開いた時点で再計算される。
        $writer->setPreCalculateFormulas(false);

        $writer->save($tempPath);

        // Spreadsheet内部はWorksheet⇔Spreadsheet間などで循環参照を持つため、
        // ローカル変数のスコープを抜けるだけでは解放されず、PHPのGCサイクル
        // コレクタ任せになる。全従業員分を1プロセスでループ生成する際、
        // 1人あたりの未解放メモリが積み重なりPHPのメモリ上限
        // （本番128M）に達して500エラーになっていた
        // （クライアント報告: Excelの全員出力でエラーになる）。
        // ここで明示的に循環参照を断ち切り、即座に回収できるようにする。
        $spreadsheet->disconnectWorksheets();

        return $tempPath;
    }

    /**
     * 日数を表示用文字列に変換（0の場合は空文字）
     *
     * @param  float  $days  日数
     * @return string 日数文字列（例: "1", "0.5", "1.125"）
     */
    private function formatDays(float $days): string
    {
        if ($days == 0) {
            return '';
        }

        return rtrim(rtrim(number_format($days, 4), '0'), '.');
    }

    /**
     * 分を実際の数値（1日を1とする時刻の端数）としてセルへ書き込み、[h]:mm書式を当てる
     *
     * 表示用の文字列（"6:30"など）をそのまま書き込むと、見た目は同じでも
     * セルが文字列扱いになり、38行目の合計欄（=SUM(M7:M37)等）が計算できず
     * 常に0になってしまう（クライアント報告: 合計値が計算されていない）。
     * [h]:mmは24時間を超えても時間表示が崩れない書式で、テンプレート内の
     * 他の時間セルと同じもの。0分は空欄のままにする（既存の見た目を変えない）。
     *
     * @param  int  $minutes  分数
     */
    private function writeMinutesAsTime($sheet, string $cell, int $minutes): void
    {
        if ($minutes <= 0) {
            return;
        }

        $sheet->setCellValue($cell, $minutes / 1440);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('[h]:mm');
    }

    /**
     * 時刻文字列を「HH:MM」形式に変換
     *
     * DBから「HH:MM:SS」形式で返る時刻を「HH:MM」に統一する
     *
     * @param  string|null  $time  時刻文字列（HH:MM:SS or HH:MM）
     * @return string 「HH:MM」形式、またはnullの場合は空文字
     */
    private function formatTimeToHM(?string $time): string
    {
        if (! $time) {
            return '';
        }

        // HH:MM:SS → HH:MM
        return substr($time, 0, 5);
    }
}

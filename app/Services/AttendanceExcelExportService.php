<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RequestStatusEnum;
use App\Models\User;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\RequestRepositoryInterface;
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
     * 曜日の表記（日曜始まり）
     */
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    public function __construct(
        private readonly RawStampTimeService $rawStampTimeService,
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly RequestRepositoryInterface $requestRepository,
    ) {}

    /**
     * 勤務実績をExcelファイルとして生成
     *
     * @param  int  $companyId  会社ID
     * @param  User  $user  ユーザー
     * @param  string  $periodStart  開始日（Y-m-d形式）
     * @param  string  $periodEnd  終了日（Y-m-d形式）
     * @return string 一時ファイルパス
     */
    public function generate(int $companyId, User $user, string $periodStart, string $periodEnd): string
    {
        $startDate = CarbonImmutable::parse($periodStart);
        $endDate = CarbonImmutable::parse($periodEnd);

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

        // 月間サマリーを計算
        $monthlySummary = $this->calculateMonthlySummary($summaries, $approvedRequests, $dailyWorkingMinutes);

        // テンプレートを読み込み
        $spreadsheet = $this->loadTemplate();
        $sheet = $spreadsheet->getActiveSheet();

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
        $this->setDetailData($sheet, $summaries, $startDate, $endDate, $requestMap, $dailyWorkingMinutes, $rawTimes);

        // 合計行を設定（38行目）

        // 一時ファイルとして保存
        return $this->saveToTempFile($spreadsheet, $user, $endDate);
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
            $days = (float) $this->calculateLeaveDays($typeId, $request, $dailyWorkingMinutes);

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
    private function setDetailData($sheet, Collection $summaries, CarbonImmutable $startDate, CarbonImmutable $endDate, Collection $requestMap, int $dailyWorkingMinutes, array $rawTimes = []): void
    {
        // 日付をキーにしたマップを作成
        $summaryMap = $summaries->keyBy(fn ($s) => $s->work_date->format('Y-m-d'));

        $currentDate = $startDate;
        $row = self::DATA_START_ROW;

        while ($currentDate->lte($endDate)) {
            $dateKey = $currentDate->format('Y-m-d');
            $summary = $summaryMap->get($dateKey);

            // A: 日付（例: 12/1(月)）
            $dateText = $currentDate->format('n/j').'('.self::WEEKDAYS[$currentDate->dayOfWeek].')';
            $sheet->setCellValue('A'.$row, $dateText);

            // B: 勤務区分
            $workType = $this->getWorkType($summary, $currentDate);
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
                $raw = $rawTimes[$dateKey] ?? null;
                $sheet->setCellValue('G'.$row, $raw['work_start'] ?? $summary->work_start?->format('H:i') ?? '');
                $sheet->setCellValue('H'.$row, $raw['work_end'] ?? $summary->work_end?->format('H:i') ?? '');

                // I〜L: 休憩の入り・出（2枠。こちらも実打刻）
                $breaks = $raw['breaks'] ?? [];
                if ($breaks === []) {
                    $breaks = $this->breakPeriodsFor($summary);
                }
                $sheet->setCellValue('I'.$row, $breaks[0]['start'] ?? '');
                $sheet->setCellValue('J'.$row, $breaks[0]['end'] ?? '');
                $sheet->setCellValue('K'.$row, $breaks[1]['start'] ?? '');
                $sheet->setCellValue('L'.$row, $breaks[1]['end'] ?? '');

                // M: 労働時間
                $sheet->setCellValue('M'.$row, $this->formatMinutesToHM($summary->net_work_minutes ?? 0));

                // N: 時間外
                $sheet->setCellValue('N'.$row, $this->formatMinutesToHM($summary->overtime_minutes ?? 0));

                // O: 休日
                $sheet->setCellValue('O'.$row, $this->formatMinutesToHM($summary->holiday_minutes ?? 0));

                // P: 深夜
                $sheet->setCellValue('P'.$row, $this->formatMinutesToHM($summary->night_minutes ?? 0));

                // Q: 遅刻早退
                $lateEarlyMinutes = ($summary->late_minutes ?? 0) + ($summary->early_leave_minutes ?? 0);
                $sheet->setCellValue('Q'.$row, $this->formatMinutesToHM($lateEarlyMinutes));

                // R・S: 備考/申請（ラベルと数値を別の列に分ける）
                //
                // 集計欄の数式は R 列でラベルの位置を特定し、S 列の同じ位置にある
                // 数値を取り出す作りになっている。
                //   例: H4 = SUMPRODUCT(... FIND("有給休暇", $R$7:$R$37) ... $S$7:$S$37 ...)
                // 1セルにまとめると数式が値を拾えなくなるため、必ず2列に分けて書く。
                $dayRequests = $requestMap->get($dateKey, collect());
                [$noteLabel, $noteValue] = $this->buildNoteColumns($summary, $dayRequests, $dailyWorkingMinutes);
                $sheet->setCellValue('R'.$row, $noteLabel);
                $sheet->setCellValueExplicit('S'.$row, $noteValue, DataType::TYPE_STRING);
            }

            $currentDate = $currentDate->addDay();
            $row++;
        }
    }

    /**
     * 勤務区分を取得
     *
     * @param  mixed  $summary
     */
    /**
     * 休憩の入り・出を最大2枠まで取り出す
     *
     * テンプレートは休憩を2枠（休憩入①/出①、休憩入②/出②）持つ。
     * 打刻から得られる休憩を順に割り当て、無い枠は空欄にする。
     *
     * @param  mixed  $summary
     * @return array<int, array{start: string, end: string}>
     */
    private function breakPeriodsFor($summary): array
    {
        $periods = $summary->break_periods ?? null;

        if (is_string($periods)) {
            $periods = json_decode($periods, true);
        }

        if (! is_array($periods)) {
            return [];
        }

        return collect($periods)
            ->take(2)
            ->map(fn ($period) => [
                'start' => $this->formatTimeToHM($period['start'] ?? null),
                'end' => $this->formatTimeToHM($period['end'] ?? null),
            ])
            ->values()
            ->all();
    }

    private function getWorkType($summary, CarbonImmutable $date): string
    {
        // 土日判定
        if ($date->isSaturday() || $date->isSunday()) {
            if ($summary?->work_start !== null) {
                return '休出';
            }

            return '休日';
        }

        // 休暇種別がある場合
        if ($summary?->leave_type !== null) {
            return $summary->leave_type->label();
        }

        // 出勤している場合
        if ($summary?->work_start !== null) {
            return '出勤';
        }

        // シフトが割り当てられているのに出退勤がなく、休暇の申請もない日は欠勤。
        // 集計欄の欠勤日数は =COUNTIFS(B7:B37,"欠勤") でこの表記を数えている。
        if ($summary?->scheduled_start_time !== null) {
            return '欠勤';
        }

        return '';
    }

    /**
     * 備考/申請列（O列: 種別ラベル, P列: 数値）を生成
     *
     * - 時間系申請（遅刻・早退・残業）: {N}H 形式
     * - 日数系申請（有給・特別休暇・欠勤等）: leave_minutes ÷ 1日所定分 で小数表示
     * - 1日に複数申請がある場合は改行区切りで表示
     *
     * @param  mixed  $summary  daily_work_summaries レコード
     * @param  Collection  $dayRequests  当日の承認済み申請コレクション
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     * @return array{0: string, 1: string} [O列テキスト, P列テキスト]
     */
    private function buildNoteColumns($summary, Collection $dayRequests, int $dailyWorkingMinutes): array
    {
        // 時間系の申請種別ID（遅刻=3, 早退=4, 残業申請=7）
        $hourlyTypeIds = [3, 4, 7];

        $labels = [];
        $values = [];

        foreach ($dayRequests as $request) {
            $typeName = $request->applicationType?->name ?? '';
            $typeId = $request->type;

            if (in_array($typeId, $hourlyTypeIds, true)) {
                // 時間系: 対応する minutes フィールドから {N}H 形式で出力
                $minutes = match ($typeId) {
                    3 => $summary?->late_minutes ?? 0,
                    4 => $summary?->early_leave_minutes ?? 0,
                    7 => $summary?->overtime_minutes ?? 0,
                    default => 0,
                };
                $valueStr = $minutes > 0 ? ((int) round($minutes / 60)).'H' : '';
            } else {
                // 日数系: 申請種別に応じた日数計算
                $valueStr = $this->calculateLeaveDays($typeId, $request, $dailyWorkingMinutes);
            }

            $labels[] = $typeName;
            $values[] = $valueStr;
        }

        return [
            implode("\n", array_filter($labels)),
            implode("\n", array_filter($values)),
        ];
    }

    /**
     * 休暇申請の日数を計算
     *
     * @param  int  $typeId  申請種別ID
     * @param  mixed  $request  申請レコード
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     * @return string 日数文字列（例: "1.0", "0.5", "0.125"）
     */
    private function calculateLeaveDays(int $typeId, $request, int $dailyWorkingMinutes): string
    {
        // 半日有給（type=9）: 常に0.5日
        if ($typeId === 9) {
            return '0.5';
        }

        // 時間有給（type=10）: start_time/end_time から時間数を算出し、1日所定時間で割る
        if ($typeId === 10 && $request->start_time && $request->end_time) {
            $start = CarbonImmutable::parse($request->start_time);
            $end = CarbonImmutable::parse($request->end_time);
            $leaveMinutes = (int) $start->diffInMinutes($end);

            if ($dailyWorkingMinutes > 0 && $leaveMinutes > 0) {
                $days = round($leaveMinutes / $dailyWorkingMinutes, 4);

                return rtrim(rtrim(number_format($days, 4), '0'), '.');
            }

            return '0';
        }

        // 全日有給（type=1）/ 特別休暇（type=5）/ 欠勤（type=6）/ その他: 1.0日
        return '1.0';
    }

    /**
     * 一時ファイルとして保存
     *
     * @return string ファイルパス
     */
    private function saveToTempFile(Spreadsheet $spreadsheet, User $user, CarbonImmutable $targetMonth): string
    {
        $filename = sprintf(
            '勤務実績_%s_%s.xlsx',
            $user->name,
            $targetMonth->format('Y年m月')
        );

        $tempPath = storage_path('app/temp/'.$filename);

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
     * 分を「H:MM」形式に変換
     *
     * @param  int  $minutes  分数
     * @return string 「H:MM」形式の文字列
     */
    private function formatMinutesToHM(int $minutes): string
    {
        if ($minutes === 0) {
            return '';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
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

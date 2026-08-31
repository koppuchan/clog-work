<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RequestStatusEnum;
use App\Models\User;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\RequestRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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

    public function __construct(
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
        $this->setHeaderData($sheet, $user, $endDate, $monthlySummary, $dailyWorkingMinutes);

        // 明細部分を設定
        $this->setDetailData($sheet, $summaries, $startDate, $endDate, $requestMap, $dailyWorkingMinutes);

        // 合計行を設定（38行目）
        $this->setTotalRow($sheet, $monthlySummary, $dailyWorkingMinutes);

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
    private function setHeaderData($sheet, User $user, CarbonImmutable $targetMonth, array $monthlySummary, int $dailyWorkingMinutes): void
    {
        // 年度を設定（E1）
        $fiscalYear = $targetMonth->month >= 4 ? $targetMonth->year : $targetMonth->year - 1;
        $sheet->setCellValue('E1', $fiscalYear.'年度');

        // Row 4: スタッフ情報とサマリー
        $sheet->setCellValue('A'.self::HEADER_ROW, $user->employee_code ?? '');
        $sheet->setCellValue('B'.self::HEADER_ROW, $user->name);

        // 所属部門
        $user->loadMissing('departments');
        $primaryDepartment = $user->departments->where('pivot.is_primary', true)->first();
        $sheet->setCellValue('D'.self::HEADER_ROW, $primaryDepartment?->name ?? '');

        // サマリーデータ
        $sheet->setCellValue('E'.self::HEADER_ROW, $monthlySummary['work_days']);
        $sheet->setCellValue('F'.self::HEADER_ROW, $this->formatDays($monthlySummary['paid_leave_days']));
        $sheet->setCellValue('G'.self::HEADER_ROW, $this->formatDays($monthlySummary['absence_days']));
        $sheet->setCellValue('H'.self::HEADER_ROW, $this->formatDays($monthlySummary['special_leave_days']));
        $sheet->setCellValue('J'.self::HEADER_ROW, $monthlySummary['late_count'] + $monthlySummary['early_leave_count']);
        $sheet->setCellValue('K'.self::HEADER_ROW, $this->formatMinutesToHM($monthlySummary['total_net_work_minutes']));
        // L4: 所定時間 = 出勤日数 × 1日所定時間
        $scheduledMinutes = $monthlySummary['work_days'] * $dailyWorkingMinutes;
        $sheet->setCellValue('L'.self::HEADER_ROW, $this->formatMinutesToHM($scheduledMinutes));
        $sheet->setCellValue('M'.self::HEADER_ROW, $this->formatMinutesToHM($monthlySummary['total_overtime_minutes']));
        $sheet->setCellValue('N'.self::HEADER_ROW, $this->formatMinutesToHM($monthlySummary['total_holiday_minutes']));
        $sheet->setCellValue('O'.self::HEADER_ROW, $this->formatMinutesToHM($monthlySummary['total_night_minutes']));
        $sheet->setCellValue('P'.self::HEADER_ROW, $this->formatMinutesToHM($monthlySummary['total_late_minutes'] + $monthlySummary['total_early_leave_minutes']));
    }

    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * 明細部分にデータを設定
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $sheet
     * @param  Collection  $requestMap  日付をキーにした承認済み申請マップ
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     */
    private function setDetailData($sheet, Collection $summaries, CarbonImmutable $startDate, CarbonImmutable $endDate, Collection $requestMap, int $dailyWorkingMinutes): void
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
                // C: シフト開始時刻（HH:MM形式）
                $sheet->setCellValue('C'.$row, $this->formatTimeToHM($summary->scheduled_start_time));

                // D: シフト終了時刻（HH:MM形式）
                $sheet->setCellValue('D'.$row, $this->formatTimeToHM($summary->scheduled_end_time));

                // E: シフト休憩時間（現在のデータソースにない）

                // F: 出勤時刻
                $sheet->setCellValue('F'.$row, $summary->work_start?->format('H:i') ?? '');

                // G: 退勤時刻
                $sheet->setCellValue('G'.$row, $summary->work_end?->format('H:i') ?? '');

                // H: 休憩時間
                $sheet->setCellValue('H'.$row, $this->formatMinutesToHM($summary->break_minutes ?? 0));

                // I: 実働時間
                $sheet->setCellValue('I'.$row, $this->formatMinutesToHM($summary->net_work_minutes ?? 0));

                // J: 所定時間（シフトが設定されていれば1日所定時間を表示）
                if ($summary->scheduled_start_time && $summary->scheduled_end_time) {
                    $sheet->setCellValue('J'.$row, $this->formatMinutesToHM($dailyWorkingMinutes));
                }

                // K: 所定外残業時間
                $sheet->setCellValue('K'.$row, $this->formatMinutesToHM($summary->overtime_minutes ?? 0));

                // L: 休日残業時間
                $sheet->setCellValue('L'.$row, $this->formatMinutesToHM($summary->holiday_minutes ?? 0));

                // M: 深夜時間
                $sheet->setCellValue('M'.$row, $this->formatMinutesToHM($summary->night_minutes ?? 0));

                // N: 遅刻早退時間
                $lateEarlyMinutes = ($summary->late_minutes ?? 0) + ($summary->early_leave_minutes ?? 0);
                $sheet->setCellValue('N'.$row, $this->formatMinutesToHM($lateEarlyMinutes));

                // O・P: 備考/申請（申請種別ラベルと申請数値）
                $dayRequests = $requestMap->get($dateKey, collect());
                [$noteLabel, $noteValue] = $this->buildNoteColumns($summary, $dayRequests, $dailyWorkingMinutes);
                $sheet->setCellValue('O'.$row, $noteLabel);
                $sheet->setCellValue('P'.$row, $noteValue);
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

    private const TOTAL_ROW = 38;

    /**
     * 合計行（38行目）にI〜N列の合計を設定
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $sheet
     * @param  array<string, int|float>  $monthlySummary
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     */
    private function setTotalRow($sheet, array $monthlySummary, int $dailyWorkingMinutes): void
    {
        // I38: 実働時間合計
        $sheet->setCellValue('I'.self::TOTAL_ROW, $this->formatMinutesToHM($monthlySummary['total_net_work_minutes']));

        // J38: 所定時間合計
        $scheduledMinutes = $monthlySummary['work_days'] * $dailyWorkingMinutes;
        $sheet->setCellValue('J'.self::TOTAL_ROW, $this->formatMinutesToHM($scheduledMinutes));

        // K38: 所定外残業時間合計
        $sheet->setCellValue('K'.self::TOTAL_ROW, $this->formatMinutesToHM($monthlySummary['total_overtime_minutes']));

        // L38: 休日残業時間合計
        $sheet->setCellValue('L'.self::TOTAL_ROW, $this->formatMinutesToHM($monthlySummary['total_holiday_minutes']));

        // M38: 深夜時間合計
        $sheet->setCellValue('M'.self::TOTAL_ROW, $this->formatMinutesToHM($monthlySummary['total_night_minutes']));

        // N38: 遅刻早退時間合計
        $sheet->setCellValue('N'.self::TOTAL_ROW, $this->formatMinutesToHM($monthlySummary['total_late_minutes'] + $monthlySummary['total_early_leave_minutes']));
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

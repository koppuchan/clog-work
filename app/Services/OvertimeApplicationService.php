<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Request;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;

/**
 * 残業申請反映サービス
 *
 * 承認された残業申請を勤務実績（daily_work_summaries）に反映する
 */
class OvertimeApplicationService
{
    private const OVERTIME_APPLICATION_TYPE_ID = 7;

    public function __construct(
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly ShiftRepositoryInterface $shiftRepository
    ) {}

    /**
     * 残業申請を勤務実績に反映
     *
     * @param  Request  $request  承認された残業申請
     */
    /**
     * 残業申請の勤務実績反映（無効化済み）
     *
     * 時間外は打刻ベースの自動計算値を常に使用するため、
     * 残業申請の承認では勤務実績を上書きしない。
     */
    public function applyOvertimeToWorkSummary(Request $request): void
    {
        // 勤務実績の overtime_minutes は打刻ベースの自動計算値を維持する
    }

    /**
     * 残業申請の勤務実績反映取り消し（無効化済み）
     *
     * applyOvertimeToWorkSummary が無効化されているため、取り消し処理も不要。
     */
    public function removeOvertimeFromWorkSummary(Request $request): void
    {
        // 勤務実績を変更していないため、取り消し処理は不要
    }

    /**
     * 残業申請かどうかを判定
     *
     * @param  int  $applicationType  申請種別
     */
    public static function isOvertimeApplication(int $applicationType): bool
    {
        return $applicationType === self::OVERTIME_APPLICATION_TYPE_ID;
    }

    /**
     * 残業申請の時間を計算（分単位）
     *
     * @param  Request  $request  申請
     * @return int 残業時間（分）
     */
    private function calculateOvertimeMinutes(Request $request): int
    {
        $startMinutes = $this->timeToMinutes($request->start_time);
        $endMinutes = $this->timeToMinutes($request->end_time);

        return max(0, $endMinutes - $startMinutes);
    }

    /**
     * 自動計算値で残業時間を再計算
     *
     * @param  Request  $request  申請
     * @param  \App\Models\DailyWorkSummary  $dailySummary  日次勤務実績
     * @return int 残業時間（分）
     */
    private function recalculateOvertime(Request $request, $dailySummary): int
    {
        $targetDate = $request->target_date->format('Y-m-d');

        $shifts = $this->shiftRepository->findByUserIdAndDateRange(
            $request->company_id,
            $request->requested_by,
            $targetDate,
            $targetDate
        );

        $shift = $shifts->first();
        $scheduledWorkMinutes = $shift?->shiftPattern?->work_minutes ?? 0;

        // work_minutesが未設定の場合、始業・終業時刻と休憩時間から算出
        if ($scheduledWorkMinutes <= 0 && $shift?->shiftPattern?->start_time && $shift?->shiftPattern?->end_time) {
            $startMinutes = $this->timeToMinutes($shift->shiftPattern->start_time);
            $endMinutes = $this->timeToMinutes($shift->shiftPattern->end_time);

            // 日付越えシフトの場合
            if ($endMinutes < $startMinutes) {
                $endMinutes += 24 * 60;
            }

            $totalShiftMinutes = $endMinutes - $startMinutes;
            $patternBreakMinutes = $shift->shiftPattern->break_minutes ?? 0;
            $scheduledWorkMinutes = max(0, $totalShiftMinutes - $patternBreakMinutes);
        }

        if ($scheduledWorkMinutes <= 0) {
            return 0;
        }

        return max(0, ($dailySummary->net_work_minutes ?? 0) - $scheduledWorkMinutes);
    }

    /**
     * 時刻文字列を分に変換
     *
     * @param  string  $time  時刻（HH:MM形式）
     * @return int 0時からの分数
     */
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return (int) $parts[0] * 60 + (int) ($parts[1] ?? 0);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Models\Request;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;

/**
 * 休暇申請反映サービス
 *
 * 承認された休暇申請を勤務実績（daily_work_summaries）に反映する
 */
class LeaveApplicationService
{
    private const DEFAULT_DAILY_WORKING_HOURS = 8;

    private const HALF_DAY_TOLERANCE_MINUTES = 30;

    public function __construct(
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly CompanyRepositoryInterface $companyRepository
    ) {}

    /**
     * 休暇申請を勤務実績に反映
     *
     * @param  Request  $request  承認された休暇申請
     */
    public function applyLeaveToWorkSummary(Request $request): void
    {
        $leaveType = LeaveTypeEnum::fromApplicationType($request->type);

        if ($leaveType === null) {
            return;
        }

        $company = $this->companyRepository->findById($request->company_id);
        $leaveMinutes = $this->calculateLeaveMinutes($request, $company);

        $this->dailyWorkSummaryRepository->upsert(
            $request->company_id,
            $request->requested_by,
            $request->target_date->format('Y-m-d'),
            [
                'leave_type' => $leaveType->value,
                'leave_minutes' => $leaveMinutes,
                'record_source' => RecordSourceEnum::REQUEST->value,
            ]
        );
    }

    /**
     * 休暇申請の勤務実績反映を取り消す
     *
     * @param  Request  $request  取り消し対象の休暇申請
     */
    public function removeLeaveFromWorkSummary(Request $request): void
    {
        $leaveType = LeaveTypeEnum::fromApplicationType($request->type);

        if ($leaveType === null) {
            return;
        }

        $this->dailyWorkSummaryRepository->upsert(
            $request->company_id,
            $request->requested_by,
            $request->target_date->format('Y-m-d'),
            [
                'leave_type' => null,
                'leave_minutes' => null,
            ]
        );
    }

    /**
     * 休暇申請かどうかを判定
     *
     * @param  int  $applicationType  申請種別
     */
    public static function isLeaveApplication(int $applicationType): bool
    {
        return LeaveTypeEnum::isLeaveApplication($applicationType);
    }

    /**
     * 休暇時間を計算（分単位）
     *
     * @param  Request  $request  申請
     * @param  mixed  $company  会社（daily_working_hours取得用）
     * @return int|null 休暇時間（分）、終日の場合はnull
     */
    private function calculateLeaveMinutes(Request $request, $company): ?int
    {
        // 時間指定がない場合は終日休暇
        if (! $request->start_time || ! $request->end_time) {
            return null;
        }

        // 時間差を計算
        $startMinutes = $this->timeToMinutes($request->start_time);
        $endMinutes = $this->timeToMinutes($request->end_time);

        return $endMinutes - $startMinutes;
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

    /**
     * 半日休暇かどうかを判定
     *
     * @param  int  $leaveMinutes  休暇時間（分）
     * @param  mixed  $company  会社
     */
    public function isHalfDay(int $leaveMinutes, $company): bool
    {
        $dailyWorkingMinutes = (int) (($company->daily_working_hours ?? self::DEFAULT_DAILY_WORKING_HOURS) * 60);
        $halfDayMinutes = (int) ($dailyWorkingMinutes / 2);

        // 所定労働時間の約半分（±30分の許容）なら半日とみなす
        return abs($leaveMinutes - $halfDayMinutes) <= self::HALF_DAY_TOLERANCE_MINUTES;
    }
}

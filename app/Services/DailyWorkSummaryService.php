<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Exceptions\NotFoundException;
use App\Models\DailyWorkSummary;
use App\Models\TimeRecordCorrection;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\TimeRecordCorrectionRepositoryInterface;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 勤務実績サービス
 */
class DailyWorkSummaryService
{
    private const NOTE_ADMIN_CORRECTION = '管理者による修正';

    private const NOTE_ADMIN_ADDITION = '管理者による追加';

    private const CORRECTION_SOURCE_REQUEST = 'request';

    private const CORRECTION_SOURCE_ADMIN = 'admin';

    private const DEFAULT_DAILY_WORKING_MINUTES = 480;

    public function __construct(
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly TimeRecordRepositoryInterface $timeRecordRepository,
        private readonly TimeRecordCorrectionRepositoryInterface $timeRecordCorrectionRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly DailyWorkSummaryBatchService $dailyWorkSummaryBatchService,
        private readonly TimeRoundingService $timeRoundingService,
        private readonly RawStampTimeService $rawStampTimeService,
        private readonly LateEarlyLeaveDisplay $lateEarlyLeaveDisplay
    ) {}

    /**
     * IDで勤務実績を取得
     *
     * @param  int  $id  勤務実績ID
     *
     * @throws NotFoundException 勤務実績が見つからない場合
     */
    public function findById(int $id): DailyWorkSummary
    {
        $dailyWorkSummary = $this->dailyWorkSummaryRepository->findById($id);

        if (! $dailyWorkSummary) {
            throw new NotFoundException('勤務実績が見つかりません。');
        }

        return $dailyWorkSummary;
    }

    /**
     * ユーザーの日付範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function getByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate): Collection
    {
        return $this->dailyWorkSummaryRepository->findByUserIdAndDateRange($companyId, $userId, $startDate, $endDate);
    }

    /**
     * 会社全体の日付範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function getByCompanyIdAndDateRange(int $companyId, string $startDate, string $endDate): Collection
    {
        return $this->dailyWorkSummaryRepository->findByCompanyIdAndDateRange($companyId, $startDate, $endDate);
    }

    /**
     * ユーザーの指定日の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $date  日付（Y-m-d形式）
     */
    public function getByUserIdAndDate(int $companyId, int $userId, string $date): ?DailyWorkSummary
    {
        return $this->dailyWorkSummaryRepository->findByUserIdAndDate($companyId, $userId, $date);
    }

    /**
     * 複数ユーザーの日付範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function getByUserIdsAndDateRange(int $companyId, array $userIds, string $startDate, string $endDate): Collection
    {
        if (empty($userIds)) {
            return new Collection;
        }

        return $this->dailyWorkSummaryRepository->findByUserIdsAndDateRange($companyId, $userIds, $startDate, $endDate);
    }

    /**
     * 月間の勤務サマリーを計算
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return array{
     *     total_work_minutes: int,
     *     total_break_minutes: int,
     *     total_net_work_minutes: int,
     *     total_overtime_minutes: int,
     *     total_night_minutes: int,
     *     total_holiday_minutes: int,
     *     total_late_minutes: int,
     *     total_early_leave_minutes: int,
     *     late_count: int,
     *     early_leave_count: int,
     *     work_days: int,
     *     paid_leave_days: float,
     *     special_leave_days: float,
     *     absence_days: float
     * }
     */
    public function calculateMonthlySummary(int $companyId, int $userId, string $startDate, string $endDate): array
    {
        $summaries = $this->getByUserIdAndDateRange($companyId, $userId, $startDate, $endDate);

        // 会社のデフォルトシフトパターンから所定労働時間を取得
        $company = $this->companyRepository->findById($companyId);
        $dailyWorkingMinutes = $company?->defaultShiftPattern?->work_minutes ?? self::DEFAULT_DAILY_WORKING_MINUTES;

        return [
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
            'work_days' => $summaries->count(),
            'paid_leave_days' => $this->countLeaveDays($summaries, LeaveTypeEnum::PAID_LEAVE, $dailyWorkingMinutes),
            'special_leave_days' => $this->countLeaveDays($summaries, LeaveTypeEnum::SPECIAL_LEAVE, $dailyWorkingMinutes),
            'absence_days' => $this->countLeaveDays($summaries, LeaveTypeEnum::ABSENCE, $dailyWorkingMinutes),
        ];
    }

    /**
     * 休暇日数を計算
     *
     * 終日休暇は1.0日、時間休暇は所定労働時間に対する割合で計算
     *
     * @param  Collection  $summaries  日次勤務実績コレクション
     * @param  LeaveTypeEnum  $leaveType  休暇種別
     * @param  int  $dailyWorkingMinutes  1日の所定労働時間（分）
     * @return float 休暇日数
     */
    private function countLeaveDays(Collection $summaries, LeaveTypeEnum $leaveType, int $dailyWorkingMinutes): float
    {
        $days = 0.0;

        foreach ($summaries as $daily) {
            if ($daily->leave_type !== $leaveType) {
                continue;
            }

            if ($daily->leave_minutes === null) {
                $days += 1.0;
            } else {
                $days += round($daily->leave_minutes / $dailyWorkingMinutes, 2);
            }
        }

        return $days;
    }

    /**
     * 勤務時間を新規作成または更新
     *
     * DailyWorkSummaryが存在しない日でも勤務時間を登録できる。
     * 既存レコードがあれば updateWorkTimes で更新、なければ空レコードを作成後に更新。
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $workDate  勤務日（Y-m-d形式）
     * @param  ?string  $workStart  勤務開始時刻（H:i形式）
     * @param  ?string  $workEnd  勤務終了時刻（H:i形式）
     * @param  array<int, array{start: string, end: string}>  $breakPeriods  休憩時間帯の配列
     */
    public function createOrUpdateWorkTimes(
        int $companyId,
        int $userId,
        string $workDate,
        ?string $workStart,
        ?string $workEnd,
        array $breakPeriods = [],
        ?int $correctedBy = null
    ): DailyWorkSummary {
        $existing = $this->dailyWorkSummaryRepository->findByUserIdAndDate($companyId, $userId, $workDate);

        if ($existing) {
            return $this->updateWorkTimes($existing->id, $workStart, $workEnd, $breakPeriods, $correctedBy);
        }

        // DailyWorkSummaryが存在しない場合、空レコードを作成
        $summary = $this->dailyWorkSummaryRepository->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'work_date' => $workDate,
            'work_minutes' => 0,
            'break_minutes' => 0,
            'net_work_minutes' => 0,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        return $this->updateWorkTimes($summary->id, $workStart, $workEnd, $breakPeriods, $correctedBy);
    }

    /**
     * 勤務実績を削除（打刻レコードと勤務サマリーを削除）
     *
     * 対象日のtime_records（出勤・退勤・休憩・日跨ぎ退勤）を一括削除し、
     * DailyWorkSummary本体も削除する。修正履歴は監査目的のため保持される。
     *
     * @param  int  $id  勤務実績ID
     *
     * @throws NotFoundException 勤務実績が見つからない場合
     */
    public function deleteWorkTimes(int $id): void
    {
        $summary = $this->findById($id);
        $companyId = $summary->company_id;
        $userId = $summary->user_id;
        $workDate = $summary->work_date->format('Y-m-d');

        DB::transaction(function () use ($companyId, $userId, $workDate, $summary) {
            // 対象日の打刻レコード
            $todayRecords = $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $workDate);

            // 翌日の日跨ぎ退勤レコードと、日跨ぎ退勤時刻以前の翌日休憩レコードもマージして削除対象に含める
            // （夜勤の翌朝休憩や日跨ぎ休憩は翌日の日付に正規化されて保存されるため、
            // 削除対象に含めないと孤立したレコードが残ってしまう）
            $nextDate = CarbonImmutable::parse($workDate)->addDay()->format('Y-m-d');
            $nextDayRecords = $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $nextDate);
            $nextDayCrossEnd = $nextDayRecords->first(
                fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
            );

            $nextDayRecordsToDelete = collect();
            if ($nextDayCrossEnd !== null) {
                $nextDayRecordsToDelete = $nextDayRecords->filter(function ($r) use ($nextDayCrossEnd) {
                    if ($r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY) {
                        return true;
                    }
                    if ($r->record_type === TimeRecordTypeEnum::BREAK_START
                        || $r->record_type === TimeRecordTypeEnum::BREAK_END) {
                        return $r->record_time->lte($nextDayCrossEnd->record_time);
                    }

                    return false;
                });
            }

            $targetIds = $todayRecords->merge($nextDayRecordsToDelete)
                ->pluck('id')
                ->all();

            // 打刻レコードを一括削除
            $this->timeRecordRepository->deleteByIds($targetIds);

            // 勤務サマリー本体を削除
            $this->dailyWorkSummaryRepository->delete($summary->id);
        });
    }

    /**
     * 勤務時間を更新（time_records経由 + バッチ再計算方式）
     *
     * 打刻修正申請の承認と同じ方式で、time_recordsを更新後に
     * DailyWorkSummaryBatchServiceで全フィールドを再計算する。
     *
     * @param  int  $id  勤務実績ID
     * @param  ?string  $workStart  勤務開始時刻（H:i形式）
     * @param  ?string  $workEnd  勤務終了時刻（H:i形式）
     * @param  array<int, array{start: string, end: string}>  $breakPeriods  休憩時間帯の配列
     *
     * @throws NotFoundException 勤務実績が見つからない場合
     */
    public function updateWorkTimes(int $id, ?string $workStart, ?string $workEnd, array $breakPeriods = [], ?int $correctedBy = null): DailyWorkSummary
    {
        $summary = $this->findById($id);
        $companyId = $summary->company_id;
        $userId = $summary->user_id;
        $workDate = $summary->work_date->format('Y-m-d');

        DB::transaction(function () use ($companyId, $userId, $workDate, $workStart, $workEnd, $breakPeriods, $summary, $correctedBy) {
            $todayRecords = $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $workDate);

            // 翌日の日付越え退勤レコードと、日跨ぎ退勤時刻以前の休憩レコードもマージ
            // （夜勤の翌朝休憩や日跨ぎ休憩は翌日の日付に正規化されて保存されるため）
            $nextDate = CarbonImmutable::parse($workDate)->addDay()->format('Y-m-d');
            $nextDayRecords = $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $nextDate);
            $nextDayCrossEnd = $nextDayRecords->first(
                fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
            );
            if ($nextDayCrossEnd !== null) {
                $carriedNextDayRecords = $nextDayRecords->filter(function ($r) use ($nextDayCrossEnd) {
                    if ($r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY) {
                        return true;
                    }
                    if ($r->record_type === TimeRecordTypeEnum::BREAK_START
                        || $r->record_type === TimeRecordTypeEnum::BREAK_END) {
                        return $r->record_time->lte($nextDayCrossEnd->record_time);
                    }

                    return false;
                });
                $todayRecords = $todayRecords->merge($carriedNextDayRecords);
            }

            // WORK_STARTレコードを更新または作成
            if ($workStart !== null) {
                $startDateTime = CarbonImmutable::parse($workDate.' '.$workStart);
                $roundedStart = $this->timeRoundingService->roundTime($companyId, $startDateTime, TimeRecordTypeEnum::WORK_START);
                $workStartRecord = $todayRecords->first(
                    fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_START
                );

                if ($workStartRecord) {
                    // 修正履歴を記録（correctedByが指定されている場合のみ）
                    if ($correctedBy !== null) {
                        $this->timeRecordCorrectionRepository->create([
                            'time_record_id' => $workStartRecord->id,
                            'record_type' => $workStartRecord->record_type->value,
                            'before_record_time' => $workStartRecord->record_time,
                            'before_rounded_time' => $workStartRecord->rounded_time,
                            'before_record_source' => $workStartRecord->record_source->value,
                            'after_record_time' => $startDateTime,
                            'after_rounded_time' => $roundedStart,
                            'after_record_source' => RecordSourceEnum::MANUAL->value,
                            'corrected_by' => $correctedBy,
                            'correction_note' => self::NOTE_ADMIN_CORRECTION,
                        ]);
                    }

                    $this->timeRecordRepository->update($workStartRecord->id, [
                        'record_time' => $startDateTime,
                        'rounded_time' => $roundedStart,
                        'record_source' => RecordSourceEnum::MANUAL,
                        'note' => self::NOTE_ADMIN_CORRECTION,
                    ]);
                } else {
                    $newRecord = $this->timeRecordRepository->create([
                        'company_id' => $companyId,
                        'user_id' => $userId,
                        'record_type' => TimeRecordTypeEnum::WORK_START,
                        'record_time' => $startDateTime,
                        'rounded_time' => $roundedStart,
                        'record_source' => RecordSourceEnum::MANUAL,
                        'note' => self::NOTE_ADMIN_ADDITION,
                    ]);

                    if ($correctedBy !== null) {
                        $this->timeRecordCorrectionRepository->create([
                            'time_record_id' => $newRecord->id,
                            'record_type' => TimeRecordTypeEnum::WORK_START->value,
                            'before_record_time' => $startDateTime,
                            'before_rounded_time' => $roundedStart,
                            'before_record_source' => RecordSourceEnum::MANUAL->value,
                            'after_record_time' => $startDateTime,
                            'after_rounded_time' => $roundedStart,
                            'after_record_source' => RecordSourceEnum::MANUAL->value,
                            'corrected_by' => $correctedBy,
                            'correction_note' => self::NOTE_ADMIN_ADDITION,
                        ]);
                    }
                }
            }

            // WORK_ENDレコードを更新または作成
            if ($workEnd !== null) {
                $endDateTime = CarbonImmutable::parse($workDate.' '.$workEnd);

                // 日跨ぎ判定: 終了時刻が開始時刻より前なら翌日
                $effectiveStart = $workStart ?? $summary->work_start?->format('H:i');
                $isCrossDay = $effectiveStart !== null && $workEnd < $effectiveStart;
                if ($isCrossDay) {
                    $endDateTime = $endDateTime->addDay();
                }

                $recordType = $isCrossDay
                    ? TimeRecordTypeEnum::WORK_END_NEXT_DAY
                    : TimeRecordTypeEnum::WORK_END;

                $roundedEnd = $this->timeRoundingService->roundTime($companyId, $endDateTime, $recordType);

                $workEndRecord = $todayRecords->first(
                    fn ($r) => $r->record_type->isWorkEnd()
                );

                if ($workEndRecord) {
                    // 修正履歴を記録（correctedByが指定されている場合のみ）
                    if ($correctedBy !== null) {
                        $this->timeRecordCorrectionRepository->create([
                            'time_record_id' => $workEndRecord->id,
                            'record_type' => $workEndRecord->record_type->value,
                            'before_record_time' => $workEndRecord->record_time,
                            'before_rounded_time' => $workEndRecord->rounded_time,
                            'before_record_source' => $workEndRecord->record_source->value,
                            'after_record_time' => $endDateTime,
                            'after_rounded_time' => $roundedEnd,
                            'after_record_source' => RecordSourceEnum::MANUAL->value,
                            'corrected_by' => $correctedBy,
                            'correction_note' => self::NOTE_ADMIN_CORRECTION,
                        ]);
                    }

                    $this->timeRecordRepository->update($workEndRecord->id, [
                        'record_type' => $recordType,
                        'record_time' => $endDateTime,
                        'rounded_time' => $roundedEnd,
                        'record_source' => RecordSourceEnum::MANUAL,
                        'note' => self::NOTE_ADMIN_CORRECTION,
                    ]);
                } else {
                    $newRecord = $this->timeRecordRepository->create([
                        'company_id' => $companyId,
                        'user_id' => $userId,
                        'record_type' => $recordType,
                        'record_time' => $endDateTime,
                        'rounded_time' => $roundedEnd,
                        'record_source' => RecordSourceEnum::MANUAL,
                        'note' => self::NOTE_ADMIN_ADDITION,
                    ]);

                    if ($correctedBy !== null) {
                        $this->timeRecordCorrectionRepository->create([
                            'time_record_id' => $newRecord->id,
                            'record_type' => $recordType->value,
                            'before_record_time' => $endDateTime,
                            'before_rounded_time' => $roundedEnd,
                            'before_record_source' => RecordSourceEnum::MANUAL->value,
                            'after_record_time' => $endDateTime,
                            'after_rounded_time' => $roundedEnd,
                            'after_record_source' => RecordSourceEnum::MANUAL->value,
                            'corrected_by' => $correctedBy,
                            'correction_note' => self::NOTE_ADMIN_ADDITION,
                        ]);
                    }
                }
            }

            // 休憩レコードを更新する。
            // 削除→新規作成方式は time_record_corrections の ON DELETE CASCADE で
            // 修正履歴が連鎖削除されてしまうため、既存ペアは可能な限り update で書き換える。
            $existingBreakStarts = $todayRecords->filter(
                fn ($r) => $r->record_type === TimeRecordTypeEnum::BREAK_START
            )->sortBy('record_time')->values();
            $existingBreakEnds = $todayRecords->filter(
                fn ($r) => $r->record_type === TimeRecordTypeEnum::BREAK_END
            )->sortBy('record_time')->values();

            // 休憩日付正規化用の出勤時刻基準（夜勤翌朝休憩を翌日扱いする判定用）
            $workStartForBreakNormalization = $workStart !== null
                ? CarbonImmutable::parse($workDate.' '.$workStart)
                : ($summary->work_start !== null
                    ? CarbonImmutable::parse($summary->work_start)
                    : null);

            // 休憩開始/終了の日付を勤務時刻に合わせて正規化するクロージャ
            // - 休憩開始が出勤時刻より時間が早い場合は翌日扱い（夜勤の翌朝休憩）
            // - 休憩終了 <= 休憩開始 の場合は休憩終了をさらに+1日（日跨ぎ休憩 23:30-00:30 等）
            $normalizeBreakPeriod = function (array $period) use ($workDate, $workStartForBreakNormalization): array {
                $bs = CarbonImmutable::parse($workDate.' '.$period['start']);
                $be = CarbonImmutable::parse($workDate.' '.$period['end']);

                if ($workStartForBreakNormalization !== null && $bs->lt($workStartForBreakNormalization)) {
                    $bs = $bs->addDay();
                    $be = $be->addDay();
                }

                if ($be->lte($bs)) {
                    $be = $be->addDay();
                }

                return [$bs, $be];
            };

            $existingPairCount = min($existingBreakStarts->count(), $existingBreakEnds->count());
            $newPairCount = count($breakPeriods);
            $updatePairCount = min($existingPairCount, $newPairCount);

            // 1. 既存ペアを update で書き換える（修正履歴も記録）
            for ($i = 0; $i < $updatePairCount; $i++) {
                [$breakStartDateTime, $breakEndDateTime] = $normalizeBreakPeriod($breakPeriods[$i]);

                $roundedBreakStart = $this->timeRoundingService->roundTime(
                    $companyId, $breakStartDateTime, TimeRecordTypeEnum::BREAK_START
                );
                $roundedBreakEnd = $this->timeRoundingService->roundTime(
                    $companyId, $breakEndDateTime, TimeRecordTypeEnum::BREAK_END
                );

                $existingStart = $existingBreakStarts[$i];
                $existingEnd = $existingBreakEnds[$i];

                if ($correctedBy !== null) {
                    $this->timeRecordCorrectionRepository->create([
                        'time_record_id' => $existingStart->id,
                        'record_type' => TimeRecordTypeEnum::BREAK_START->value,
                        'before_record_time' => $existingStart->record_time,
                        'before_rounded_time' => $existingStart->rounded_time,
                        'before_record_source' => $existingStart->record_source->value,
                        'after_record_time' => $breakStartDateTime,
                        'after_rounded_time' => $roundedBreakStart,
                        'after_record_source' => RecordSourceEnum::MANUAL->value,
                        'corrected_by' => $correctedBy,
                        'correction_note' => self::NOTE_ADMIN_CORRECTION,
                    ]);

                    $this->timeRecordCorrectionRepository->create([
                        'time_record_id' => $existingEnd->id,
                        'record_type' => TimeRecordTypeEnum::BREAK_END->value,
                        'before_record_time' => $existingEnd->record_time,
                        'before_rounded_time' => $existingEnd->rounded_time,
                        'before_record_source' => $existingEnd->record_source->value,
                        'after_record_time' => $breakEndDateTime,
                        'after_rounded_time' => $roundedBreakEnd,
                        'after_record_source' => RecordSourceEnum::MANUAL->value,
                        'corrected_by' => $correctedBy,
                        'correction_note' => self::NOTE_ADMIN_CORRECTION,
                    ]);
                }

                $this->timeRecordRepository->update($existingStart->id, [
                    'record_time' => $breakStartDateTime,
                    'rounded_time' => $roundedBreakStart,
                    'record_source' => RecordSourceEnum::MANUAL,
                    'note' => self::NOTE_ADMIN_CORRECTION,
                ]);
                $this->timeRecordRepository->update($existingEnd->id, [
                    'record_time' => $breakEndDateTime,
                    'rounded_time' => $roundedBreakEnd,
                    'record_source' => RecordSourceEnum::MANUAL,
                    'note' => self::NOTE_ADMIN_CORRECTION,
                ]);
            }

            // 2. 既存が新規より多い場合: 余剰の既存ペアを削除する
            //    （ON DELETE CASCADE で修正履歴も消えるが、もともとそのペアの履歴は残す必要がない）
            for ($i = $updatePairCount; $i < $existingPairCount; $i++) {
                $this->timeRecordRepository->delete($existingBreakStarts[$i]->id);
                $this->timeRecordRepository->delete($existingBreakEnds[$i]->id);
            }
            // ペアにならない単独の既存BREAK_START/BREAK_END（壊れたデータ）も掃除
            for ($i = $existingPairCount; $i < $existingBreakStarts->count(); $i++) {
                $this->timeRecordRepository->delete($existingBreakStarts[$i]->id);
            }
            for ($i = $existingPairCount; $i < $existingBreakEnds->count(); $i++) {
                $this->timeRecordRepository->delete($existingBreakEnds[$i]->id);
            }

            // 3. 新規が既存より多い場合: 不足分を新規作成
            for ($i = $updatePairCount; $i < $newPairCount; $i++) {
                [$breakStartDateTime, $breakEndDateTime] = $normalizeBreakPeriod($breakPeriods[$i]);

                $roundedBreakStart = $this->timeRoundingService->roundTime(
                    $companyId, $breakStartDateTime, TimeRecordTypeEnum::BREAK_START
                );
                $roundedBreakEnd = $this->timeRoundingService->roundTime(
                    $companyId, $breakEndDateTime, TimeRecordTypeEnum::BREAK_END
                );

                $newBreakStartRecord = $this->timeRecordRepository->create([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'record_type' => TimeRecordTypeEnum::BREAK_START,
                    'record_time' => $breakStartDateTime,
                    'rounded_time' => $roundedBreakStart,
                    'record_source' => RecordSourceEnum::MANUAL,
                    'note' => self::NOTE_ADMIN_ADDITION,
                ]);

                $newBreakEndRecord = $this->timeRecordRepository->create([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'record_type' => TimeRecordTypeEnum::BREAK_END,
                    'record_time' => $breakEndDateTime,
                    'rounded_time' => $roundedBreakEnd,
                    'record_source' => RecordSourceEnum::MANUAL,
                    'note' => self::NOTE_ADMIN_ADDITION,
                ]);

                if ($correctedBy !== null) {
                    $this->timeRecordCorrectionRepository->create([
                        'time_record_id' => $newBreakStartRecord->id,
                        'record_type' => TimeRecordTypeEnum::BREAK_START->value,
                        'before_record_time' => $breakStartDateTime,
                        'before_rounded_time' => $roundedBreakStart,
                        'before_record_source' => RecordSourceEnum::MANUAL->value,
                        'after_record_time' => $breakStartDateTime,
                        'after_rounded_time' => $roundedBreakStart,
                        'after_record_source' => RecordSourceEnum::MANUAL->value,
                        'corrected_by' => $correctedBy,
                        'correction_note' => self::NOTE_ADMIN_ADDITION,
                    ]);

                    $this->timeRecordCorrectionRepository->create([
                        'time_record_id' => $newBreakEndRecord->id,
                        'record_type' => TimeRecordTypeEnum::BREAK_END->value,
                        'before_record_time' => $breakEndDateTime,
                        'before_rounded_time' => $roundedBreakEnd,
                        'before_record_source' => RecordSourceEnum::MANUAL->value,
                        'after_record_time' => $breakEndDateTime,
                        'after_rounded_time' => $roundedBreakEnd,
                        'after_record_source' => RecordSourceEnum::MANUAL->value,
                        'corrected_by' => $correctedBy,
                        'correction_note' => self::NOTE_ADMIN_ADDITION,
                    ]);
                }
            }

            // DailyWorkSummaryのrecord_sourceをAUTOに戻す（バッチ再計算を可能にする）
            $this->dailyWorkSummaryRepository->update($summary->id, [
                'record_source' => RecordSourceEnum::AUTO,
            ]);

            // バッチ再計算（打刻修正申請の承認と同じ方式）
            $company = $this->companyRepository->findById($companyId);
            $user = $this->userRepository->findById($userId);
            $targetDate = CarbonImmutable::parse($workDate);
            $this->dailyWorkSummaryBatchService->aggregateByUser($company, $user, $targetDate);
        });

        // aggregateByUser()でレコードが削除+再作成される可能性があるため、IDではなくユーザー+日付で取得
        $result = $this->dailyWorkSummaryRepository->findByUserIdAndDate($companyId, $userId, $workDate);
        if ($result === null) {
            throw new NotFoundException("DailyWorkSummary not found: user_id={$userId}, work_date={$workDate}");
        }

        return $result;
    }

    /**
     * 勤務実績をCSV形式で生成
     *
     * フォーマットは全従業員CSV（generateCsvAll）と統一する。
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $periodStart  開始日（Y-m-d形式）
     * @param  string  $periodEnd  終了日（Y-m-d形式）
     * @param  User  $user  ユーザー情報
     * @return string CSV文字列
     */
    public function generateCsv(int $companyId, int $userId, string $periodStart, string $periodEnd, User $user): string
    {
        return $this->generateCsvAll(
            $companyId,
            $periodStart,
            $periodEnd,
            new \Illuminate\Database\Eloquent\Collection([$user])
        );
    }

    /**
     * 勤務区分を判定する
     *
     * 帳票（Excel）と同じ基準で判定する。シフトが割り当てられているのに
     * 出退勤がなく休暇の申請もない日は欠勤として扱う。
     *
     * @param  mixed  $summary  勤務実績
     * @param  CarbonImmutable  $date  対象日
     */
    private function resolveWorkType($summary, CarbonImmutable $date): string
    {
        if ($date->isSaturday() || $date->isSunday()) {
            return $summary?->work_start !== null ? '休出' : '休日';
        }

        if ($summary?->leave_type !== null) {
            return $summary->leave_type->label();
        }

        if ($summary?->work_start !== null) {
            return '出勤';
        }

        if ($summary?->scheduled_start_time !== null) {
            return '欠勤';
        }

        return '';
    }

    /**
     * 全従業員の勤務実績を1つのCSVで生成（集計なし）
     *
     * @param  int  $companyId  会社ID
     * @param  string  $periodStart  開始日（Y-m-d形式）
     * @param  string  $periodEnd  終了日（Y-m-d形式）
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users  対象ユーザー
     * @return string CSV文字列
     */
    public function generateCsvAll(int $companyId, string $periodStart, string $periodEnd, \Illuminate\Database\Eloquent\Collection $users): string
    {
        $startDate = CarbonImmutable::parse($periodStart);
        $endDate = CarbonImmutable::parse($periodEnd);
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        $lines = [];
        $lines[] = ['氏名', '日付', '曜日', '勤務区分', '出勤時刻', '退勤時刻', '勤務時間', '休憩', '実働時間', '時間外', '休日', '深夜', '遅刻', '早退', '備考'];

        foreach ($users as $user) {
            $rawTimes = $this->rawStampTimeService->mapByDate(
                $companyId,
                $user->id,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            );

            $summaries = $this->getByUserIdAndDateRange(
                $companyId,
                $user->id,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );
            $summaryMap = $summaries->keyBy(fn ($s) => $s->work_date->format('Y-m-d'));

            $currentDate = $startDate;
            while ($currentDate->lte($endDate)) {
                $dateKey = $currentDate->format('Y-m-d');
                $summary = $summaryMap->get($dateKey);
                $dayOfWeek = $weekdays[$currentDate->dayOfWeek];

                // 表示は実打刻を使う。集計テーブルには丸め後の時刻が入っている。
                $raw = $rawTimes[$dateKey] ?? null;

                $lines[] = [
                    $user->name,
                    $currentDate->format('Y/m/d'),
                    $dayOfWeek,
                    $this->resolveWorkType($summary, $currentDate),
                    $raw['work_start'] ?? $summary?->work_start?->format('H:i') ?? '',
                    $raw['work_end'] ?? $summary?->work_end?->format('H:i') ?? '',
                    $this->formatMinutesToHM($summary?->work_minutes ?? 0),
                    $this->formatMinutesToHM($summary?->break_minutes ?? 0),
                    $this->formatMinutesToHM($summary?->net_work_minutes ?? 0),
                    $this->formatMinutesToHM($summary?->overtime_minutes ?? 0),
                    $this->formatMinutesToHM($summary?->holiday_minutes ?? 0),
                    $this->formatMinutesToHM($summary?->night_minutes ?? 0),
                    $this->formatMinutesToHM($this->lateEarlyLeaveDisplay->lateMinutes($summary)),
                    $this->formatMinutesToHM($this->lateEarlyLeaveDisplay->earlyLeaveMinutes($summary)),
                    $summary?->note ?? '',
                ];

                $currentDate = $currentDate->addDay();
            }
        }

        $output = '';
        foreach ($lines as $line) {
            $output .= $this->escapeCsvLine($line)."\n";
        }

        return $output;
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
            return '0:00';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }

    /**
     * CSV行をエスケープ
     *
     * @param  array<string|int>  $fields  フィールド配列
     * @return string エスケープされたCSV行
     */
    private function escapeCsvLine(array $fields): string
    {
        $escapedFields = array_map(function ($field) {
            $value = (string) $field;
            // カンマ、ダブルクォート、改行が含まれる場合はクォートで囲む
            if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                return '"'.str_replace('"', '""', $value).'"';
            }

            return $value;
        }, $fields);

        return implode(',', $escapedFields);
    }

    /**
     * 打刻修正履歴を取得（日付でグループ化）
     *
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return array<string, array<int, array{before_time: string, after_time: string, corrected_at: string, record_type: array{value: int, label: string}, correction_source: string}>>
     */
    public function getCorrectionsByUserIdAndDateRange(int $userId, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();

        $corrections = $this->timeRecordCorrectionRepository->findByUserIdAndDateRange(
            $userId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        return $corrections
            ->groupBy(fn ($c) => $this->correctionWorkDate($c))
            ->map(fn ($group) => $group->map(fn ($c) => [
                'before_time' => $c->before_record_time->format('H:i'),
                'after_time' => $c->after_record_time->format('H:i'),
                'corrected_at' => $c->created_at->format('Y-m-d H:i'),
                'record_type' => [
                    'value' => $c->record_type->value,
                    'label' => $c->record_type->label(),
                ],
                'correction_source' => $c->correction_request_detail_id !== null ? self::CORRECTION_SOURCE_REQUEST : self::CORRECTION_SOURCE_ADMIN,
            ])->values())
            ->toArray();
    }

    /**
     * 打刻修正を勤務日に帰属させる
     *
     * 夜勤の退勤は翌日の時刻で記録されるため、打刻時刻の日付でまとめると
     * 6/2 の勤務に対する修正が 6/3 の修正として表示されてしまう。
     * 日付越えの退勤は勤務開始日に寄せる。
     */
    private function correctionWorkDate(TimeRecordCorrection $correction): string
    {
        $recordTime = CarbonImmutable::parse($correction->before_record_time);

        if ($correction->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY) {
            return $recordTime->subDay()->format('Y-m-d');
        }

        return $recordTime->format('Y-m-d');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Exceptions\NotFoundException;
use App\Models\DailyWorkSummary;
use App\Models\Request as LeaveRequest;
use App\Models\TimeRecordCorrection;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\RequestRepositoryInterface;
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
        private readonly LateEarlyLeaveDisplay $lateEarlyLeaveDisplay,
        private readonly RequestRepositoryInterface $requestRepository
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
                    // 実際に時刻が変わった場合のみ修正扱いにする。
                    // 編集モーダルは未変更のフィールドも含めて全項目を送信してくるため、
                    // 値が同じなら履歴を残さず、record_sourceも書き換えない
                    // （そうしないと無関係な項目の編集のたびに全打刻が「手動修正」扱いになる）。
                    // 比較は分単位で行う。画面には秒を持たせておらず(H:i)、フォームも
                    // 秒は常に00で送られてくるため、秒まで含めて比較すると実打刻の秒数
                    // （例: 09:00:47）と一致せず、未変更でも「変更あり」と誤判定してしまう。
                    $hasChanged = $workStartRecord->record_time->format('Y-m-d H:i') !== $startDateTime->format('Y-m-d H:i');

                    if ($hasChanged) {
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
                    }
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
                    // 実際に変わった場合のみ修正扱いにする（理由はWORK_START側と同様）
                    $hasChanged = $workEndRecord->record_type !== $recordType
                        || $workEndRecord->record_time->format('Y-m-d H:i') !== $endDateTime->format('Y-m-d H:i');

                    if ($hasChanged) {
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
                    }
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

                // 開始・終了それぞれ、実際に変わった側だけ修正扱いにする
                $startChanged = $existingStart->record_time->format('Y-m-d H:i') !== $breakStartDateTime->format('Y-m-d H:i');
                $endChanged = $existingEnd->record_time->format('Y-m-d H:i') !== $breakEndDateTime->format('Y-m-d H:i');

                if ($correctedBy !== null && $startChanged) {
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
                }

                if ($correctedBy !== null && $endChanged) {
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

                if ($startChanged) {
                    $this->timeRecordRepository->update($existingStart->id, [
                        'record_time' => $breakStartDateTime,
                        'rounded_time' => $roundedBreakStart,
                        'record_source' => RecordSourceEnum::MANUAL,
                        'note' => self::NOTE_ADMIN_CORRECTION,
                    ]);
                }
                if ($endChanged) {
                    $this->timeRecordRepository->update($existingEnd->id, [
                        'record_time' => $breakEndDateTime,
                        'rounded_time' => $roundedBreakEnd,
                        'record_source' => RecordSourceEnum::MANUAL,
                        'note' => self::NOTE_ADMIN_CORRECTION,
                    ]);
                }
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
        $lines[] = ['コード', '氏名', '日付', '勤務区分', 'シフト開始', 'シフト終了', 'シフト休憩入', 'シフト休憩出', '出勤時刻', '退勤時刻', '休憩入①', '休憩出①', '休憩入②', '休憩出②', '労働時間', '時間外', '休日', '深夜', '遅刻早退', '備考/申請'];

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

            // 帳票（Excel）と同じ基準で1日所定勤務時間を求める（申請の日数・時間換算に使う）
            $user->loadMissing('companies');
            $primaryCompany = $user->companies->firstWhere('pivot.is_primary', true) ?? $user->companies->first();
            $dailyWorkingMinutes = (int) (($primaryCompany?->daily_working_hours ?? 8) * 60);

            // 帳票（Excel）と同じく、承認済み申請を日付ごとにまとめておく
            $approvedRequests = $this->requestRepository->findByUserIdAndDateRange(
                $companyId,
                $user->id,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
                RequestStatusEnum::APPROVED->value
            );
            $requestMap = $approvedRequests->groupBy(fn (LeaveRequest $r) => $r->target_date->format('Y-m-d'));

            $currentDate = $startDate;
            while ($currentDate->lte($endDate)) {
                $dateKey = $currentDate->format('Y-m-d');
                $summary = $summaryMap->get($dateKey);
                $dateText = $currentDate->format('n/j').'('.$weekdays[$currentDate->dayOfWeek].')';

                // 表示は実打刻を使う。集計テーブルには丸め後の時刻が入っている。
                $raw = $rawTimes[$dateKey] ?? null;
                $breaks = $raw['breaks'] ?? [];

                $lines[] = [
                    $user->employee_code ?? '',
                    $user->name,
                    $dateText,
                    $this->resolveWorkType($summary, $currentDate),
                    $this->formatScheduledTime($summary?->scheduled_start_time),
                    $this->formatScheduledTime($summary?->scheduled_end_time),
                    // シフト休憩入・休憩出: 帳票（Excel）にも対応する所定データがなく常に空欄
                    '',
                    '',
                    $raw['work_start'] ?? $summary?->work_start?->format('H:i') ?? '',
                    $raw['work_end'] ?? $summary?->work_end?->format('H:i') ?? '',
                    $breaks[0]['start'] ?? '',
                    $breaks[0]['end'] ?? '',
                    $breaks[1]['start'] ?? '',
                    $breaks[1]['end'] ?? '',
                    $this->formatMinutesToHM($summary?->net_work_minutes ?? 0),
                    $this->formatMinutesToHM($summary?->overtime_minutes ?? 0),
                    $this->formatMinutesToHM($summary?->holiday_minutes ?? 0),
                    $this->formatMinutesToHM($summary?->night_minutes ?? 0),
                    $this->formatMinutesToHM(
                        $this->lateEarlyLeaveDisplay->lateMinutes($summary)
                        + $this->lateEarlyLeaveDisplay->earlyLeaveMinutes($summary)
                    ),
                    $this->buildNoteAndRequestColumn($summary, $requestMap->get($dateKey, collect()), $dailyWorkingMinutes),
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
     * シフトの所定時刻を「HH:MM」形式に変換する
     *
     * DBには「HH:MM:SS」形式で入っているため「HH:MM」に統一する
     *
     * @param  string|null  $time  時刻文字列（HH:MM:SS or HH:MM）
     */
    private function formatScheduledTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '';
    }

    /**
     * 備考/申請列を生成する（帳票=Excelと同じ判定基準）
     *
     * - 時間系申請（遅刻・早退・残業）: 小数の時間数（例: "1.75"）
     * - 日数系申請（有給・特別休暇・欠勤等）: leave_minutes ÷ 1日所定分 の日数
     * - 1日に複数申請がある場合は改行区切りで表示する
     *
     * @param  mixed  $summary  daily_work_summaries レコード
     * @param  \Illuminate\Support\Collection<int, LeaveRequest>  $dayRequests  当日の承認済み申請
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     */
    private function buildNoteAndRequestColumn($summary, \Illuminate\Support\Collection $dayRequests, int $dailyWorkingMinutes): string
    {
        $entries = [];

        foreach ($dayRequests as $request) {
            $typeName = $request->applicationType?->name ?? '';

            if ($typeName === '') {
                continue;
            }

            $valueStr = match ($request->type) {
                // 遅刻・早退: 申請自体に時間の指定はないため、打刻ベースの自動計算値を使う
                3 => $this->formatHourlyRequestValue($summary?->late_minutes ?? 0),
                4 => $this->formatHourlyRequestValue($summary?->early_leave_minutes ?? 0),
                // 残業申請: 承認しても daily_work_summaries の overtime_minutes は
                // 書き換えない設計（打刻ベースの自動計算値を維持するため）なので、
                // ここで summary の overtime_minutes を参照すると常に空欄/実態と
                // ずれた値になってしまう。申請自体の開始・終了時刻から算出する。
                7 => $this->formatHourlyRequestValue($this->requestRangeMinutes($request)),
                default => $this->calculateLeaveDays($request->type, $request, $dailyWorkingMinutes),
            };

            $entries[] = trim($typeName.' '.$valueStr);
        }

        return implode("\n", $entries);
    }

    /**
     * 時間系申請（遅刻・早退・残業）の表示値を生成する
     *
     * @param  int  $minutes  時間（分）
     * @return string 小数の時間数（例: 1時間45分 → "1.75"）、0以下の場合は空文字
     */
    private function formatHourlyRequestValue(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        $hours = round($minutes / 60, 2);
        $formatted = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

        return str_contains($formatted, '.') ? $formatted : $formatted.'.0';
    }

    /**
     * 申請自体の開始・終了時刻から時間数（分）を算出する
     *
     * @param  LeaveRequest  $request  申請レコード
     */
    private function requestRangeMinutes(LeaveRequest $request): int
    {
        if (! $request->start_time || ! $request->end_time) {
            return 0;
        }

        $start = CarbonImmutable::parse($request->start_time);
        $end = CarbonImmutable::parse($request->end_time);

        return max(0, (int) $start->diffInMinutes($end));
    }

    /**
     * 休暇申請の日数を計算する（帳票=Excelと同じ基準）
     *
     * @param  int  $typeId  申請種別ID
     * @param  LeaveRequest  $request  申請レコード
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     * @return string 日数文字列（例: "1.0", "0.5", "0.125"）
     */
    private function calculateLeaveDays(int $typeId, LeaveRequest $request, int $dailyWorkingMinutes): string
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

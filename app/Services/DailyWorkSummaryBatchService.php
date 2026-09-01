<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCodeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\Shift;
use App\Models\TimeRecord;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\HasLogService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 日次勤務実績バッチサービス
 *
 * 打刻レコードから日次勤務実績を自動集計するバッチ処理を提供
 */
class DailyWorkSummaryBatchService
{
    use HasLogService;

    /**
     * 深夜時間帯の開始時刻（22時）
     */
    private const NIGHT_START_HOUR = 22;

    /**
     * 深夜時間帯の終了時刻（5時）
     */
    private const NIGHT_END_HOUR = 5;

    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TimeRecordRepositoryInterface $timeRecordRepository,
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly WorkTimeCalculator $workTimeCalculator,
        private readonly AutoBreakFillService $autoBreakFillService
    ) {}

    /**
     * 指定日の全ユーザーの勤務実績を集計
     *
     * @param  CarbonImmutable  $targetDate  対象日
     * @return array{processed: int, created: int, updated: int, skipped: int, errors: int}
     */
    public function aggregateAllUsers(CarbonImmutable $targetDate): array
    {
        $result = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $companies = $this->companyRepository->getAll();

        foreach ($companies as $company) {
            $companyResult = $this->aggregateByCompany($company, $targetDate);
            $result['processed'] += $companyResult['processed'];
            $result['created'] += $companyResult['created'];
            $result['updated'] += $companyResult['updated'];
            $result['skipped'] += $companyResult['skipped'];
            $result['errors'] += $companyResult['errors'];
        }

        return $result;
    }

    /**
     * 会社ごとにユーザーの勤務実績を集計
     *
     * @param  Company  $company  会社
     * @param  CarbonImmutable  $targetDate  対象日
     * @return array{processed: int, created: int, updated: int, skipped: int, errors: int}
     */
    public function aggregateByCompany(Company $company, CarbonImmutable $targetDate): array
    {
        $result = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $dateString = $targetDate->format('Y-m-d');
        $users = $this->userRepository->findByCompanyIdForShift($company->id, $dateString);

        foreach ($users as $user) {
            try {
                $userResult = $this->aggregateByUser($company, $user, $targetDate);
                $result['processed']++;

                if ($userResult === 'created') {
                    $result['created']++;
                } elseif ($userResult === 'updated') {
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }
            } catch (\Throwable $e) {
                $result['errors']++;
                $this->logError(ErrorCodeEnum::ATTENDANCE_BATCH_AGGREGATE_FAILED, [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'target_date' => $dateString,
                ], $e);
            }
        }

        return $result;
    }

    /**
     * ユーザーの指定日の勤務実績を集計
     *
     * @param  Company  $company  会社
     * @param  User  $user  ユーザー
     * @param  CarbonImmutable  $targetDate  対象日
     * @return string 'created'|'updated'|'skipped'
     */
    public function aggregateByUser(Company $company, User $user, CarbonImmutable $targetDate): string
    {
        $dateString = $targetDate->format('Y-m-d');

        // 既存の勤務実績が手動修正または申請修正の場合はスキップ
        $existingSummary = $this->dailyWorkSummaryRepository->findByUserIdAndDate(
            $company->id,
            $user->id,
            $dateString
        );

        if ($existingSummary && $existingSummary->record_source !== RecordSourceEnum::AUTO) {
            return 'skipped';
        }

        // 打刻レコードを取得（当日分）
        $timeRecords = $this->timeRecordRepository->findByUserIdAndDate(
            $company->id,
            $user->id,
            $dateString
        );

        // 翌日の日付越え退勤レコードと、夜勤の翌日休憩レコードも取得して結合
        // 翌日休憩は WORK_END_NEXT_DAY が存在する夜勤の場合のみ対象（翌日が独立勤務日の場合は混入させない）
        $nextDateString = $targetDate->addDay()->format('Y-m-d');
        $nextDayRecords = $this->timeRecordRepository->findByUserIdAndDate(
            $company->id,
            $user->id,
            $nextDateString
        );
        $nextDayEndRecords = $nextDayRecords->filter(
            fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
        );
        if ($nextDayEndRecords->isNotEmpty()) {
            $timeRecords = $timeRecords->merge($nextDayEndRecords);

            // WORK_END_NEXT_DAY 以前の翌日休憩レコードのみマージ
            $crossDayEndTime = $nextDayEndRecords->first()->record_time;
            $nextDayBreakRecords = $nextDayRecords->filter(
                fn (TimeRecord $r) => ($r->record_type === TimeRecordTypeEnum::BREAK_START
                        || $r->record_type === TimeRecordTypeEnum::BREAK_END)
                    && $r->record_time->lte($crossDayEndTime)
            );
            if ($nextDayBreakRecords->isNotEmpty()) {
                $timeRecords = $timeRecords->merge($nextDayBreakRecords);
            }
        }

        // シフト情報を取得
        $shifts = $this->shiftRepository->findByUserIdAndDateRange(
            $company->id,
            $user->id,
            $dateString,
            $dateString
        );
        $shift = $shifts->first();

        // 勤務実績データを計算
        $summaryData = $this->calculateSummary($company, $user, $targetDate, $timeRecords, $shift);

        // 打刻がない場合は勤務実績を作成・更新しない
        if ($summaryData === null) {
            if ($existingSummary) {
                // 既存のレコードがあり、打刻がなくなった場合は削除
                $this->dailyWorkSummaryRepository->delete($existingSummary->id);
            }

            return 'skipped';
        }

        return DB::transaction(function () use ($existingSummary, $summaryData) {
            if ($existingSummary) {
                $this->dailyWorkSummaryRepository->update($existingSummary->id, $summaryData);

                return 'updated';
            }

            $this->dailyWorkSummaryRepository->create($summaryData);

            return 'created';
        });
    }

    /**
     * 勤務実績データを計算
     *
     * @param  Company  $company  会社
     * @param  User  $user  ユーザー
     * @param  CarbonImmutable  $targetDate  対象日
     * @param  Collection<int, TimeRecord>  $timeRecords  打刻レコード
     * @param  Shift|null  $shift  シフト情報
     * @return array<string, mixed>|null 勤務実績データ（打刻がない場合はnull）
     */
    private function calculateSummary(
        Company $company,
        User $user,
        CarbonImmutable $targetDate,
        Collection $timeRecords,
        ?Shift $shift
    ): ?array {
        // 勤務開始打刻を取得
        $workStart = $timeRecords
            ->filter(fn (TimeRecord $r) => $r->record_type->isWorkStart())
            ->first();

        // 勤務終了打刻を取得（通常終了または日付越え終了）
        $workEnd = $timeRecords
            ->filter(fn (TimeRecord $r) => $r->record_type->isWorkEnd())
            ->last();

        // 勤務開始打刻がない場合はスキップ
        if (! $workStart) {
            return null;
        }

        $dateString = $targetDate->format('Y-m-d');
        $workStartTime = $workStart->rounded_time ?? $workStart->record_time;
        $workEndTime = $workEnd?->rounded_time ?? $workEnd?->record_time;

        // シフト情報から予定時刻を取得
        $scheduledStartTime = null;
        $scheduledEndTime = null;
        if ($shift?->shiftPattern) {
            $scheduledStartTime = $shift->shiftPattern->start_time;
            $scheduledEndTime = $shift->shiftPattern->end_time;
        }

        // 日付越え判定
        $isCrossDay = $workEnd?->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY;

        // 丸め処理で退勤時刻が出勤時刻より前になった場合は出勤時刻に合わせる
        if ($workStartTime && $workEndTime && ! $isCrossDay) {
            $startCarbon = CarbonImmutable::parse($workStartTime);
            $endCarbon = CarbonImmutable::parse($workEndTime);
            if ($endCarbon->lt($startCarbon)) {
                $workEndTime = $workStartTime;
            }
        }

        // 勤務時間の計算（分単位）
        $workMinutes = 0;
        if ($workStartTime && $workEndTime) {
            $startCarbon = CarbonImmutable::parse($workStartTime);
            $endCarbon = CarbonImmutable::parse($workEndTime);

            // 日付越えの場合は終了時刻を翌日として計算
            if ($isCrossDay && $endCarbon->lt($startCarbon)) {
                $endCarbon = $endCarbon->addDay();
            }

            $workMinutes = max(0, (int) $startCarbon->diffInMinutes($endCarbon));
        }

        // 休憩時間の計算
        $breakMinutes = $this->calculateBreakMinutes(
            $timeRecords,
            $shift,
            $company,
            $targetDate,
            $workStartTime,
            $workEndTime,
            $isCrossDay,
        );

        // 実働時間
        $netWorkMinutes = max(0, $workMinutes - $breakMinutes);

        // 深夜時間の計算
        $nightMinutes = $this->calculateNightMinutes(
            $workStartTime,
            $workEndTime,
            $isCrossDay,
            $breakMinutes,
            $timeRecords,
            $shift,
            $company
        );

        // 遅刻時間の計算
        $lateMinutes = $this->calculateLateMinutes($workStartTime, $scheduledStartTime);

        // 早退時間の計算
        $earlyLeaveMinutes = $this->calculateEarlyLeaveMinutes(
            $workEndTime,
            $scheduledEndTime
        );

        // 時間外勤務の計算（時刻ベース + 休憩不足分）
        $overtimeMinutes = $this->calculateOvertimeMinutes(
            $scheduledStartTime,
            $scheduledEndTime,
            $netWorkMinutes,
            $shift?->shiftPattern,
        );

        return [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'work_date' => $dateString,
            'scheduled_start_time' => $scheduledStartTime,
            'scheduled_end_time' => $scheduledEndTime,
            'work_start' => $workStartTime,
            'work_end' => $workEndTime,
            'work_minutes' => $workMinutes,
            'break_minutes' => $breakMinutes,
            'net_work_minutes' => $netWorkMinutes,
            'night_minutes' => $nightMinutes,
            'holiday_minutes' => $shift === null ? $netWorkMinutes : 0,
            'overtime_minutes' => $overtimeMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'is_cross_day' => $isCrossDay,
            'record_source' => RecordSourceEnum::AUTO,
            'note' => null,
        ];
    }

    /**
     * 休憩時間を計算
     *
     * @param  Collection<int, TimeRecord>  $timeRecords  打刻レコード
     * @param  Shift|null  $shift  シフト情報
     * @param  Company  $company  会社
     * @return int 休憩時間（分）
     */
    private function calculateBreakMinutes(
        Collection $timeRecords,
        ?Shift $shift,
        Company $company,
        CarbonImmutable $workDate,
        mixed $workStartTime = null,
        mixed $workEndTime = null,
        bool $isCrossDay = false,
    ): int {
        // 休憩打刻から計算
        $breakStarts = $timeRecords
            ->filter(fn (TimeRecord $r) => $r->record_type->isBreakStart())
            ->values();

        $breakEnds = $timeRecords
            ->filter(fn (TimeRecord $r) => $r->record_type->isBreakEnd())
            ->values();

        $pattern = $shift?->shiftPattern;
        if (! $pattern) {
            $company->loadMissing('defaultShiftPattern');
            $pattern = $company->defaultShiftPattern;
        }

        $hasAnyBreakRecord = $breakStarts->isNotEmpty() || $breakEnds->isNotEmpty();

        // 休憩の打刻が1件もない日は、シフトの休憩時刻で補う（設定で有効な場合のみ）
        if ($this->autoBreakFillService->isApplicable($pattern, $hasAnyBreakRecord)) {
            return $this->autoBreakFillService->fillMinutes(
                $pattern,
                $workDate,
                $this->toWorkCarbon($workStartTime),
                $this->toWorkCarbon($workEndTime, $isCrossDay, $this->toWorkCarbon($workStartTime)),
            );
        }

        $breakMinutes = 0;

        foreach ($breakStarts as $index => $breakStart) {
            $breakEnd = $breakEnds->get($index);
            if ($breakEnd) {
                $startTime = $breakStart->rounded_time ?? $breakStart->record_time;
                $endTime = $breakEnd->rounded_time ?? $breakEnd->record_time;

                $startCarbon = CarbonImmutable::parse($startTime);
                $endCarbon = CarbonImmutable::parse($endTime);

                // 休憩終了が開始より前の場合は翌日とみなす（日跨ぎ勤務時）
                if ($endCarbon->lte($startCarbon)) {
                    $endCarbon = $endCarbon->addDay();
                }

                $breakMinutes += (int) $startCarbon->diffInMinutes($endCarbon);
            }
        }

        // 休憩打刻がなければ休憩0分（打刻ベースで計算）
        return $breakMinutes;
    }

    /**
     * 勤務時刻を比較可能な形にそろえる
     *
     * 日付越えの退勤は翌日として扱わないと休憩の重なりを判定できない。
     */
    private function toWorkCarbon(mixed $time, bool $isCrossDay = false, ?CarbonImmutable $start = null): ?CarbonImmutable
    {
        if ($time === null) {
            return null;
        }

        $carbon = CarbonImmutable::parse($time);

        if ($isCrossDay && $start !== null && $carbon->lt($start)) {
            $carbon = $carbon->addDay();
        }

        return $carbon;
    }

    /**
     * 深夜時間を計算（22:00〜05:00）
     *
     * 休憩時間は実際の休憩時間帯と深夜時間帯の重複分のみ減算する。
     * 休憩打刻がない場合（シフトパターンからの固定休憩）は、
     * シフトパターンの休憩時間帯（break_start/break_end）を使用する。
     *
     * @param  \DateTimeInterface|null  $workStart  勤務開始時刻
     * @param  \DateTimeInterface|null  $workEnd  勤務終了時刻
     * @param  bool  $isCrossDay  日付越えフラグ
     * @param  int  $breakMinutes  休憩時間（分）
     * @param  Collection<int, TimeRecord>  $timeRecords  打刻レコード
     * @param  Shift|null  $shift  シフト情報
     * @param  Company  $company  会社
     * @return int 深夜時間（分）
     */
    private function calculateNightMinutes(
        ?\DateTimeInterface $workStart,
        ?\DateTimeInterface $workEnd,
        bool $isCrossDay,
        int $breakMinutes,
        Collection $timeRecords,
        ?Shift $shift,
        Company $company
    ): int {
        if (! $workStart || ! $workEnd) {
            return 0;
        }

        $startCarbon = CarbonImmutable::parse($workStart);
        $endCarbon = CarbonImmutable::parse($workEnd);

        if ($isCrossDay && $endCarbon->lt($startCarbon)) {
            $endCarbon = $endCarbon->addDay();
        }

        $nightMinutes = $this->calculateNightMinutesInRange($startCarbon, $endCarbon);

        // 実際の休憩時間帯と深夜時間帯の重複分を計算して減算
        $nightBreakMinutes = $this->calculateNightBreakMinutes(
            $timeRecords, $shift, $company, $startCarbon, $breakMinutes, $nightMinutes, $startCarbon, $endCarbon
        );
        $nightMinutes = max(0, $nightMinutes - $nightBreakMinutes);

        return $nightMinutes;
    }

    /**
     * 深夜時間帯に含まれる休憩時間を計算
     *
     * 休憩時間帯が特定できる場合（休憩打刻あり、またはbreak_start/break_endが設定されている
     * シフトパターン）は、実際の重複分を正確に計算する。
     * 休憩時間帯が特定できない場合（break_mode=1で分数のみ設定）は、
     * 按分計算にフォールバックする。
     *
     * @param  Collection<int, TimeRecord>  $timeRecords  打刻レコード
     * @param  Shift|null  $shift  シフト情報
     * @param  Company  $company  会社
     * @param  CarbonImmutable  $workDate  勤務日のCarbon（日付基準用）
     * @param  int  $breakMinutes  休憩時間（分）
     * @param  int  $nightMinutes  深夜時間（分、休憩減算前）
     * @param  CarbonImmutable  $workStart  勤務開始時刻
     * @param  CarbonImmutable  $workEnd  勤務終了時刻
     * @return int 深夜時間帯に含まれる休憩時間（分）
     */
    private function calculateNightBreakMinutes(
        Collection $timeRecords,
        ?Shift $shift,
        Company $company,
        CarbonImmutable $workDate,
        int $breakMinutes,
        int $nightMinutes,
        CarbonImmutable $workStart,
        CarbonImmutable $workEnd
    ): int {
        // 休憩時間帯のペアを収集
        $breakPeriods = $this->collectBreakPeriods($timeRecords, $shift, $company, $workDate);

        // 休憩時間帯が特定できない場合（break_mode=1等）は按分計算にフォールバック
        if (empty($breakPeriods)) {
            $totalWorkMinutes = (int) $workStart->diffInMinutes($workEnd);
            if ($totalWorkMinutes > 0 && $breakMinutes > 0) {
                return (int) ($breakMinutes * ($nightMinutes / $totalWorkMinutes));
            }

            return 0;
        }

        $nightBreakMinutes = 0;
        foreach ($breakPeriods as $period) {
            $breakStart = $period['start'];
            $breakEnd = $period['end'];

            // 休憩時間帯を実際の勤務時間帯にクリップする（早退等で休憩が勤務外の場合を考慮）
            $breakStart = $breakStart->lt($workStart) ? $workStart : $breakStart;
            $breakEnd = $breakEnd->gt($workEnd) ? $workEnd : $breakEnd;

            if ($breakStart->gte($breakEnd)) {
                continue;
            }

            $nightBreakMinutes += $this->calculateNightMinutesInRange($breakStart, $breakEnd);
        }

        return $nightBreakMinutes;
    }

    /**
     * 指定区間内の深夜時間帯（22:00〜翌5:00）の分数を区間計算で算出
     *
     * 分単位ループを使わず、時間帯の重なりを直接計算する。
     */
    private function calculateNightMinutesInRange(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($start->gte($end)) {
            return 0;
        }

        $totalMinutes = 0;
        $current = $start->copy()->startOfDay();

        // 勤務区間をカバーする日数分の深夜帯を生成して重なりを計算
        // 最大3日分（前日22時〜、当日0時〜5時/22時〜、翌日0時〜5時）
        for ($day = 0; $day <= (int) $start->diffInDays($end) + 1; $day++) {
            $dayBase = $current->addDays($day);

            // 深夜帯1: 当日 00:00〜05:00
            $nightStart1 = $dayBase;
            $nightEnd1 = $dayBase->setTime(self::NIGHT_END_HOUR, 0);
            $totalMinutes += $this->overlapMinutes($start, $end, $nightStart1, $nightEnd1);

            // 深夜帯2: 当日 22:00〜翌日00:00
            $nightStart2 = $dayBase->setTime(self::NIGHT_START_HOUR, 0);
            $nightEnd2 = $dayBase->addDay()->startOfDay();
            $totalMinutes += $this->overlapMinutes($start, $end, $nightStart2, $nightEnd2);
        }

        return $totalMinutes;
    }

    /**
     * 2つの区間の重なり分数を計算
     */
    private function overlapMinutes(
        CarbonImmutable $start1,
        CarbonImmutable $end1,
        CarbonImmutable $start2,
        CarbonImmutable $end2
    ): int {
        $overlapStart = $start1->gt($start2) ? $start1 : $start2;
        $overlapEnd = $end1->lt($end2) ? $end1 : $end2;

        if ($overlapStart->gte($overlapEnd)) {
            return 0;
        }

        return (int) $overlapStart->diffInMinutes($overlapEnd);
    }

    /**
     * 休憩時間帯のペアを収集
     *
     * 休憩打刻がある場合はそれを使用し、ない場合はシフトパターンまたは
     * 会社デフォルトシフトパターンの休憩時間帯を使用する。
     *
     * @param  Collection<int, TimeRecord>  $timeRecords  打刻レコード
     * @param  Shift|null  $shift  シフト情報
     * @param  Company  $company  会社
     * @param  CarbonImmutable  $workDate  勤務日のCarbon（日付基準用）
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function collectBreakPeriods(
        Collection $timeRecords,
        ?Shift $shift,
        Company $company,
        CarbonImmutable $workDate
    ): array {
        $breakPeriods = [];

        // 休憩打刻から収集
        $breakStarts = $timeRecords
            ->filter(fn (TimeRecord $r) => $r->record_type->isBreakStart())
            ->values();
        $breakEnds = $timeRecords
            ->filter(fn (TimeRecord $r) => $r->record_type->isBreakEnd())
            ->values();

        foreach ($breakStarts as $index => $breakStart) {
            $breakEnd = $breakEnds->get($index);
            if ($breakEnd) {
                $breakPeriods[] = [
                    'start' => CarbonImmutable::parse($breakStart->rounded_time ?? $breakStart->record_time),
                    'end' => CarbonImmutable::parse($breakEnd->rounded_time ?? $breakEnd->record_time),
                ];
            }
        }

        // 休憩打刻がない場合はシフトパターンの休憩時間帯を使用
        // ただし、勤務打刻が手動修正・申請修正されている場合はフォールバックしない
        if (empty($breakPeriods) && ! $this->hasManuallyModifiedWorkRecord($timeRecords)) {
            $pattern = $shift?->shiftPattern;
            if (! $pattern) {
                $company->loadMissing('defaultShiftPattern');
                $pattern = $company->defaultShiftPattern;
            }

            if ($pattern && $pattern->break_start && $pattern->break_end) {
                $breakStartCarbon = $workDate->copy()->setTimeFromTimeString($pattern->break_start);
                $breakEndCarbon = $workDate->copy()->setTimeFromTimeString($pattern->break_end);

                // 休憩時間帯が日付を跨ぐ場合（例: 23:30-00:30）は終了時刻を翌日にする
                if ($breakEndCarbon->lte($breakStartCarbon)) {
                    $breakEndCarbon = $breakEndCarbon->addDay();
                }

                // 夜勤で休憩が翌日（例: 01:00-02:00）の場合、勤務開始前に配置されるため翌日に補正
                if ($breakEndCarbon->lte($workDate)) {
                    $breakStartCarbon = $breakStartCarbon->addDay();
                    $breakEndCarbon = $breakEndCarbon->addDay();
                }

                $breakPeriods[] = [
                    'start' => $breakStartCarbon,
                    'end' => $breakEndCarbon,
                ];
            }
        }

        return $breakPeriods;
    }

    /**
     * 勤務打刻が手動修正または申請修正されているか判定
     *
     * WORK_START/WORK_END のいずれかが AUTO 以外（MANUAL or REQUEST）の場合、
     * 人間が介入済みと判断する。修正後に休憩打刻がないのは「休憩なし」を意味するため、
     * シフトパターンへのフォールバックを行わない判断に使用する。
     *
     * @param  Collection<int, TimeRecord>  $timeRecords  打刻レコード
     */
    private function hasManuallyModifiedWorkRecord(Collection $timeRecords): bool
    {
        return $timeRecords
            ->filter(fn (TimeRecord $r) => $r->record_type->isWorkStart() || $r->record_type->isWorkEnd())
            ->contains(fn (TimeRecord $r) => ! $r->record_source->isAuto());
    }

    /**
     * 遅刻時間を計算
     *
     * @param  \DateTimeInterface|null  $workStart  実際の勤務開始時刻
     * @param  string|null  $scheduledStartTime  予定開始時刻（HH:MM形式）
     * @return int 遅刻時間（分）
     */
    private function calculateLateMinutes(
        ?\DateTimeInterface $workStart,
        ?string $scheduledStartTime
    ): int {
        if (! $workStart || ! $scheduledStartTime) {
            return 0;
        }

        $workStartCarbon = CarbonImmutable::parse($workStart);
        $scheduledStartCarbon = $workStartCarbon->copy()
            ->setTimeFromTimeString($scheduledStartTime);

        if ($workStartCarbon->gt($scheduledStartCarbon)) {
            return (int) $scheduledStartCarbon->diffInMinutes($workStartCarbon);
        }

        return 0;
    }

    /**
     * 早退時間を計算
     *
     * 日跨ぎ勤務の場合 $workEnd は WORK_END_NEXT_DAY の翌日日付を持っているため、
     * copy()->setTimeFromTimeString() で生成される $scheduledEndCarbon も同じ翌日基準になる。
     * したがって addDay 補正は不要。
     *
     * @param  \DateTimeInterface|null  $workEnd  実際の勤務終了時刻
     * @param  string|null  $scheduledEndTime  予定終了時刻（HH:MM形式）
     * @return int 早退時間（分）
     */
    private function calculateEarlyLeaveMinutes(
        ?\DateTimeInterface $workEnd,
        ?string $scheduledEndTime
    ): int {
        if (! $workEnd || ! $scheduledEndTime) {
            return 0;
        }

        $workEndCarbon = CarbonImmutable::parse($workEnd);
        $scheduledEndCarbon = $workEndCarbon->copy()
            ->setTimeFromTimeString($scheduledEndTime);

        if ($workEndCarbon->lt($scheduledEndCarbon)) {
            return (int) $workEndCarbon->diffInMinutes($scheduledEndCarbon);
        }

        return 0;
    }

    /**
     * 時間外勤務時間を計算（時刻ベース + 休憩不足分）
     *
     * 3つの要素を加算:
     * 1. シフト終業後の残業: max(0, 退勤時刻 − シフト終業時刻)
     * 2. シフト始業前の早出: max(0, シフト始業時刻 − 出勤時刻)
     * 3. 休憩不足分: max(0, シフト休憩分 − 実休憩分)
     *
     * 遅刻・早退とは独立して計算されるため、遅刻と残業が相殺されない。
     *
     * @param  \DateTimeInterface|null  $workStart  実際の勤務開始時刻（丸め後）
     * @param  \DateTimeInterface|null  $workEnd  実際の勤務終了時刻（丸め後）
     * @param  string|null  $scheduledStartTime  予定開始時刻（HH:MM形式）
     * @param  string|null  $scheduledEndTime  シフト終業（HH:MM形式）
     * @param  int  $netWorkMinutes  実労働時間（分）
     * @param  \App\Models\ShiftPattern|null  $shiftPattern  シフトパターン
     * @return int 時間外（分）
     */
    private function calculateOvertimeMinutes(
        ?string $scheduledStartTime,
        ?string $scheduledEndTime,
        int $netWorkMinutes,
        ?\App\Models\ShiftPattern $shiftPattern
    ): int {
        if ($shiftPattern === null) {
            return 0;
        }

        $scheduledWorkMinutes = $this->workTimeCalculator->scheduledWorkMinutes(
            $scheduledStartTime,
            $scheduledEndTime,
            $this->getScheduledBreakMinutes($shiftPattern),
        );

        return $this->workTimeCalculator->overtimeMinutes($netWorkMinutes, $scheduledWorkMinutes);
    }

    /**
     * シフトパターンから所定休憩時間（分）を取得
     *
     * break_minutes を優先し、未設定の場合は break_start/break_end から算出する。
     */
    private function getScheduledBreakMinutes(\App\Models\ShiftPattern $shiftPattern): int
    {
        $breakMinutes = (int) ($shiftPattern->break_minutes ?? 0);

        if ($breakMinutes <= 0 && $shiftPattern->break_start && $shiftPattern->break_end) {
            $breakStartMin = $this->timeStringToMinutes((string) $shiftPattern->break_start);
            $breakEndMin = $this->timeStringToMinutes((string) $shiftPattern->break_end);
            if ($breakEndMin <= $breakStartMin) {
                $breakEndMin += 24 * 60;
            }
            $breakMinutes = $breakEndMin - $breakStartMin;
        }

        return $breakMinutes;
    }

    /**
     * 時刻文字列（HH:MM or HH:MM:SS）を0時からの分数に変換
     */
    private function timeStringToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return (int) $parts[0] * 60 + (int) ($parts[1] ?? 0);
    }
}

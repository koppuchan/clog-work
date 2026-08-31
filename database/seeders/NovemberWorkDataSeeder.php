<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\RequestTypeEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Request;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\User;
use App\Models\UserShiftPattern;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * 11月の勤務実績データSeeder
 *
 * - 11月の全営業日に対して勤務実績を作成
 * - 承認済みの申請（有給、遅刻、早退など）を作成
 * - 申請が勤務実績に反映された状態のデータを生成
 */
class NovemberWorkDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            $this->command->error('会社が見つかりません。先にAdminUserSeederを実行してください。');

            return;
        }

        // 会社に所属するユーザーを取得
        $users = User::whereHas('companies', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })->get();

        if ($users->isEmpty()) {
            $this->command->error('ユーザーが見つかりません。先にUserSeederを実行してください。');

            return;
        }

        // デフォルトのシフトパターンを取得
        $defaultShift = ShiftPattern::where('company_id', $company->id)->first();

        if (! $defaultShift) {
            $this->command->error('シフトパターンが見つかりません。先にShiftPatternSeederを実行してください。');

            return;
        }

        // 管理者/責任者を承認者として取得
        $approvers = $users->filter(function ($user) {
            return $user->isAdmin() || $user->isManager();
        });

        $approver = $approvers->first();

        $this->command->info('11月の勤務実績データを作成中...');

        // 2025年11月の勤務実績を作成
        $startDate = CarbonImmutable::create(2025, 11, 1);
        $endDate = CarbonImmutable::create(2025, 11, 30);

        $workRecordCount = 0;
        $requestCount = 0;

        // 各ユーザーに対して承認済み申請を事前に作成
        $approvedRequests = $this->createApprovedRequests($company->id, $users, $approver, $startDate, $endDate);
        $requestCount = count($approvedRequests);

        $targetDate = $startDate;
        while ($targetDate->lte($endDate)) {
            // 土日はスキップ
            if ($targetDate->isWeekend()) {
                $targetDate = $targetDate->addDay();

                continue;
            }

            // 曜日ID（1=月曜〜7=日曜）
            $weekdayId = $targetDate->dayOfWeekIso;

            foreach ($users as $user) {
                // このユーザーのこの日の承認済み申請を確認
                $userRequests = collect($approvedRequests)->filter(function ($req) use ($user, $targetDate) {
                    return $req['user_id'] === $user->id
                        && $req['target_date'] === $targetDate->format('Y-m-d');
                });

                // 有給休暇の申請があればスキップ（勤務なし）
                $hasLeaveRequest = $userRequests->contains(function ($req) {
                    return $req['type'] === RequestTypeEnum::LEAVE;
                });

                if ($hasLeaveRequest) {
                    // 有給休暇の日は勤務実績を作成しない
                    continue;
                }

                // ユーザーのその曜日のシフトパターンを取得
                $userShiftPattern = UserShiftPattern::where('user_id', $user->id)
                    ->where('weekday_id', $weekdayId)
                    ->with('shiftPattern')
                    ->first();

                $shiftPattern = $userShiftPattern?->shiftPattern ?? $defaultShift;

                // シフトパターンがない場合はスキップ
                if (! $shiftPattern || ! $shiftPattern->start_time) {
                    continue;
                }

                // 遅刻・早退申請を確認
                $lateRequest = $userRequests->first(function ($req) {
                    return $req['type'] === RequestTypeEnum::LATE;
                });

                $earlyLeaveRequest = $userRequests->first(function ($req) {
                    return $req['type'] === RequestTypeEnum::EARLY_LEAVE;
                });

                $this->createDailyTimeRecords(
                    $company->id,
                    $user->id,
                    $targetDate,
                    $shiftPattern,
                    $lateRequest,
                    $earlyLeaveRequest
                );
                $workRecordCount++;
            }

            $targetDate = $targetDate->addDay();
        }

        $this->command->info("11月の勤務実績データを作成しました（{$workRecordCount}日分）");
        $this->command->info("承認済み申請を{$requestCount}件作成しました");
    }

    /**
     * 承認済み申請を作成
     *
     * @return array<int, array{user_id: int, target_date: string, type: RequestTypeEnum}>
     */
    private function createApprovedRequests(
        int $companyId,
        $users,
        ?User $approver,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate
    ): array {
        $requests = [];
        $decidedAt = CarbonImmutable::now()->subDays(7);

        foreach ($users as $user) {
            // 各ユーザーに1〜3件の承認済み申請を作成
            $requestDates = $this->getRandomWorkdaysInRange($startDate, $endDate, fake()->numberBetween(1, 3));

            foreach ($requestDates as $index => $targetDate) {
                // 申請タイプをランダムに決定（有給:50%, 遅刻:25%, 早退:25%）
                $typeRand = fake()->numberBetween(1, 100);
                if ($typeRand <= 50) {
                    $type = RequestTypeEnum::LEAVE;
                    $reason = fake()->randomElement([
                        '私用のため',
                        '家族の用事',
                        '通院のため',
                        'リフレッシュ休暇',
                    ]);
                    $startTime = null;
                    $endTime = null;
                } elseif ($typeRand <= 75) {
                    $type = RequestTypeEnum::LATE;
                    $reason = fake()->randomElement([
                        '電車遅延のため',
                        '通院のため',
                        '役所手続きのため',
                    ]);
                    $startTime = fake()->randomElement(['09:30', '10:00', '10:30', '11:00']);
                    $endTime = null;
                } else {
                    $type = RequestTypeEnum::EARLY_LEAVE;
                    $reason = fake()->randomElement([
                        '体調不良のため',
                        '通院のため',
                        '家庭の事情',
                    ]);
                    $startTime = null;
                    $endTime = fake()->randomElement(['16:00', '16:30', '17:00']);
                }

                Request::create([
                    'company_id' => $companyId,
                    'requested_by' => $user->id,
                    'type' => $type,
                    'target_date' => $targetDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'reason' => $reason,
                    'status' => RequestStatusEnum::APPROVED,
                    'approver_id' => $approver?->id,
                    'decided_at' => $decidedAt,
                    'decision_note' => null,
                ]);

                $requests[] = [
                    'user_id' => $user->id,
                    'target_date' => $targetDate->format('Y-m-d'),
                    'type' => $type,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ];
            }
        }

        return $requests;
    }

    /**
     * 指定期間内のランダムな平日を取得
     *
     * @return array<CarbonImmutable>
     */
    private function getRandomWorkdaysInRange(CarbonImmutable $start, CarbonImmutable $end, int $count): array
    {
        $workdays = [];
        $current = $start;

        while ($current->lte($end)) {
            if (! $current->isWeekend()) {
                $workdays[] = $current;
            }
            $current = $current->addDay();
        }

        // ランダムに選択
        shuffle($workdays);

        return array_slice($workdays, 0, min($count, count($workdays)));
    }

    /**
     * 1日分の勤務実績を作成（申請を反映）
     */
    private function createDailyTimeRecords(
        int $companyId,
        int $userId,
        CarbonImmutable $date,
        ShiftPattern $shiftPattern,
        ?array $lateRequest,
        ?array $earlyLeaveRequest
    ): void {
        // シフトパターンから勤務開始・終了時刻を取得
        [$shiftStartHour, $shiftStartMinute] = $this->parseTime($shiftPattern->start_time);
        [$shiftEndHour, $shiftEndMinute] = $this->parseTime($shiftPattern->end_time);

        // 遅刻申請がある場合は申請時刻を使用
        if ($lateRequest && $lateRequest['start_time']) {
            [$lateHour, $lateMinute] = $this->parseTime($lateRequest['start_time']);
            $workStart = $date->setTime($lateHour, $lateMinute);
            $lateMinutes = (int) $date->setTime($shiftStartHour, $shiftStartMinute)->diffInMinutes($workStart);
        } else {
            // 通常勤務開始（シフト開始時刻の前後10分以内でランダム）
            $startVariation = fake()->numberBetween(-10, 10);
            $workStart = $date->setTime($shiftStartHour, $shiftStartMinute)->addMinutes($startVariation);
            $lateMinutes = max(0, $startVariation);
        }

        // 勤務開始打刻
        $this->createTimeRecord(
            $companyId,
            $userId,
            TimeRecordTypeEnum::WORK_START,
            $workStart,
            $this->roundTime($workStart)
        );

        // 休憩時間の処理
        $breakMinutes = 0;
        if ($shiftPattern->break_start && $shiftPattern->break_end) {
            [$breakStartHour, $breakStartMinute] = $this->parseTime($shiftPattern->break_start);
            [$breakEndHour, $breakEndMinute] = $this->parseTime($shiftPattern->break_end);

            $breakStart = $date->setTime($breakStartHour, $breakStartMinute);
            $breakEnd = $date->setTime($breakEndHour, $breakEndMinute);

            $this->createTimeRecord(
                $companyId,
                $userId,
                TimeRecordTypeEnum::BREAK_START,
                $breakStart,
                $this->roundTime($breakStart)
            );

            $this->createTimeRecord(
                $companyId,
                $userId,
                TimeRecordTypeEnum::BREAK_END,
                $breakEnd,
                $this->roundTime($breakEnd)
            );

            $breakMinutes = (int) $breakStart->diffInMinutes($breakEnd);
        } elseif ($shiftPattern->break_minutes > 0) {
            $breakStart = $date->setTime(12, 0);
            $breakEnd = $breakStart->addMinutes($shiftPattern->break_minutes);

            $this->createTimeRecord(
                $companyId,
                $userId,
                TimeRecordTypeEnum::BREAK_START,
                $breakStart,
                $this->roundTime($breakStart)
            );

            $this->createTimeRecord(
                $companyId,
                $userId,
                TimeRecordTypeEnum::BREAK_END,
                $breakEnd,
                $this->roundTime($breakEnd)
            );

            $breakMinutes = $shiftPattern->break_minutes;
        }

        // 早退申請がある場合は申請時刻を使用
        $earlyLeaveMinutes = 0;
        if ($earlyLeaveRequest && $earlyLeaveRequest['end_time']) {
            [$earlyHour, $earlyMinute] = $this->parseTime($earlyLeaveRequest['end_time']);
            $workEnd = $date->setTime($earlyHour, $earlyMinute);
            $scheduledEnd = $date->setTime($shiftEndHour, $shiftEndMinute);
            $earlyLeaveMinutes = (int) $workEnd->diffInMinutes($scheduledEnd);
        } else {
            // 通常勤務終了（シフト終了時刻〜+30分でランダム、残業含む）
            $endVariation = fake()->numberBetween(0, 30);
            $workEnd = $date->setTime($shiftEndHour, $shiftEndMinute)->addMinutes($endVariation);
        }

        // 勤務終了打刻
        $this->createTimeRecord(
            $companyId,
            $userId,
            TimeRecordTypeEnum::WORK_END,
            $workEnd,
            $this->roundTime($workEnd)
        );

        // 日次勤務実績を作成
        $this->createDailyWorkSummary(
            $companyId,
            $userId,
            $date,
            $workStart,
            $workEnd,
            $breakMinutes,
            $lateMinutes,
            $earlyLeaveMinutes,
            $shiftPattern
        );
    }

    /**
     * 時刻文字列をパース
     *
     * @return array{0: int, 1: int}
     */
    private function parseTime(string $time): array
    {
        $parts = explode(':', $time);

        return [(int) $parts[0], (int) ($parts[1] ?? 0)];
    }

    /**
     * 打刻レコードを作成
     */
    private function createTimeRecord(
        int $companyId,
        int $userId,
        TimeRecordTypeEnum $recordType,
        CarbonImmutable $recordTime,
        CarbonImmutable $roundedTime
    ): void {
        TimeRecord::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'record_type' => $recordType,
            'record_time' => $recordTime,
            'rounded_time' => $roundedTime,
            'record_source' => RecordSourceEnum::AUTO,
            'note' => null,
        ]);
    }

    /**
     * 日次勤務実績を作成（申請を反映）
     */
    private function createDailyWorkSummary(
        int $companyId,
        int $userId,
        CarbonImmutable $date,
        CarbonImmutable $workStart,
        CarbonImmutable $workEnd,
        int $breakMinutes,
        int $lateMinutes,
        int $earlyLeaveMinutes,
        ShiftPattern $shiftPattern
    ): void {
        // 総勤務時間（分）
        $workMinutes = (int) $workStart->diffInMinutes($workEnd);

        // 実働時間（休憩を除く）
        $netWorkMinutes = max(0, $workMinutes - $breakMinutes);

        // 残業時間の計算（所定労働時間を超えた分）
        $scheduledWorkMinutes = $shiftPattern->work_minutes ?? 480;
        $overtimeMinutes = max(0, $netWorkMinutes - $scheduledWorkMinutes);

        // record_source: 申請が反映されている場合は3（申請修正）
        $recordSource = ($lateMinutes > 0 || $earlyLeaveMinutes > 0)
            ? RecordSourceEnum::REQUEST
            : RecordSourceEnum::AUTO;

        DailyWorkSummary::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'work_date' => $date->format('Y-m-d'),
            'scheduled_start_time' => $shiftPattern->start_time,
            'scheduled_end_time' => $shiftPattern->end_time,
            'work_start' => $workStart,
            'work_end' => $workEnd,
            'work_minutes' => $workMinutes,
            'break_minutes' => $breakMinutes,
            'net_work_minutes' => $netWorkMinutes,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => $overtimeMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'is_cross_day' => false,
            'record_source' => $recordSource,
            'note' => null,
        ]);
    }

    /**
     * 時刻を15分単位で丸める
     */
    private function roundTime(CarbonImmutable $time): CarbonImmutable
    {
        $minute = $time->minute;
        $roundedMinute = (int) (ceil($minute / 15) * 15);

        if ($roundedMinute >= 60) {
            return $time->addHour()->setMinute(0)->setSecond(0);
        }

        return $time->setMinute($roundedMinute)->setSecond(0);
    }
}

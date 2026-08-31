<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\User;
use App\Models\UserShiftPattern;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TimeRecordSeeder extends Seeder
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

        $this->command->info('勤務実績データを作成中...');

        $createdCount = 0;

        // 2025年10月1日〜11月30日の勤務実績を作成
        $startDate = CarbonImmutable::create(2025, 10, 1);
        $endDate = CarbonImmutable::create(2025, 11, 30);

        $targetDate = $startDate;
        while ($targetDate->lte($endDate)) {
            // 曜日ID（1=月曜〜7=日曜）
            $weekdayId = $targetDate->dayOfWeekIso;

            foreach ($users as $user) {
                // ユーザーのその曜日のシフトパターンを取得
                $userShiftPattern = UserShiftPattern::where('user_id', $user->id)
                    ->where('weekday_id', $weekdayId)
                    ->with('shiftPattern')
                    ->first();

                $shiftPattern = $userShiftPattern?->shiftPattern ?? $defaultShift;

                // シフトパターンがない場合（休日）はスキップ
                if (! $shiftPattern || ! $shiftPattern->start_time) {
                    continue;
                }

                // 土日はスキップ（休日勤務はしない設定）
                if ($targetDate->isWeekend()) {
                    continue;
                }

                // 5%の確率で欠勤
                if (fake()->numberBetween(1, 100) <= 5) {
                    continue;
                }

                $this->createDailyTimeRecords($company->id, $user->id, $targetDate, $shiftPattern);
                $createdCount++;
            }

            $targetDate = $targetDate->addDay();
        }

        $this->command->info("勤務実績データを作成しました（{$createdCount}日分）");
    }

    /**
     * 1日分の勤務実績を作成
     */
    private function createDailyTimeRecords(
        int $companyId,
        int $userId,
        CarbonImmutable $date,
        ShiftPattern $shiftPattern
    ): void {
        // シフトパターンから勤務開始時刻を取得
        [$shiftStartHour, $shiftStartMinute] = $this->parseTime($shiftPattern->start_time);
        [$shiftEndHour, $shiftEndMinute] = $this->parseTime($shiftPattern->end_time);

        // 勤務開始時刻（シフト開始時刻の前後15分以内でランダム）
        $startVariation = fake()->numberBetween(-15, 15);
        $workStart = $date->setTime($shiftStartHour, $shiftStartMinute)->addMinutes($startVariation);

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
            // 固定休憩時間がある場合
            [$breakStartHour, $breakStartMinute] = $this->parseTime($shiftPattern->break_start);
            [$breakEndHour, $breakEndMinute] = $this->parseTime($shiftPattern->break_end);

            $breakVariation = fake()->numberBetween(-5, 10);
            $breakStart = $date->setTime($breakStartHour, $breakStartMinute)->addMinutes($breakVariation);

            $this->createTimeRecord(
                $companyId,
                $userId,
                TimeRecordTypeEnum::BREAK_START,
                $breakStart,
                $this->roundTime($breakStart)
            );

            $breakEndVariation = fake()->numberBetween(-5, 15);
            $breakEnd = $date->setTime($breakEndHour, $breakEndMinute)->addMinutes($breakEndVariation);

            $this->createTimeRecord(
                $companyId,
                $userId,
                TimeRecordTypeEnum::BREAK_END,
                $breakEnd,
                $this->roundTime($breakEnd)
            );

            $breakMinutes = (int) $breakStart->diffInMinutes($breakEnd);
        } elseif ($shiftPattern->break_minutes > 0) {
            // 休憩時間が分数で指定されている場合（12:00頃に休憩開始）
            $breakStart = $date->setTime(12, fake()->randomElement([0, 15, 30]));

            $this->createTimeRecord(
                $companyId,
                $userId,
                TimeRecordTypeEnum::BREAK_START,
                $breakStart,
                $this->roundTime($breakStart)
            );

            $breakDuration = $shiftPattern->break_minutes + fake()->numberBetween(-5, 15);
            $breakEnd = $breakStart->addMinutes($breakDuration);

            $this->createTimeRecord(
                $companyId,
                $userId,
                TimeRecordTypeEnum::BREAK_END,
                $breakEnd,
                $this->roundTime($breakEnd)
            );

            $breakMinutes = $breakDuration;
        }

        // 勤務終了時刻（シフト終了時刻の前後30分以内でランダム、残業含む）
        $endVariation = fake()->numberBetween(-10, 60); // 残業は最大1時間
        $workEnd = $date->setTime($shiftEndHour, $shiftEndMinute)->addMinutes($endVariation);

        // 日付越えの場合（3%の確率）
        $isNextDay = fake()->numberBetween(1, 100) <= 3 && $shiftEndHour >= 20;
        if ($isNextDay) {
            $workEnd = $date->addDay()->setTime(fake()->numberBetween(0, 1), fake()->randomElement([0, 30]));
        }

        $this->createTimeRecord(
            $companyId,
            $userId,
            $isNextDay ? TimeRecordTypeEnum::WORK_END_NEXT_DAY : TimeRecordTypeEnum::WORK_END,
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
            $isNextDay,
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
        // 記録元（90%が自動打刻、10%が手動入力）
        $recordSource = fake()->numberBetween(1, 100) <= 90
            ? RecordSourceEnum::AUTO
            : RecordSourceEnum::MANUAL;

        // 手動入力の場合はメモを追加
        $note = $recordSource === RecordSourceEnum::MANUAL
            ? fake()->randomElement(['打刻忘れのため手動入力', 'システム不具合のため', '端末エラーのため'])
            : null;

        TimeRecord::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'record_type' => $recordType,
            'record_time' => $recordTime,
            'rounded_time' => $roundedTime,
            'record_source' => $recordSource,
            'note' => $note,
        ]);
    }

    /**
     * 日次勤務実績を作成
     */
    private function createDailyWorkSummary(
        int $companyId,
        int $userId,
        CarbonImmutable $date,
        CarbonImmutable $workStart,
        CarbonImmutable $workEnd,
        int $breakMinutes,
        bool $isCrossDay,
        ShiftPattern $shiftPattern
    ): void {
        // 総勤務時間（分）
        $workMinutes = (int) $workStart->diffInMinutes($workEnd);

        // 実働時間（休憩を除く）
        $netWorkMinutes = $workMinutes - $breakMinutes;

        // 深夜時間（22:00〜05:00）の計算
        $nightMinutes = $this->calculateNightMinutes($workStart, $workEnd, $breakMinutes);

        // 残業時間の計算（所定労働時間を超えた分）
        $scheduledWorkMinutes = $shiftPattern->work_minutes ?? 480; // デフォルト8時間
        $overtimeMinutes = max(0, $netWorkMinutes - $scheduledWorkMinutes);

        // 休日勤務の計算（土日の場合）
        $holidayMinutes = $date->isWeekend() ? $netWorkMinutes : 0;

        // 遅刻・早退の計算
        [$lateMinutes, $earlyLeaveMinutes] = $this->calculateLateAndEarlyLeave(
            $date,
            $workStart,
            $workEnd,
            $shiftPattern
        );

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
            'night_minutes' => $nightMinutes,
            'holiday_minutes' => $holidayMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'is_cross_day' => $isCrossDay,
            'record_source' => RecordSourceEnum::AUTO,
            'note' => null,
        ]);
    }

    /**
     * 深夜時間（22:00〜05:00）を計算
     */
    private function calculateNightMinutes(
        CarbonImmutable $workStart,
        CarbonImmutable $workEnd,
        int $breakMinutes
    ): int {
        $nightMinutes = 0;

        // 深夜時間帯: 22:00〜翌05:00
        $nightStartHour = 22;
        $nightEndHour = 5;

        $current = $workStart->copy();
        while ($current->lt($workEnd)) {
            $hour = $current->hour;

            // 22:00〜23:59 または 00:00〜04:59
            if ($hour >= $nightStartHour || $hour < $nightEndHour) {
                $nightMinutes++;
            }

            $current = $current->addMinute();
        }

        // 休憩時間の一部が深夜にかかる可能性があるが、簡略化のため考慮しない
        return $nightMinutes;
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

    /**
     * 遅刻・早退を計算
     *
     * @return array{0: int, 1: int} [遅刻分数, 早退分数]
     */
    private function calculateLateAndEarlyLeave(
        CarbonImmutable $date,
        CarbonImmutable $workStart,
        CarbonImmutable $workEnd,
        ShiftPattern $shiftPattern
    ): array {
        // 所定開始時刻
        [$scheduledStartHour, $scheduledStartMinute] = $this->parseTime($shiftPattern->start_time);
        $scheduledStart = $date->setTime($scheduledStartHour, $scheduledStartMinute);

        // 所定終了時刻
        [$scheduledEndHour, $scheduledEndMinute] = $this->parseTime($shiftPattern->end_time);
        $scheduledEnd = $date->setTime($scheduledEndHour, $scheduledEndMinute);

        // 遅刻計算：実際の出勤時刻が所定開始時刻より遅い場合
        $lateMinutes = 0;
        if ($workStart->gt($scheduledStart)) {
            $lateMinutes = (int) $scheduledStart->diffInMinutes($workStart);
        }

        // 早退計算：実際の退勤時刻が所定終了時刻より早い場合
        $earlyLeaveMinutes = 0;
        if ($workEnd->lt($scheduledEnd)) {
            $earlyLeaveMinutes = (int) $workEnd->diffInMinutes($scheduledEnd);
        }

        return [$lateMinutes, $earlyLeaveMinutes];
    }
}

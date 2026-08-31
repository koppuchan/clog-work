<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Models\DailyWorkSummary;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * 2025年12月の勤務実績テストデータSeeder
 */
class December2025WorkSummarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyId = 1;
        $users = User::all();
        $startDate = CarbonImmutable::create(2025, 12, 1);
        $endDate = CarbonImmutable::create(2025, 12, 31);

        // 既存の2025年12月データを削除
        DailyWorkSummary::where('company_id', $companyId)
            ->whereBetween('work_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->delete();

        foreach ($users as $user) {
            $this->createUserWorkSummaries($companyId, $user, $startDate, $endDate);
        }

        $this->command->info('2025年12月の勤務実績データを作成しました。');
    }

    /**
     * ユーザーごとの勤務実績を作成
     */
    private function createUserWorkSummaries(int $companyId, User $user, CarbonImmutable $startDate, CarbonImmutable $endDate): void
    {
        $currentDate = $startDate;

        // ユーザーごとに異なるパターンを設定
        $userPattern = $this->getUserPattern($user->id);

        while ($currentDate->lte($endDate)) {
            $data = $this->generateDayData($companyId, $user->id, $currentDate, $userPattern);

            if ($data !== null) {
                DailyWorkSummary::create($data);
            }

            $currentDate = $currentDate->addDay();
        }
    }

    /**
     * ユーザーごとのパターンを取得
     */
    private function getUserPattern(int $userId): array
    {
        $patterns = [
            1 => [ // 山田 太郎 - 通常勤務、たまに遅刻
                'schedule_start' => '09:00',
                'schedule_end' => '18:00',
                'late_days' => [3, 15], // 遅刻日
                'early_leave_days' => [],
                'paid_leave_days' => [25, 26], // 有給
                'special_leave_days' => [],
                'absence_days' => [],
                'overtime_pattern' => 'normal',
            ],
            2 => [ // 佐藤 花子 - 早退あり、有給多め
                'schedule_start' => '09:00',
                'schedule_end' => '18:00',
                'late_days' => [],
                'early_leave_days' => [5, 18],
                'paid_leave_days' => [12, 24, 25, 26],
                'special_leave_days' => [],
                'absence_days' => [],
                'overtime_pattern' => 'low',
            ],
            3 => [ // 鈴木 一郎 - 残業多め
                'schedule_start' => '09:00',
                'schedule_end' => '18:00',
                'late_days' => [8],
                'early_leave_days' => [],
                'paid_leave_days' => [31],
                'special_leave_days' => [29, 30],
                'absence_days' => [],
                'overtime_pattern' => 'high',
            ],
            4 => [ // 田中 美咲 - 欠勤あり
                'schedule_start' => '10:00',
                'schedule_end' => '19:00',
                'late_days' => [2, 16],
                'early_leave_days' => [9],
                'paid_leave_days' => [25],
                'special_leave_days' => [],
                'absence_days' => [10],
                'overtime_pattern' => 'normal',
            ],
            5 => [ // たけし - 深夜勤務パターン
                'schedule_start' => '13:00',
                'schedule_end' => '22:00',
                'late_days' => [4],
                'early_leave_days' => [19],
                'paid_leave_days' => [25, 26],
                'special_leave_days' => [],
                'absence_days' => [],
                'overtime_pattern' => 'night',
            ],
        ];

        return $patterns[$userId] ?? $patterns[1];
    }

    /**
     * 1日分のデータを生成
     */
    private function generateDayData(int $companyId, int $userId, CarbonImmutable $date, array $pattern): ?array
    {
        $day = $date->day;
        $dayOfWeek = $date->dayOfWeek;

        // 土日は基本的に休み（一部休日出勤あり）
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            // 12月6日（土）と12月13日（土）は休日出勤のパターン
            if ($userId === 3 && in_array($day, [6, 13])) {
                return $this->createHolidayWork($companyId, $userId, $date, $pattern);
            }

            return null;
        }

        // 有給休暇
        if (in_array($day, $pattern['paid_leave_days'])) {
            return $this->createLeaveData($companyId, $userId, $date, LeaveTypeEnum::PAID_LEAVE, $pattern);
        }

        // 特別休暇
        if (in_array($day, $pattern['special_leave_days'])) {
            return $this->createLeaveData($companyId, $userId, $date, LeaveTypeEnum::SPECIAL_LEAVE, $pattern);
        }

        // 欠勤
        if (in_array($day, $pattern['absence_days'])) {
            return $this->createLeaveData($companyId, $userId, $date, LeaveTypeEnum::ABSENCE, $pattern);
        }

        // 通常出勤
        return $this->createWorkData($companyId, $userId, $date, $pattern, $day);
    }

    /**
     * 休暇データを作成
     */
    private function createLeaveData(int $companyId, int $userId, CarbonImmutable $date, LeaveTypeEnum $leaveType, array $pattern): array
    {
        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'work_date' => $date->format('Y-m-d'),
            'scheduled_start_time' => $pattern['schedule_start'],
            'scheduled_end_time' => $pattern['schedule_end'],
            'work_start' => null,
            'work_end' => null,
            'work_minutes' => 0,
            'break_minutes' => 0,
            'net_work_minutes' => 0,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::REQUEST,
            'note' => $leaveType->label(),
            'leave_type' => $leaveType,
            'leave_minutes' => 480, // 8時間
        ];
    }

    /**
     * 通常勤務データを作成
     */
    private function createWorkData(int $companyId, int $userId, CarbonImmutable $date, array $pattern, int $day): array
    {
        $isLate = in_array($day, $pattern['late_days']);
        $isEarlyLeave = in_array($day, $pattern['early_leave_days']);

        // 始業時間
        [$scheduleStartHour, $scheduleStartMin] = explode(':', $pattern['schedule_start']);
        $scheduleStart = $date->setTime((int) $scheduleStartHour, (int) $scheduleStartMin);

        // 終業時間
        [$scheduleEndHour, $scheduleEndMin] = explode(':', $pattern['schedule_end']);
        $scheduleEnd = $date->setTime((int) $scheduleEndHour, (int) $scheduleEndMin);

        // 実際の出勤時間
        $lateMinutes = 0;
        if ($isLate) {
            $lateMinutes = rand(10, 45);
            $workStart = $scheduleStart->addMinutes($lateMinutes);
        } else {
            // たまに少し早く出勤
            $earlyArrival = rand(0, 10);
            $workStart = $scheduleStart->subMinutes($earlyArrival);
        }

        // 実際の退勤時間
        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        if ($isEarlyLeave) {
            $earlyLeaveMinutes = rand(30, 90);
            $workEnd = $scheduleEnd->subMinutes($earlyLeaveMinutes);
        } else {
            // 残業パターン
            $overtimeMinutes = $this->calculateOvertime($pattern['overtime_pattern'], $day);
            $workEnd = $scheduleEnd->addMinutes($overtimeMinutes);
        }

        // 勤務時間計算
        $workMinutes = $workStart->diffInMinutes($workEnd);
        $breakMinutes = 60; // 休憩1時間
        $netWorkMinutes = max(0, $workMinutes - $breakMinutes);

        // 深夜時間（22:00以降）
        $nightMinutes = 0;
        if ($pattern['overtime_pattern'] === 'night' || $workEnd->hour >= 22) {
            $nightStart = $date->setTime(22, 0);
            if ($workEnd->gt($nightStart)) {
                $nightMinutes = $nightStart->diffInMinutes($workEnd);
            }
        }

        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'work_date' => $date->format('Y-m-d'),
            'scheduled_start_time' => $pattern['schedule_start'],
            'scheduled_end_time' => $pattern['schedule_end'],
            'work_start' => $workStart,
            'work_end' => $workEnd,
            'work_minutes' => $workMinutes,
            'break_minutes' => $breakMinutes,
            'net_work_minutes' => $netWorkMinutes,
            'night_minutes' => $nightMinutes,
            'holiday_minutes' => 0,
            'overtime_minutes' => max(0, $overtimeMinutes),
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::AUTO,
            'note' => $this->generateNote($isLate, $isEarlyLeave, $overtimeMinutes),
            'leave_type' => null,
            'leave_minutes' => 0,
        ];
    }

    /**
     * 休日出勤データを作成
     */
    private function createHolidayWork(int $companyId, int $userId, CarbonImmutable $date, array $pattern): array
    {
        $workStart = $date->setTime(10, 0);
        $workEnd = $date->setTime(17, 0);
        $workMinutes = 420; // 7時間
        $breakMinutes = 60;
        $netWorkMinutes = 360; // 6時間
        $holidayMinutes = 360;

        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'work_date' => $date->format('Y-m-d'),
            'scheduled_start_time' => null,
            'scheduled_end_time' => null,
            'work_start' => $workStart,
            'work_end' => $workEnd,
            'work_minutes' => $workMinutes,
            'break_minutes' => $breakMinutes,
            'net_work_minutes' => $netWorkMinutes,
            'night_minutes' => 0,
            'holiday_minutes' => $holidayMinutes,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::AUTO,
            'note' => '休日出勤',
            'leave_type' => null,
            'leave_minutes' => 0,
        ];
    }

    /**
     * 残業時間を計算
     */
    private function calculateOvertime(string $pattern, int $day): int
    {
        return match ($pattern) {
            'high' => rand(60, 180), // 1〜3時間
            'low' => $day % 5 === 0 ? rand(0, 30) : 0, // たまに30分
            'night' => rand(30, 120), // 深夜パターンは1〜2時間
            default => $day % 3 === 0 ? rand(30, 90) : rand(0, 30), // 通常は0〜1.5時間
        };
    }

    /**
     * 備考を生成
     */
    private function generateNote(bool $isLate, bool $isEarlyLeave, int $overtimeMinutes): ?string
    {
        $notes = [];

        if ($isLate) {
            $notes[] = '遅刻（電車遅延）';
        }

        if ($isEarlyLeave) {
            $notes[] = '早退（私用）';
        }

        if ($overtimeMinutes > 120) {
            $notes[] = '残業（プロジェクト対応）';
        }

        return empty($notes) ? null : implode('、', $notes);
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\CompanyRegularHoliday;
use App\Models\CompanyShiftDisplaySetting;
use App\Models\CompanyShiftRoundingSetting;
use App\Models\DailyWorkSummary;
use App\Models\Department;
use App\Models\Request;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\User;
use App\Models\UserAvailableWorkDay;
use App\Models\UserPermission;
use App\Models\UserShiftPattern;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 機能確認用Seeder
 *
 * 今回の修正（残業申請反映、有給日数計算、締め日出力など）の動作確認用データを作成
 * - 締め日20日設定
 * - 有給（終日・0.5日・時間単位）
 * - 残業申請（承認済み）
 * - 通常勤務（残業あり・遅刻あり）
 */
class FeatureVerificationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // ========================================
            // 1. 会社を作成（締め日20日・時間有給ON）
            // ========================================
            $maxCode = Company::query()->max('company_code') ?? '100000';
            $newCode = str_pad((string) ((int) $maxCode + 1), 6, '0', STR_PAD_LEFT);

            $company = Company::create([
                'company_code' => $newCode,
                'name' => '確認用株式会社',
                'is_closed_on_holidays' => true,
                'payroll_closing_day' => 20,
                'paid_leave_half_day' => false,
                'paid_leave_hourly' => true,
            ]);

            $this->command->info("会社作成: 確認用株式会社 (コード: {$newCode})");
            $this->command->info('  締め日: 20日 / 有給: 時間単位ON');

            // 定休日（土日）
            foreach ([6, 7] as $weekdayId) {
                CompanyRegularHoliday::firstOrCreate([
                    'company_id' => $company->id,
                    'weekday_id' => $weekdayId,
                ]);
            }

            // 打刻丸め（15分単位）
            CompanyShiftRoundingSetting::firstOrCreate(
                ['company_id' => $company->id],
                ['rounding_unit_id' => 4]
            );

            // シフト表示設定（締め日翌日スタート）
            CompanyShiftDisplaySetting::firstOrCreate(
                ['company_id' => $company->id],
                ['shift_period_id' => 2]
            );

            // ========================================
            // 2. 部署・シフトパターン
            // ========================================
            $dept = Department::firstOrCreate(
                ['company_id' => $company->id, 'name' => '総務部']
            );

            $shiftPattern = ShiftPattern::firstOrCreate(
                ['company_id' => $company->id, 'name' => '日勤'],
                [
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'work_minutes' => 480,
                    'break_mode' => 1,
                    'break_minutes' => 60,
                    'break_start' => '12:00:00',
                    'break_end' => '13:00:00',
                ]
            );

            $company->update(['default_shift_pattern_id' => $shiftPattern->id]);

            // ========================================
            // 3. ユーザー作成（管理者1名 + 一般1名）
            // ========================================
            $companySuffix = ltrim($newCode, '0');

            // 管理者
            $admin = $this->createUser($company, $dept, $shiftPattern, [
                'name' => '確認 管理者',
                'name_kana' => 'カクニン カンリシャ',
                'email' => "verify-admin+{$companySuffix}@demo.com",
                'employee_code' => substr($newCode, -4).'01',
                'role_id' => 1,
            ]);

            // 一般スタッフ
            $staff = $this->createUser($company, $dept, $shiftPattern, [
                'name' => '確認 太郎',
                'name_kana' => 'カクニン タロウ',
                'email' => "verify-staff+{$companySuffix}@demo.com",
                'employee_code' => substr($newCode, -4).'02',
                'role_id' => 3,
            ]);

            $this->command->info('');
            $this->command->info("管理者: verify-admin+{$companySuffix}@demo.com / password");
            $this->command->info("スタッフ: verify-staff+{$companySuffix}@demo.com / password");

            // ========================================
            // 4. 2026年3月の勤務データ作成
            // ========================================
            $this->command->info('');
            $this->command->info('2026年1月の勤務データを作成中...');

            $now = CarbonImmutable::now();

            // --- 有給（終日）: 1/8（木） ---
            $this->createLeaveDay($company, $staff, $admin, $shiftPattern, '2026-01-08', [
                'type' => 1, // paid-leave
                'reason' => '私用のため（終日有給テスト）',
            ]);
            $this->command->info('  1/8: 終日有給');

            // --- 有給（0.5日）: 1/13（火） ---
            $this->createLeaveDay($company, $staff, $admin, $shiftPattern, '2026-01-13', [
                'type' => 9, // half-day-leave
                'reason' => '通院のため（半日有給テスト）',
                'start_time' => '09:00',
                'end_time' => '13:00',
                'leave_minutes' => 240,
            ]);
            $this->command->info('  1/13: 半日有給（0.5日）');

            // --- 時間有給（2時間）: 1/15（木） ---
            $this->createLeaveDay($company, $staff, $admin, $shiftPattern, '2026-01-15', [
                'type' => 10, // hourly-leave
                'reason' => '子供の学校行事（2時間有給テスト）',
                'start_time' => '14:00',
                'end_time' => '16:00',
                'leave_minutes' => 120,
            ]);
            // 時間有給の日は勤務もある
            $this->createWorkDay(
                $company, $staff, $shiftPattern,
                '2026-01-15',
                '09:00', '18:00',
                120  // 2H有給分のleave_minutes
            );
            $this->command->info('  1/15: 時間有給（2時間 = 0.25日）+ 勤務');

            // --- 残業申請（承認済み）: 1/20（火） ---
            // 実打刻: 9:00〜18:45（残業45分）だが、申請は30分
            $this->createWorkDay($company, $staff, $shiftPattern, '2026-01-20', '09:00', '18:45');
            $this->createOvertimeRequest($company, $staff, $admin, '2026-01-20', '18:00', '18:30');
            $this->command->info('  1/20: 残業（打刻45分、申請30分→申請30分で反映）');

            // --- 通常勤務（残業あり）: 複数日 ---
            $normalDays = [
                '2026-01-02' => ['09:00', '18:30', 0],   // 残業30分
                '2026-01-05' => ['09:00', '19:00', 0],   // 残業1時間
                '2026-01-06' => ['09:00', '18:00', 0],   // 定時
                '2026-01-07' => ['09:05', '18:00', 5],   // 遅刻5分
                '2026-01-09' => ['09:00', '18:00', 0],   // 定時
                '2026-01-12' => ['09:00', '20:00', 0],   // 残業2時間
                '2026-01-14' => ['09:00', '18:00', 0],   // 定時
                '2026-01-16' => ['09:00', '18:15', 0],   // 残業15分
                '2026-01-19' => ['09:00', '18:00', 0],   // 定時
                '2026-01-21' => ['09:00', '18:30', 0],   // 残業30分
                '2026-01-22' => ['09:10', '18:00', 10],  // 遅刻10分
                '2026-01-23' => ['09:00', '18:00', 0],   // 定時
                '2026-01-26' => ['09:00', '18:45', 0],   // 残業45分
                '2026-01-27' => ['09:00', '18:00', 0],   // 定時
                '2026-01-28' => ['09:00', '19:30', 0],   // 残業1.5時間
                '2026-01-29' => ['09:00', '18:00', 0],   // 定時
                '2026-01-30' => ['09:00', '18:30', 0],   // 残業30分
            ];

            foreach ($normalDays as $date => [$start, $end, $lateMin]) {
                $this->createWorkDay($company, $staff, $shiftPattern, $date, $start, $end, null, $lateMin);
            }
            $this->command->info('  通常勤務日: 17日分（残業・遅刻含む）');

            // ========================================
            // 5. サマリー表示
            // ========================================
            $this->command->info('');
            $this->command->info('========================================');
            $this->command->info('確認用データの作成が完了しました！');
            $this->command->info('========================================');
            $this->command->info('');
            $this->command->info('【確認ポイント】');
            $this->command->info("  会社コード: {$newCode}");
            $this->command->info('  締め日: 20日 → レポート期間: 12/21〜1/20 or 1/21〜2/20');
            $this->command->info('  有給設定: 時間単位ON');
            $this->command->info('');
            $this->command->info('【有給データ】');
            $this->command->info('  1/8:  終日有給 → 1.0日');
            $this->command->info('  1/13: 半日有給 → 0.5日');
            $this->command->info('  1/15: 2時間有給 → 0.25日（480分中120分）');
            $this->command->info('  合計: 1.75日 が表示されるべき');
            $this->command->info('');
            $this->command->info('【残業申請データ】');
            $this->command->info('  1/20: 打刻上は45分残業だが、申請30分で承認済み');
            $this->command->info('  → overtime_minutes=30 が表示されるべき');
            $this->command->info('');
            $this->command->info('【締め日出力確認】');
            $this->command->info('  CSV/Excelエクスポート時、12/21〜1/20 の期間で出力されるべき');
        });
    }

    /**
     * ユーザー作成ヘルパー
     */
    private function createUser(
        Company $company,
        Department $dept,
        ShiftPattern $shiftPattern,
        array $data
    ): User {
        $user = User::create([
            'name' => $data['name'],
            'name_kana' => $data['name_kana'],
            'employee_code' => $data['employee_code'],
            'email' => $data['email'],
            'email_verified_at' => CarbonImmutable::now(),
            'password' => 'password',
            'must_change_password' => false,
            'is_retired' => false,
        ]);

        $user->companies()->attach($company->id, ['is_primary' => true]);
        $user->roles()->attach($data['role_id']);
        $user->departments()->attach($dept->id, ['is_primary' => true]);

        for ($weekdayId = 1; $weekdayId <= 7; $weekdayId++) {
            UserAvailableWorkDay::create([
                'user_id' => $user->id,
                'weekday_id' => $weekdayId,
                'is_available' => $weekdayId <= 5,
            ]);
            UserShiftPattern::create([
                'user_id' => $user->id,
                'weekday_id' => $weekdayId,
                'shift_pattern_id' => $weekdayId <= 5 ? $shiftPattern->id : null,
            ]);
        }

        // 管理者以外は権限設定
        if ($data['role_id'] !== 1) {
            UserPermission::create(['user_id' => $user->id, 'resource_id' => 1, 'scope_id' => 2]);
            UserPermission::create(['user_id' => $user->id, 'resource_id' => 2, 'scope_id' => 1]);
        }

        return $user;
    }

    /**
     * 通常勤務日データ作成
     */
    private function createWorkDay(
        Company $company,
        User $user,
        ShiftPattern $shiftPattern,
        string $date,
        string $startTime,
        string $endTime,
        ?int $leaveMinutes = null,
        int $lateMinutes = 0,
    ): void {
        $dateStr = $date;

        Shift::firstOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id, 'shift_date' => $dateStr],
            ['shift_pattern_id' => $shiftPattern->id]
        );

        $actualStart = CarbonImmutable::parse("{$dateStr} {$startTime}");
        $actualEnd = CarbonImmutable::parse("{$dateStr} {$endTime}");

        TimeRecord::firstOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id, 'record_type' => TimeRecordTypeEnum::WORK_START, 'record_time' => $actualStart],
            ['rounded_time' => $actualStart, 'record_source' => RecordSourceEnum::AUTO]
        );
        TimeRecord::firstOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id, 'record_type' => TimeRecordTypeEnum::WORK_END, 'record_time' => $actualEnd],
            ['rounded_time' => $actualEnd, 'record_source' => RecordSourceEnum::AUTO]
        );

        $workMinutes = (int) $actualStart->diffInMinutes($actualEnd);
        $breakMinutes = $shiftPattern->break_minutes;
        $netWorkMinutes = $workMinutes - $breakMinutes;
        $overtimeMinutes = max(0, $netWorkMinutes - $shiftPattern->work_minutes);

        $summaryData = [
            'scheduled_start_time' => $shiftPattern->start_time,
            'scheduled_end_time' => $shiftPattern->end_time,
            'work_start' => $actualStart,
            'work_end' => $actualEnd,
            'work_minutes' => $workMinutes,
            'break_minutes' => $breakMinutes,
            'net_work_minutes' => $netWorkMinutes,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => $overtimeMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => 0,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::AUTO,
        ];

        if ($leaveMinutes !== null) {
            $summaryData['leave_type'] = LeaveTypeEnum::PAID_LEAVE->value;
            $summaryData['leave_minutes'] = $leaveMinutes;
        }

        DailyWorkSummary::updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id, 'work_date' => $dateStr],
            $summaryData
        );
    }

    /**
     * 有給休暇日データ作成（申請＋日次実績）
     */
    private function createLeaveDay(
        Company $company,
        User $staff,
        User $admin,
        ShiftPattern $shiftPattern,
        string $date,
        array $leaveData,
    ): void {
        $now = CarbonImmutable::now();

        // シフト
        Shift::firstOrCreate(
            ['company_id' => $company->id, 'user_id' => $staff->id, 'shift_date' => $date],
            ['shift_pattern_id' => $shiftPattern->id]
        );

        // 申請（承認済み）
        Request::firstOrCreate(
            ['company_id' => $company->id, 'requested_by' => $staff->id, 'type' => $leaveData['type'], 'target_date' => $date],
            [
                'start_time' => $leaveData['start_time'] ?? null,
                'end_time' => $leaveData['end_time'] ?? null,
                'reason' => $leaveData['reason'],
                'status' => RequestStatusEnum::APPROVED->value,
                'approver_id' => $admin->id,
                'decided_at' => $now,
            ]
        );

        // 日次実績（有給）
        $leaveMinutes = $leaveData['leave_minutes'] ?? null;

        DailyWorkSummary::firstOrCreate(
            ['company_id' => $company->id, 'user_id' => $staff->id, 'work_date' => $date],
            [
                'scheduled_start_time' => $shiftPattern->start_time,
                'scheduled_end_time' => $shiftPattern->end_time,
                'leave_type' => LeaveTypeEnum::PAID_LEAVE->value,
                'leave_minutes' => $leaveMinutes,
                'record_source' => RecordSourceEnum::REQUEST,
            ]
        );
    }

    /**
     * 残業申請作成（承認済み → overtime_minutes上書き）
     */
    private function createOvertimeRequest(
        Company $company,
        User $staff,
        User $admin,
        string $date,
        string $startTime,
        string $endTime,
    ): void {
        $now = CarbonImmutable::now();

        // 残業申請（承認済み）
        Request::firstOrCreate(
            ['company_id' => $company->id, 'requested_by' => $staff->id, 'type' => 7, 'target_date' => $date],
            [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reason' => '顧客対応のため（残業申請テスト）',
                'status' => RequestStatusEnum::APPROVED->value,
                'approver_id' => $admin->id,
                'decided_at' => $now,
            ]
        );

        // overtime_minutesを申請時間で上書き
        $startMinutes = (int) explode(':', $startTime)[0] * 60 + (int) explode(':', $startTime)[1];
        $endMinutes = (int) explode(':', $endTime)[0] * 60 + (int) explode(':', $endTime)[1];
        $overtimeMinutes = $endMinutes - $startMinutes;

        DailyWorkSummary::query()
            ->where('company_id', $company->id)
            ->where('user_id', $staff->id)
            ->where('work_date', $date)
            ->update([
                'overtime_minutes' => $overtimeMinutes,
                'record_source' => RecordSourceEnum::REQUEST,
            ]);
    }
}

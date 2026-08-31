<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Exceptions\NotFoundException;
use App\Models\Company;
use App\Models\CompanyShiftRoundingSetting;
use App\Models\DailyWorkSummary;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\TimeRecordCorrection;
use App\Models\User;
use App\Services\DailyWorkSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DailyWorkSummaryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DailyWorkSummaryService $service;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DailyWorkSummaryService::class);

        // テスト用の会社を作成
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
        ]);

        // デフォルトシフトパターン（480分 = 8時間）を作成して紐付け
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        $this->company->update(['default_shift_pattern_id' => $shiftPattern->id]);

        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create([
                'name' => 'Test User',
                'is_retired' => false,
            ]);
    }

    // ========================================
    // calculateMonthlySummary 有給日数計算テスト
    // ========================================

    /**
     * @test
     */
    public function calculate_monthly_summary_counts_full_day_paid_leave(): void
    {
        // Arrange: 終日有給2日
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-15',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert
        $this->assertEquals(2.0, $result['paid_leave_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_counts_2_hour_leave_as_025_days(): void
    {
        // Arrange: 2時間有給（8時間勤務の会社 → 2/8 = 0.25日）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => 120, // 2時間
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert
        $this->assertEquals(0.25, $result['paid_leave_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_counts_half_day_leave_as_050_days(): void
    {
        // Arrange: 半日有給（4時間 = 240分 → 240/480 = 0.5日）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => 240, // 4時間
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert
        $this->assertEquals(0.5, $result['paid_leave_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_counts_mixed_leave_correctly(): void
    {
        // Arrange: 1日 + 2時間有給 = 1.0 + 0.25 = 1.25日
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null, // 終日
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-15',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => 120, // 2時間
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert: 1日 + 2H有給 = 1.25日
        $this->assertEquals(1.25, $result['paid_leave_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_counts_1_hour_leave(): void
    {
        // Arrange: 1時間有給（60/480 = 0.13）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => 60,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert: 60/480 = 0.125 → round(,2) = 0.13
        $this->assertEquals(0.13, $result['paid_leave_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_counts_3_hour_leave(): void
    {
        // Arrange: 3時間有給（180/480 = 0.375 → 0.38）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => 180,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert: 180/480 = 0.375 → round(,2) = 0.38
        $this->assertEquals(0.38, $result['paid_leave_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_counts_special_leave_separately(): void
    {
        // Arrange: 有給1日 + 特別休暇1日
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-15',
            'leave_type' => LeaveTypeEnum::SPECIAL_LEAVE,
            'leave_minutes' => null,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert
        $this->assertEquals(1.0, $result['paid_leave_days']);
        $this->assertEquals(1.0, $result['special_leave_days']);
        $this->assertEquals(0.0, $result['absence_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_returns_zero_leave_when_no_leave(): void
    {
        // Arrange: 有給なしの通常勤務日
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'work_minutes' => 480,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert
        $this->assertEquals(0.0, $result['paid_leave_days']);
        $this->assertEquals(0.0, $result['special_leave_days']);
        $this->assertEquals(0.0, $result['absence_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_respects_date_range_for_leave(): void
    {
        // Arrange: 1月と2月に有給
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-02-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act: 1月分のみ取得
        $result = $this->service->calculateMonthlySummary(
            $this->company->id,
            $this->user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert: 1月の1日分のみ
        $this->assertEquals(1.0, $result['paid_leave_days']);
    }

    /**
     * @test
     */
    public function calculate_monthly_summary_uses_default_working_minutes_when_no_shift_pattern(): void
    {
        // Arrange: デフォルトシフトパターンなしの会社
        $companyNoPattern = Company::factory()->create([
            'name' => 'No Pattern Company',
            'default_shift_pattern_id' => null,
        ]);

        $user = User::factory()
            ->forCompany($companyNoPattern->id)
            ->create([
                'name' => 'User No Pattern',
                'is_retired' => false,
            ]);

        // 2時間有給（デフォルト480分 → 120/480 = 0.25日）
        DailyWorkSummary::query()->create([
            'company_id' => $companyNoPattern->id,
            'user_id' => $user->id,
            'work_date' => '2025-01-10',
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => 120,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->calculateMonthlySummary(
            $companyNoPattern->id,
            $user->id,
            '2025-01-01',
            '2025-01-31'
        );

        // Assert: デフォルト480分で計算 → 0.25
        $this->assertEquals(0.25, $result['paid_leave_days']);
    }

    // ========================================
    // deleteWorkTimes テスト
    // ========================================

    /**
     * @test
     */
    public function delete_work_times_removes_summary_and_time_records(): void
    {
        // Arrange: 勤務実績と打刻レコード（出勤・退勤・休憩開始・休憩終了）を作成
        $workDate = '2025-02-10';
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 60,
            'net_work_minutes' => 480,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $records = collect([
            ['type' => TimeRecordTypeEnum::WORK_START, 'time' => '2025-02-10 09:00:00'],
            ['type' => TimeRecordTypeEnum::BREAK_START, 'time' => '2025-02-10 12:00:00'],
            ['type' => TimeRecordTypeEnum::BREAK_END, 'time' => '2025-02-10 13:00:00'],
            ['type' => TimeRecordTypeEnum::WORK_END, 'time' => '2025-02-10 18:00:00'],
        ])->map(fn ($r) => TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => $r['type'],
            'record_time' => CarbonImmutable::parse($r['time']),
            'rounded_time' => CarbonImmutable::parse($r['time']),
            'record_source' => RecordSourceEnum::AUTO,
        ]));

        // Act
        $this->service->deleteWorkTimes($summary->id);

        // Assert: 勤務実績と打刻レコードが全て削除されている
        $this->assertDatabaseMissing('daily_work_summaries', ['id' => $summary->id]);
        $records->each(function (TimeRecord $record) {
            $this->assertDatabaseMissing('time_records', ['id' => $record->id]);
        });
    }

    /**
     * @test
     */
    public function delete_work_times_removes_cross_day_end_record_on_next_day(): void
    {
        // Arrange: 日跨ぎ勤務（前日23:00出勤、翌日02:00退勤）
        $workDate = '2025-02-15';
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => '23:00:00',
            'work_end' => '02:00:00',
            'work_minutes' => 180,
            'break_minutes' => 0,
            'net_work_minutes' => 180,
            'is_cross_day' => true,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $startRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => CarbonImmutable::parse('2025-02-15 23:00:00'),
            'rounded_time' => CarbonImmutable::parse('2025-02-15 23:00:00'),
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 翌日に日跨ぎ退勤
        $endRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => CarbonImmutable::parse('2025-02-16 02:00:00'),
            'rounded_time' => CarbonImmutable::parse('2025-02-16 02:00:00'),
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->deleteWorkTimes($summary->id);

        // Assert: サマリー、出勤打刻、翌日の日跨ぎ退勤打刻すべて削除
        $this->assertDatabaseMissing('daily_work_summaries', ['id' => $summary->id]);
        $this->assertDatabaseMissing('time_records', ['id' => $startRecord->id]);
        $this->assertDatabaseMissing('time_records', ['id' => $endRecord->id]);
    }

    /**
     * @test
     */
    public function delete_work_times_throws_when_summary_not_found(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->deleteWorkTimes(999999);
    }

    // ========================================
    // updateWorkTimes 休憩日付正規化テスト
    // ========================================

    /**
     * @test
     */
    public function update_work_times_adjusts_break_period_to_next_day_on_night_shift(): void
    {
        // Arrange: 22:00出勤・翌10:45退勤の夜勤、休憩02:00-03:30
        $workDate = '2025-02-10';
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => '22:00:00',
            'work_end' => '10:45:00',
            'work_minutes' => 765,
            'break_minutes' => 0,
            'net_work_minutes' => 765,
            'is_cross_day' => true,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act: 編集モーダル経由で休憩02:00-03:30を登録
        $this->service->updateWorkTimes(
            $summary->id,
            '22:00',
            '10:45',
            [['start' => '02:00', 'end' => '03:30']],
            null
        );

        // Assert: 休憩開始/終了が翌日(2025-02-11)に正規化されている
        $breakStart = TimeRecord::query()
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::BREAK_START->value)
            ->first();

        $this->assertNotNull($breakStart);
        $this->assertEquals('2025-02-11 02:00:00', $breakStart->record_time->format('Y-m-d H:i:s'));

        $breakEnd = TimeRecord::query()
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::BREAK_END->value)
            ->first();

        $this->assertNotNull($breakEnd);
        $this->assertEquals('2025-02-11 03:30:00', $breakEnd->record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function update_work_times_preserves_break_period_on_day_shift(): void
    {
        // Arrange: 09:00-18:00日中勤務、12:00-13:00休憩
        $workDate = '2025-02-12';
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 60,
            'net_work_minutes' => 480,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->updateWorkTimes(
            $summary->id,
            '09:00',
            '18:00',
            [['start' => '12:00', 'end' => '13:00']],
            null
        );

        // Assert: 休憩は当日のまま（リグレッションガード）
        $breakStart = TimeRecord::query()
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::BREAK_START->value)
            ->first();

        $this->assertNotNull($breakStart);
        $this->assertEquals('2025-02-12 12:00:00', $breakStart->record_time->format('Y-m-d H:i:s'));

        $breakEnd = TimeRecord::query()
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::BREAK_END->value)
            ->first();

        $this->assertNotNull($breakEnd);
        $this->assertEquals('2025-02-12 13:00:00', $breakEnd->record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function update_work_times_handles_cross_midnight_break(): void
    {
        // Arrange: 22:00-翌06:00夜勤、23:30-00:30休憩（日跨ぎ休憩）
        $workDate = '2025-02-15';
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => '22:00:00',
            'work_end' => '06:00:00',
            'work_minutes' => 480,
            'break_minutes' => 0,
            'net_work_minutes' => 480,
            'is_cross_day' => true,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->updateWorkTimes(
            $summary->id,
            '22:00',
            '06:00',
            [['start' => '23:30', 'end' => '00:30']],
            null
        );

        // Assert: BREAK_START は当日23:30、BREAK_END は翌日00:30
        $breakStart = TimeRecord::query()
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::BREAK_START->value)
            ->first();

        $this->assertNotNull($breakStart);
        $this->assertEquals('2025-02-15 23:30:00', $breakStart->record_time->format('Y-m-d H:i:s'));

        $breakEnd = TimeRecord::query()
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::BREAK_END->value)
            ->first();

        $this->assertNotNull($breakEnd);
        $this->assertEquals('2025-02-16 00:30:00', $breakEnd->record_time->format('Y-m-d H:i:s'));
    }

    // ========================================
    // updateWorkTimes における after_rounded_time の正しさ
    // 課題1: 修正履歴の after_rounded_time も丸めた値で記録されるべき
    // ========================================

    /**
     * @test
     */
    public function update_work_times_records_rounded_after_rounded_time_for_existing_break_correction(): void
    {
        // Arrange: 5分単位の丸め設定を有効化
        CompanyShiftRoundingSetting::query()->create([
            'company_id' => $this->company->id,
            'rounding_unit_id' => 2, // 5分単位
        ]);

        $workDate = '2025-04-01';

        // 既存の打刻を作成（休憩あり）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $workDate.' 09:00:00',
            'rounded_time' => $workDate.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $existingBreakStart = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $workDate.' 12:00:00',
            'rounded_time' => $workDate.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $existingBreakEnd = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $workDate.' 13:00:00',
            'rounded_time' => $workDate.' 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $workDate.' 18:00:00',
            'rounded_time' => $workDate.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 60,
            'net_work_minutes' => 480,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 修正者ユーザー（管理者）を作成
        $admin = User::factory()->forCompany($this->company->id)->create();

        // Act: 休憩を 12:03〜12:58 に修正
        // 5分単位丸め: BREAK_START は切り捨て(12:00)、BREAK_END は切り上げ(13:00)
        // ※従業員不利にならないよう、休憩はBREAK_START切り捨て・BREAK_END切り上げ設計
        $this->service->updateWorkTimes(
            $summary->id,
            '09:00',
            '18:00',
            [['start' => '12:03', 'end' => '12:58']],
            $admin->id
        );

        // Assert: 既存BREAK_STARTの修正履歴の after_rounded_time が 12:00 になっている
        $breakStartCorrection = TimeRecordCorrection::query()
            ->where('time_record_id', $existingBreakStart->id)
            ->first();

        $this->assertNotNull($breakStartCorrection);
        $this->assertEquals(
            $workDate.' 12:03:00',
            $breakStartCorrection->after_record_time->format('Y-m-d H:i:s'),
            'after_record_time は生値（12:03）が記録される'
        );
        $this->assertEquals(
            $workDate.' 12:00:00',
            $breakStartCorrection->after_rounded_time->format('Y-m-d H:i:s'),
            'after_rounded_time は丸めた値（12:00 = 切り捨て）が記録されるべき'
        );

        // Assert: 既存BREAK_ENDの修正履歴の after_rounded_time が 13:00 になっている
        $breakEndCorrection = TimeRecordCorrection::query()
            ->where('time_record_id', $existingBreakEnd->id)
            ->first();

        $this->assertNotNull($breakEndCorrection);
        $this->assertEquals(
            $workDate.' 12:58:00',
            $breakEndCorrection->after_record_time->format('Y-m-d H:i:s'),
            'after_record_time は生値（12:58）が記録される'
        );
        $this->assertEquals(
            $workDate.' 13:00:00',
            $breakEndCorrection->after_rounded_time->format('Y-m-d H:i:s'),
            'after_rounded_time は丸めた値（13:00 = 切り上げ）が記録されるべき'
        );

        // Assert: time_records 側も update されている（履歴とも一致）
        $existingBreakStart->refresh();
        $existingBreakEnd->refresh();
        $this->assertEquals(
            $workDate.' 12:00:00',
            $existingBreakStart->rounded_time->format('Y-m-d H:i:s'),
            'time_records も丸めた値（12:00）に更新される'
        );
        $this->assertEquals(
            $workDate.' 13:00:00',
            $existingBreakEnd->rounded_time->format('Y-m-d H:i:s'),
            'time_records も丸めた値（13:00）に更新される'
        );
    }

    // ========================================
    // updateWorkTimes 空休憩フォールバック制御テスト
    // ========================================

    /**
     * @test
     *
     * updateWorkTimesでbreakPeriods=[]を指定した場合、シフトパターンの休憩にフォールバックせず0になること
     */
    public function update_work_times_with_empty_break_periods_results_in_zero_break_minutes(): void
    {
        // Arrange: 既存の勤務実績（休憩60分あり）
        $workDate = '2025-02-15';
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => $workDate.' 09:00:00',
            'work_end' => $workDate.' 18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 60,
            'net_work_minutes' => 480,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 既存のWORK_START/END（AUTO）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $workDate.' 09:00:00',
            'rounded_time' => $workDate.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $workDate.' 18:00:00',
            'rounded_time' => $workDate.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 既存の休憩レコード
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $workDate.' 12:00:00',
            'rounded_time' => $workDate.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $workDate.' 13:00:00',
            'rounded_time' => $workDate.' 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act: 休憩なし（空配列）で更新
        $result = $this->service->updateWorkTimes(
            $summary->id,
            '09:00',
            '18:00',
            [],
            null
        );

        // Assert: 休憩0分（シフトパターンの60分にフォールバックしない）
        $this->assertEquals(0, $result->break_minutes, '空休憩で更新したら休憩0になる');
        $this->assertEquals(540, $result->net_work_minutes, '休憩0なので実働=勤務時間');

        // BREAK_START/BREAK_ENDレコードが削除されていること
        $breakRecords = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->whereIn('record_type', [TimeRecordTypeEnum::BREAK_START, TimeRecordTypeEnum::BREAK_END])
            ->count();

        $this->assertEquals(0, $breakRecords, '休憩レコードが削除されている');
    }

    /**
     * @test
     */
    public function update_work_times_with_empty_break_periods_deletes_next_day_normalized_break_records(): void
    {
        // Arrange: 夜勤（22:00出勤・翌10:45退勤）で、翌日に正規化された休憩レコードが既にある
        $workDate = '2025-02-16';
        $nextDate = '2025-02-17';

        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => $workDate.' 22:00:00',
            'work_end' => $nextDate.' 10:45:00',
            'work_minutes' => 765,
            'break_minutes' => 90,
            'net_work_minutes' => 675,
            'is_cross_day' => true,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // WORK_START（当日）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $workDate.' 22:00:00',
            'rounded_time' => $workDate.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // WORK_END_NEXT_DAY（翌日）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDate.' 10:45:00',
            'rounded_time' => $nextDate.' 10:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 翌朝休憩（翌日の日付で保存されている）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $nextDate.' 02:00:00',
            'rounded_time' => $nextDate.' 02:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $nextDate.' 03:30:00',
            'rounded_time' => $nextDate.' 03:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act: 休憩なし（空配列）で更新
        $this->service->updateWorkTimes(
            $summary->id,
            '22:00',
            '10:45',
            [],
            null
        );

        // Assert: 翌日に正規化されていた休憩レコードも削除されている
        $breakRecords = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->whereIn('record_type', [TimeRecordTypeEnum::BREAK_START, TimeRecordTypeEnum::BREAK_END])
            ->count();

        $this->assertEquals(0, $breakRecords, '翌日に正規化された休憩レコードも削除されている');
    }
}

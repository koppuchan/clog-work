<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\DailyWorkSummaryBatchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DailyWorkSummaryBatchServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DailyWorkSummaryBatchService $service;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DailyWorkSummaryBatchService::class);

        // テスト用の会社とユーザーを作成
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'is_closed_on_holidays' => false,
        ]);

        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create([
                'name' => 'Test User',
                'is_retired' => false,
            ]);
    }

    /**
     * @test
     */
    public function aggregate_by_user_creates_daily_work_summary_from_time_records(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // 勤務開始打刻を作成
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 勤務終了打刻を作成
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(540, $summary->work_minutes); // 9時間 = 540分
        $this->assertEquals(RecordSourceEnum::AUTO, $summary->record_source);
    }

    /**
     * @test
     */
    public function aggregate_by_user_calculates_break_minutes_from_break_records(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // 勤務開始
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 休憩開始
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:00:00',
            'rounded_time' => $dateString.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 休憩終了
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 13:00:00',
            'rounded_time' => $dateString.' 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 勤務終了
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(540, $summary->work_minutes); // 9時間 = 540分
        $this->assertEquals(60, $summary->break_minutes); // 1時間 = 60分
        $this->assertEquals(480, $summary->net_work_minutes); // 8時間 = 480分
    }

    /**
     * @test
     */
    public function aggregate_by_user_skips_when_no_work_start_record(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // 勤務終了のみ（開始なし）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('skipped', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNull($summary);
    }

    /**
     * @test
     */
    public function aggregate_by_user_skips_when_manual_record_exists(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // 既存の手動修正された勤務実績
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $dateString,
            'work_start' => $dateString.' 10:00:00',
            'work_end' => $dateString.' 19:00:00',
            'work_minutes' => 540,
            'break_minutes' => 60,
            'net_work_minutes' => 480,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 60,
            'early_leave_minutes' => 0,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::MANUAL, // 手動修正
        ]);

        // 打刻レコードを作成
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('skipped', $result);

        // 既存のレコードが変更されていないことを確認
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(60, $summary->late_minutes); // 手動で設定された遅刻時間
        $this->assertEquals(RecordSourceEnum::MANUAL, $summary->record_source);
    }

    /**
     * @test
     */
    public function aggregate_by_user_updates_existing_auto_record(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // 既存の自動集計された勤務実績
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $dateString,
            'work_start' => $dateString.' 09:00:00',
            'work_end' => $dateString.' 17:00:00',
            'work_minutes' => 480,
            'break_minutes' => 0,
            'net_work_minutes' => 480,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 新しい打刻レコードを作成（終了時刻が異なる）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('updated', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(540, $summary->work_minutes); // 更新後の9時間
    }

    /**
     * @test
     */
    public function aggregate_by_user_calculates_late_minutes(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // シフトパターンを作成
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        // シフトを作成
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 遅刻した打刻（09:30開始）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:30:00',
            'rounded_time' => $dateString.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->late_minutes); // 30分遅刻
        $this->assertEquals(60, $summary->overtime_minutes, '終業後0 + 早出0 + 休憩不足60 = 60分');
        $this->assertEquals('09:00:00', $summary->scheduled_start_time);
        $this->assertEquals('18:00:00', $summary->scheduled_end_time);
    }

    /**
     * @test
     */
    public function aggregate_by_user_handles_cross_day_shift(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');
        $nextDateString = $targetDate->addDay()->format('Y-m-d');

        // 勤務開始（22:00）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 22:00:00',
            'rounded_time' => $dateString.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 勤務終了（翌日02:00）- 日付越え終了
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDateString.' 02:00:00',
            'rounded_time' => $nextDateString.' 02:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertTrue($summary->is_cross_day);
        $this->assertEquals(240, $summary->work_minutes); // 4時間 = 240分
        $this->assertGreaterThan(0, $summary->night_minutes); // 深夜時間が計算されている
    }

    /**
     * @test
     */
    public function aggregate_all_users_processes_all_companies_and_users(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // 2つ目の会社と所属ユーザーを作成
        $company2 = Company::factory()->create(['name' => 'Company 2']);
        $user2 = User::factory()
            ->forCompany($company2->id)
            ->create(['name' => 'User 2']);

        // 各ユーザーに打刻レコードを作成
        foreach ([
            [$this->company->id, $this->user->id],
            [$company2->id, $user2->id],
        ] as [$companyId, $userId]) {
            TimeRecord::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'record_type' => TimeRecordTypeEnum::WORK_START,
                'record_time' => $dateString.' 09:00:00',
                'rounded_time' => $dateString.' 09:00:00',
                'record_source' => RecordSourceEnum::AUTO,
            ]);

            TimeRecord::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'record_type' => TimeRecordTypeEnum::WORK_END,
                'record_time' => $dateString.' 18:00:00',
                'rounded_time' => $dateString.' 18:00:00',
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        }

        // Act
        $result = $this->service->aggregateAllUsers($targetDate);

        // Assert
        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['errors']);

        // 勤務実績が作成されていることを確認
        $this->assertDatabaseHas('daily_work_summaries', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $dateString,
        ]);

        $this->assertDatabaseHas('daily_work_summaries', [
            'company_id' => $company2->id,
            'user_id' => $user2->id,
            'work_date' => $dateString,
        ]);
    }

    /**
     * @test
     */
    public function aggregate_by_user_uses_shift_pattern_break_minutes_when_no_break_records(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // シフトパターンを作成（休憩60分設定）
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        // シフトを作成
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 休憩打刻なしで勤務打刻のみ
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes); // 休憩打刻なし＝休憩0分
        $this->assertEquals(540, $summary->net_work_minutes); // 540 - 0 = 540
    }

    /**
     * @test
     *
     * 会社デフォルトシフトパターンの休憩時間がフォールバックとして使用されること
     */
    public function aggregate_by_user_uses_company_default_shift_pattern_break_minutes_when_no_shift(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // 会社のデフォルトシフトパターンを作成（休憩60分）
        $defaultPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => 'デフォルト勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        // 会社にデフォルトシフトパターンを設定
        $this->company->update(['default_shift_pattern_id' => $defaultPattern->id]);

        // シフト未割当で休憩打刻なしの勤務打刻のみ
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes); // 休憩打刻なし＝休憩0分
        $this->assertEquals(540, $summary->net_work_minutes); // 540 - 0 = 540
    }

    /**
     * @test
     *
     * 深夜時間帯（22:00-23:00）の計算が昼休憩で不正に減算されないこと
     */
    public function aggregate_by_user_calculates_night_minutes_without_daytime_break_deduction(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // break_mode=2のシフトパターン（昼休憩12:00-13:00）
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '14:00',
            'end_time' => '23:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '18:00',
            'break_end' => '19:00',
        ]);

        // シフトを作成
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 14:00-23:00勤務（深夜帯22:00-23:00 = 60分、休憩18:00-19:00は深夜帯外）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 14:00:00',
            'rounded_time' => $dateString.' 14:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 23:00:00',
            'rounded_time' => $dateString.' 23:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        // 昼休憩は深夜帯(22:00-05:00)と重複しないので、深夜時間は60分のまま
        $this->assertEquals(60, $summary->night_minutes);
    }

    /**
     * @test
     *
     * break_mode=1（分数のみ設定）の夜勤シフトで按分フォールバックが動作すること
     */
    public function aggregate_by_user_uses_proportional_fallback_for_break_mode_1_night_shift(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');
        $nextDateString = $targetDate->addDay()->format('Y-m-d');

        // break_mode=1のシフトパターン（break_start/break_endなし）
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'work_minutes' => 420,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        // シフトを作成
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 22:00-06:00勤務（日付越え）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 22:00:00',
            'rounded_time' => $dateString.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDateString.' 06:00:00',
            'rounded_time' => $nextDateString.' 06:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        // 休憩打刻なし＝休憩0分、深夜帯(22:00-05:00)は420分がそのまま計上される
        $this->assertEquals(420, $summary->night_minutes);
    }

    /**
     * @test
     *
     * 早退時にシフトパターンの休憩時間帯が勤務時間外の場合、深夜時間から不正に減算されないこと
     */
    public function aggregate_by_user_clips_break_period_to_actual_work_hours_on_early_leave(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        // 夜勤シフト（18:00-06:00、休憩01:00-02:00）
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '18:00',
            'end_time' => '06:00',
            'work_minutes' => 660,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '01:00',
            'break_end' => '02:00',
        ]);

        // シフトを作成
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 18:00-23:00で早退（休憩01:00-02:00は勤務時間外）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 23:00:00',
            'rounded_time' => $dateString.' 23:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        // 勤務18:00-23:00で深夜帯は22:00-23:00=60分
        // 休憩01:00-02:00は勤務時間外なのでクリップされ、深夜時間は60分のまま
        $this->assertEquals(60, $summary->night_minutes);
    }

    /**
     * @test
     *
     * 日付跨ぎの休憩時間帯（例: 23:30-00:30）が正しく処理されること
     */
    public function aggregate_by_user_handles_cross_midnight_break_period(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');
        $nextDateString = $targetDate->addDay()->format('Y-m-d');

        // 夜勤シフト（22:00-06:00、休憩23:30-00:30）
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'work_minutes' => 420,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '23:30',
            'break_end' => '00:30',
        ]);

        // シフトを作成
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 22:00-06:00勤務（日付越え）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 22:00:00',
            'rounded_time' => $dateString.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDateString.' 06:00:00',
            'rounded_time' => $nextDateString.' 06:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        // 深夜帯(22:00-05:00)は420分、休憩23:30-00:30は全て深夜帯なので60分減算
        // 深夜時間 = 420 - 60 = 360分
        $this->assertEquals(360, $summary->night_minutes);
    }

    /**
     * @test
     *
     * 夜勤で翌日の休憩時間帯（例: 01:00-02:00）が正しく翌日に配置されること
     */
    public function aggregate_by_user_handles_after_midnight_break_period_for_night_shift(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');
        $nextDateString = $targetDate->addDay()->format('Y-m-d');

        // 夜勤シフト（22:00-06:00、休憩01:00-02:00）
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'work_minutes' => 420,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '01:00',
            'break_end' => '02:00',
        ]);

        // シフトを作成
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 22:00-06:00勤務（日付越え）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 22:00:00',
            'rounded_time' => $dateString.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDateString.' 06:00:00',
            'rounded_time' => $nextDateString.' 06:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        // 深夜帯(22:00-05:00)は420分、休憩01:00-02:00は深夜帯なので60分減算
        // 深夜時間 = 420 - 60 = 360分
        $this->assertEquals(360, $summary->night_minutes);
    }

    /**
     * @test
     */
    public function aggregate_by_user_subtracts_night_break_when_break_records_are_on_next_day(): void
    {
        // Arrange: 22:00出勤・翌10:45退勤、休憩は翌日02:00-03:30で打刻されている前提
        // (バグ2修正後の updateWorkTimes が翌日日付で保存することを想定したシミュレーション)
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');
        $nextDateString = $targetDate->addDay()->format('Y-m-d');

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 22:00:00',
            'rounded_time' => $dateString.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDateString.' 10:45:00',
            'rounded_time' => $nextDateString.' 10:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 休憩02:00-03:30は翌日として保存（バグ2修正後の正規化想定）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $nextDateString.' 02:00:00',
            'rounded_time' => $nextDateString.' 02:00:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $nextDateString.' 03:30:00',
            'rounded_time' => $nextDateString.' 03:30:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert: 深夜帯(22:00-翌05:00)=420分から休憩2:00-3:30の90分が減算 → 330分
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(330, $summary->night_minutes);
    }

    /**
     * @test
     */
    public function aggregate_by_user_calculates_early_leave_on_cross_day_shift(): void
    {
        // Arrange: 夜勤シフト 22:00-翌05:00、勤務 22:00-翌04:30（30分早退）
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');
        $nextDateString = $targetDate->addDay()->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '22:00',
            'end_time' => '05:00',
            'work_minutes' => 420,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 22:00:00',
            'rounded_time' => $dateString.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDateString.' 04:30:00',
            'rounded_time' => $nextDateString.' 04:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert: 30分早退（翌05:00 予定終了に対し翌04:30 退勤）
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->early_leave_minutes);
    }

    /**
     * @test
     */
    public function aggregate_by_user_returns_zero_early_leave_when_overtime_on_cross_day_shift(): void
    {
        // Arrange: 夜勤シフト 22:00-翌05:00、勤務 22:00-翌05:55（残業55分、早退なし）
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');
        $nextDateString = $targetDate->addDay()->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '22:00',
            'end_time' => '05:00',
            'work_minutes' => 420,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 22:00:00',
            'rounded_time' => $dateString.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDateString.' 05:55:00',
            'rounded_time' => $nextDateString.' 05:55:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert: 早退0分（残業なので）
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->early_leave_minutes);
        // 終業後55分(05:55-05:00) + 早出0 + 休憩不足60分(60-0) = 115分
        $this->assertEquals(115, $summary->overtime_minutes, '終業後55 + 早出0 + 休憩不足60 = 115分');
    }

    /**
     * @test
     */
    public function aggregate_by_user_keeps_late_and_overtime_when_net_work_meets_scheduled(): void
    {
        // Arrange: シフト07:30-16:30（所定8h）、丸め後打刻 07:45-17:00
        // 期待: 遅刻15分(7:45-7:30), 時間外=終業後30+休憩不足60=90分
        $targetDate = CarbonImmutable::parse('2025-03-02');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '早番',
            'start_time' => '07:30',
            'end_time' => '16:30',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 07:31:00',
            'rounded_time' => $dateString.' 07:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 17:11:00',
            'rounded_time' => $dateString.' 17:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(15, $summary->late_minutes, '遅刻は常にシフト差分で計上(7:45-7:30)');
        $this->assertEquals(0, $summary->early_leave_minutes);
        // 終業後30分(17:00-16:30) + 早出0 + 休憩不足60分(60-0) = 90分
        $this->assertEquals(90, $summary->overtime_minutes, '終業後30 + 早出0 + 休憩不足60 = 90分');
    }

    /**
     * @test
     */
    public function aggregate_by_user_keeps_late_when_net_work_short_of_scheduled(): void
    {
        // Arrange: シフト07:30-16:30（所定8h）、打刻 07:45-16:30
        // 実働 = 8:45 - 1:00 = 7:45 < 所定8:00 → クリップ発動しない
        $targetDate = CarbonImmutable::parse('2025-03-03');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '早番',
            'start_time' => '07:30',
            'end_time' => '16:30',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 07:45:00',
            'rounded_time' => $dateString.' 07:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 16:30:00',
            'rounded_time' => $dateString.' 16:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(15, $summary->late_minutes, '実働が所定に満たないので遅刻15分は維持');
        $this->assertEquals(0, $summary->early_leave_minutes);
    }

    /**
     * @test
     */
    public function aggregate_by_user_keeps_early_leave_when_net_work_short_of_scheduled(): void
    {
        // Arrange: シフト07:30-16:30（所定8h）、打刻 07:30-15:45
        // 実働 = 8:15 - 1:00 = 7:15 < 所定8:00 → クリップ発動しない
        $targetDate = CarbonImmutable::parse('2025-03-04');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '早番',
            'start_time' => '07:30',
            'end_time' => '16:30',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 07:30:00',
            'rounded_time' => $dateString.' 07:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 15:45:00',
            'rounded_time' => $dateString.' 15:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->late_minutes);
        $this->assertEquals(45, $summary->early_leave_minutes, '実働が所定に満たないので早退45分は維持');
    }

    // ========================================
    // rounded_time の使用に関するリグレッションテスト
    // 課題1: 遅刻・早退・時間外の計算は rounded_time で行う
    // ========================================

    /**
     * @test
     */
    public function aggregate_uses_rounded_time_for_late_calculation_when_rounded_differs_from_record(): void
    {
        // Arrange: record_time=09:32 だが rounded_time=09:35（5分単位切り上げ想定）の打刻に対し、
        // バッチが rounded_time を優先して遅刻を計算することを保証する。
        $targetDate = CarbonImmutable::parse('2025-03-10');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:32:00',
            'rounded_time' => $dateString.' 09:35:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert: 遅刻は rounded_time(09:35) - scheduled(09:00) = 35分
        // record_time(09:32) ベースの32分ではない
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(35, $summary->late_minutes, '遅刻はrounded_time基準で計算されるべき');
        $this->assertEquals(
            $dateString.' 09:35:00',
            CarbonImmutable::parse($summary->work_start)->format('Y-m-d H:i:s'),
            'work_startはrounded_timeの値が保存されるべき'
        );
    }

    /**
     * @test
     */
    public function aggregate_falls_back_to_record_time_when_rounded_time_is_null(): void
    {
        // Arrange: 古いレガシーデータ等で rounded_time が NULL のケース。
        // バッチは ?? で record_time にフォールバックして計算しなければならない。
        $targetDate = CarbonImmutable::parse('2025-03-11');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:20:00',
            'rounded_time' => null,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => null,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert: rounded_time が null でもクラッシュせず、record_time(09:20)で計算される
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(20, $summary->late_minutes, 'rounded_time がnullの場合はrecord_time基準');
    }

    /**
     * @test
     */
    public function aggregate_uses_rounded_time_for_break_calculation(): void
    {
        // Arrange: 休憩打刻も rounded_time が優先されることを確認
        $targetDate = CarbonImmutable::parse('2025-03-12');
        $dateString = $targetDate->format('Y-m-d');

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 休憩開始 record_time=12:03, rounded=12:05（切り上げ想定）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:03:00',
            'rounded_time' => $dateString.' 12:05:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 休憩終了 record_time=12:58, rounded=12:55（切り捨て想定）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 12:58:00',
            'rounded_time' => $dateString.' 12:55:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert: 休憩は rounded で 12:05〜12:55 = 50分（record_time基準なら55分）
        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(50, $summary->break_minutes, '休憩はrounded_time基準で計算されるべき');
    }

    // ========================================
    // 時間外計算方式変更のテスト
    // 時間外 = max(0, 退勤時刻 - シフト終業時刻)
    // ゼロクリップ廃止: 遅刻・早退は常にシフト差分で計上
    // ========================================

    /**
     * @test
     */
    public function overtime_is_calculated_from_clock_out_minus_shift_end_scenario_1(): void
    {
        // ユーザー実例: シフト07:30-16:30、打刻07:45→16:45 (15分丸め想定)
        $targetDate = CarbonImmutable::parse('2025-04-01');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '早番',
            'start_time' => '07:30',
            'end_time' => '16:30',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 07:31:00',
            'rounded_time' => $dateString.' 07:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 16:46:00',
            'rounded_time' => $dateString.' 16:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(540, $summary->net_work_minutes, '実働9:00(休憩0)');
        $this->assertEquals(15, $summary->late_minutes, '遅刻15分(7:45-7:30)');
        $this->assertEquals(0, $summary->early_leave_minutes);
        // 終業後15分(16:45-16:30) + 早出0 + 休憩不足60分(60-0) = 75分
        $this->assertEquals(75, $summary->overtime_minutes, '終業後15 + 早出0 + 休憩不足60 = 75分');
    }

    /**
     * @test
     */
    public function overtime_is_calculated_from_clock_out_minus_shift_end_scenario_2(): void
    {
        // ユーザー実例: シフト07:30-16:30、打刻10:00→18:45 (15分丸め想定)
        $targetDate = CarbonImmutable::parse('2025-04-03');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '早番',
            'start_time' => '07:30',
            'end_time' => '16:30',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:50:00',
            'rounded_time' => $dateString.' 10:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:45:00',
            'rounded_time' => $dateString.' 18:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(525, $summary->net_work_minutes, '実働8:45(休憩0)');
        $this->assertEquals(150, $summary->late_minutes, '遅刻2:30(10:00-7:30)');
        $this->assertEquals(0, $summary->early_leave_minutes);
        // 終業後135分(18:45-16:30) + 早出0 + 休憩不足60分(60-0) = 195分
        $this->assertEquals(195, $summary->overtime_minutes, '終業後135 + 早出0 + 休憩不足60 = 195分');
    }

    /**
     * @test
     */
    public function overtime_is_zero_when_clock_out_equals_shift_end(): void
    {
        $targetDate = CarbonImmutable::parse('2025-04-05');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(60, $summary->overtime_minutes, '休憩0で実働540 − 所定480 = 60分');
        $this->assertEquals(0, $summary->late_minutes);
        $this->assertEquals(0, $summary->early_leave_minutes);
    }

    /**
     * @test
     */
    public function overtime_includes_break_shortage_even_when_clock_out_before_shift_end(): void
    {
        $targetDate = CarbonImmutable::parse('2025-04-06');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 17:30:00',
            'rounded_time' => $dateString.' 17:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        // 終業後0 + 早出0 + 休憩不足60分(60-0) = 60分
        $this->assertEquals(60, $summary->overtime_minutes, '終業後0 + 早出0 + 休憩不足60 = 60分');
        $this->assertEquals(30, $summary->early_leave_minutes, '早退30分(18:00-17:30)');
    }

    /**
     * @test
     */
    public function overtime_is_calculated_when_clock_out_after_shift_end(): void
    {
        $targetDate = CarbonImmutable::parse('2025-04-07');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 19:30:00',
            'rounded_time' => $dateString.' 19:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(150, $summary->overtime_minutes, '休憩0で実働630 − 所定480 = 150分');
        $this->assertEquals(0, $summary->late_minutes);
        $this->assertEquals(0, $summary->early_leave_minutes);
    }

    /**
     * @test
     */
    public function late_and_overtime_can_coexist(): void
    {
        // シフト09:00-18:00、打刻09:30→19:00
        $targetDate = CarbonImmutable::parse('2025-04-08');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:30:00',
            'rounded_time' => $dateString.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 19:00:00',
            'rounded_time' => $dateString.' 19:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->late_minutes, '遅刻30分(9:30-9:00)');
        // 終業後60分(19:00-18:00) + 早出0 + 休憩不足60分(60-0) = 120分
        $this->assertEquals(120, $summary->overtime_minutes, '終業後60 + 早出0 + 休憩不足60 = 120分');
        $this->assertEquals(0, $summary->early_leave_minutes);
        $this->assertEquals(570, $summary->net_work_minutes, '実働9:30(休憩0)');
    }

    /**
     * @test
     */
    public function early_leave_with_break_shortage_overtime(): void
    {
        // シフト09:00-18:00、打刻09:00→16:00
        $targetDate = CarbonImmutable::parse('2025-04-09');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 16:00:00',
            'rounded_time' => $dateString.' 16:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(120, $summary->early_leave_minutes, '早退2:00(18:00-16:00)');
        // 終業後0 + 早出0 + 休憩不足60分(60-0) = 60分
        $this->assertEquals(60, $summary->overtime_minutes, '終業後0 + 早出0 + 休憩不足60 = 60分');
        $this->assertEquals(0, $summary->late_minutes);
    }

    /**
     * @test
     */
    public function overtime_is_zero_when_no_shift_assigned(): void
    {
        // シフトなし、打刻09:00→20:00
        $targetDate = CarbonImmutable::parse('2025-04-10');
        $dateString = $targetDate->format('Y-m-d');

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 20:00:00',
            'rounded_time' => $dateString.' 20:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->overtime_minutes, 'シフトなしなので時間外0');
        $this->assertEquals(0, $summary->late_minutes, 'シフトなしなので遅刻0');
        $this->assertEquals(0, $summary->early_leave_minutes, 'シフトなしなので早退0');
    }

    /**
     * @test
     */
    public function overtime_is_calculated_on_cross_day_shift(): void
    {
        // 夜勤22:00-翌06:00、打刻22:00→翌07:30
        $targetDate = CarbonImmutable::parse('2025-04-11');
        $dateString = $targetDate->format('Y-m-d');
        $nextDateString = $targetDate->addDay()->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'work_minutes' => 420,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 22:00:00',
            'rounded_time' => $dateString.' 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => $nextDateString.' 07:30:00',
            'rounded_time' => $nextDateString.' 07:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(150, $summary->overtime_minutes, '休憩0で実働570 − 所定420 = 150分');
        $this->assertEquals(0, $summary->late_minutes);
        $this->assertEquals(0, $summary->early_leave_minutes);
    }

    /**
     * @test
     */
    public function late_is_not_cleared_even_when_net_work_exceeds_scheduled(): void
    {
        // ゼロクリップ廃止確認: シフト09:00-18:00、打刻09:30→19:00
        // 遅刻30分は残る、時間外=終業後60+休憩不足60=120分
        $targetDate = CarbonImmutable::parse('2025-04-12');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:30:00',
            'rounded_time' => $dateString.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 19:00:00',
            'rounded_time' => $dateString.' 19:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(570, $summary->net_work_minutes, '実働9:30(休憩0) > 所定8:00');
        $this->assertEquals(30, $summary->late_minutes, 'ゼロクリップされず遅刻30分が残る');
        $this->assertEquals(0, $summary->early_leave_minutes);
        // 終業後60分(19:00-18:00) + 早出0 + 休憩不足60分(60-0) = 120分
        $this->assertEquals(120, $summary->overtime_minutes, '終業後60 + 早出0 + 休憩不足60 = 120分');
    }

    /**
     * @test
     */
    public function early_clock_in_before_shift_start_is_calculated_as_overtime(): void
    {
        // シフト09:00-18:00、打刻08:00→18:00（早出1時間）
        $targetDate = CarbonImmutable::parse('2025-04-15');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 08:00:00',
            'rounded_time' => $dateString.' 08:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(120, $summary->overtime_minutes, '休憩0で実働600 − 所定480 = 120分');
        $this->assertEquals(0, $summary->late_minutes, '早出なので遅刻なし');
        $this->assertEquals(0, $summary->early_leave_minutes, 'シフト終了時刻に退勤なので早退なし');
    }

    /**
     * @test
     */
    public function early_clock_in_and_late_clock_out_both_calculated_as_overtime(): void
    {
        // シフト09:00-18:00、打刻08:00→19:00（早出1時間+残業1時間=計2時間）
        $targetDate = CarbonImmutable::parse('2025-04-16');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 08:00:00',
            'rounded_time' => $dateString.' 08:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 19:00:00',
            'rounded_time' => $dateString.' 19:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(180, $summary->overtime_minutes, '休憩0で実働660 − 所定480 = 180分');
        $this->assertEquals(0, $summary->late_minutes);
        $this->assertEquals(0, $summary->early_leave_minutes);
    }

    /**
     * @test
     */
    public function exact_shift_start_clock_in_results_in_zero_early_overtime(): void
    {
        // シフト09:00-18:00、打刻09:00→18:00（定時出勤、定時退勤）
        $targetDate = CarbonImmutable::parse('2025-04-17');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(60, $summary->overtime_minutes, '休憩0で実働540 − 所定480 = 60分');
        $this->assertEquals(0, $summary->late_minutes);
        $this->assertEquals(0, $summary->early_leave_minutes);
    }

    // ========================================
    // 打刻修正後の休憩フォールバック制御テスト
    // ========================================

    /**
     * @test
     *
     * 申請修正（REQUEST）された勤務打刻がある場合、休憩打刻なしでもシフトパターンの休憩時間にフォールバックしないこと
     */
    public function aggregate_by_user_does_not_fallback_to_shift_break_when_work_records_are_request_source(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 申請修正された勤務打刻（休憩打刻なし）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '修正済み打刻では休憩フォールバックしない');
        $this->assertEquals(540, $summary->net_work_minutes, '休憩0なので9時間そのまま');
    }

    /**
     * @test
     *
     * 手動修正（MANUAL）された勤務打刻がある場合、休憩打刻なしでもシフトパターンの休憩時間にフォールバックしないこと
     */
    public function aggregate_by_user_does_not_fallback_to_shift_break_when_work_records_are_manual_source(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 手動修正された勤務打刻（休憩打刻なし）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $this->assertEquals('created', $result);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '手動修正済み打刻では休憩フォールバックしない');
    }

    /**
     * @test
     *
     * 混合ソース（WORK_START=AUTO, WORK_END=REQUEST）でも1つでも非AUTOがあればフォールバックしないこと
     */
    public function aggregate_by_user_does_not_fallback_to_shift_break_when_mixed_sources_include_non_auto(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // WORK_START=AUTO, WORK_END=REQUEST（修正は退勤のみ）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $result = $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '1つでも非AUTO打刻があれば休憩フォールバックしない');
    }

    /**
     * @test
     *
     * 修正済み打刻でも明示的な休憩打刻がある場合は正しく休憩を計算すること
     */
    public function aggregate_by_user_calculates_break_from_records_even_when_work_records_are_modified(): void
    {
        // Arrange
        $targetDate = CarbonImmutable::parse('2025-01-15');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 申請修正された勤務打刻 + 明示的な休憩打刻あり
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:00:00',
            'rounded_time' => $dateString.' 12:00:00',
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 12:30:00',
            'rounded_time' => $dateString.' 12:30:00',
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        // Act
        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->break_minutes, '明示的な休憩打刻から30分計算');
        $this->assertEquals(510, $summary->net_work_minutes, '540 - 30 = 510');
    }

    // ============================================================
    // 時間外 = 実労働 − 所定労働 （休憩短縮分も含む）
    // ============================================================

    /**
     * @test
     *
     * 例1: シフト9:00-18:00 休1H (所定8H) / 実績9:00-18:00 休0H → 時間外1H
     */
    public function overtime_counts_break_shortage_when_no_break_taken(): void
    {
        $targetDate = CarbonImmutable::parse('2025-05-01');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 手動修正で出退勤打刻 → 休憩打刻なし = 休憩0分として扱う
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '休憩0分');
        $this->assertEquals(540, $summary->net_work_minutes, '実労働9H');
        $this->assertEquals(60, $summary->overtime_minutes, '時間外 = 9H − 8H = 60分');
    }

    /**
     * @test
     *
     * 例2: シフト9:00-18:00 休1H (所定8H) / 実績9:00-18:15 休0.5H → 時間外0.75H
     */
    public function overtime_counts_partial_break_shortage(): void
    {
        $targetDate = CarbonImmutable::parse('2025-05-02');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:15:00',
            'rounded_time' => $dateString.' 18:15:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 実休憩30分
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:00:00',
            'rounded_time' => $dateString.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 12:30:00',
            'rounded_time' => $dateString.' 12:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->break_minutes, '休憩30分');
        $this->assertEquals(525, $summary->net_work_minutes, '実労働8:45');
        $this->assertEquals(45, $summary->overtime_minutes, '時間外 = 8:45 − 8:00 = 45分');
    }

    /**
     * @test
     *
     * 例3: シフト9:00-17:00 休1H (所定7H) / 実績9:00-18:00 休0H → 時間外2H
     */
    public function overtime_combines_shift_end_overrun_and_break_shortage(): void
    {
        $targetDate = CarbonImmutable::parse('2025-05-03');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '短時間',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'work_minutes' => 420,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 手動修正(MANUAL)で出退勤打刻 → 休憩打刻なし = 休憩0分として扱う
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '休憩0分');
        $this->assertEquals(540, $summary->net_work_minutes, '実労働9H');
        $this->assertEquals(120, $summary->overtime_minutes, '時間外 = 9H − 7H = 120分');
    }

    /**
     * @test
     *
     * 例4: シフト9:00-17:00 休1H (所定7H) / 実績8:45-18:00 休0.75H → 時間外1.5H
     */
    public function overtime_combines_early_start_late_end_and_break_shortage(): void
    {
        $targetDate = CarbonImmutable::parse('2025-05-04');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '短時間',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'work_minutes' => 420,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 08:45:00',
            'rounded_time' => $dateString.' 08:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 休憩45分
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:00:00',
            'rounded_time' => $dateString.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 12:45:00',
            'rounded_time' => $dateString.' 12:45:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(45, $summary->break_minutes, '休憩45分');
        $this->assertEquals(510, $summary->net_work_minutes, '実労働8:30');
        $this->assertEquals(90, $summary->overtime_minutes, '時間外 = 8:30 − 7:00 = 90分');
    }

    // ============================================================
    // 遅刻 + 休憩未取得 の組み合わせテスト
    // ============================================================

    /**
     * @test
     *
     * シフト9:00-18:00 休1H(所定8H) / 実績10:00-18:00 休0H
     * 遅刻1H、休憩未取得だが実働8H=所定8H → 時間外0
     */
    public function late_with_no_break_results_in_break_shortage_overtime(): void
    {
        $targetDate = CarbonImmutable::parse('2025-06-01');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 10:00:00',
            'rounded_time' => $dateString.' 10:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '休憩打刻なし = 休憩0分');
        $this->assertEquals(480, $summary->net_work_minutes, '実労働8H');
        $this->assertEquals(60, $summary->late_minutes, '遅刻1H');
        // 終業後0 + 早出0 + 休憩不足60分(60-0) = 60分
        $this->assertEquals(60, $summary->overtime_minutes, '終業後0 + 早出0 + 休憩不足60 = 60分');
    }

    /**
     * @test
     *
     * シフト9:00-17:00 休1H(所定7H) / 実績9:00-17:00 休0H
     * 定時出退勤、休憩未取得 → 実働8H、所定7H、時間外1H
     */
    public function on_time_with_no_break_counts_break_shortage_as_overtime(): void
    {
        $targetDate = CarbonImmutable::parse('2025-06-02');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '短時間',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'work_minutes' => 420,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 17:00:00',
            'rounded_time' => $dateString.' 17:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '休憩0分');
        $this->assertEquals(480, $summary->net_work_minutes, '実労働8H');
        $this->assertEquals(0, $summary->late_minutes, '遅刻なし');
        $this->assertEquals(60, $summary->overtime_minutes, '時間外 = 8H − 7H = 1H');
    }

    /**
     * @test
     *
     * シフト9:00-18:00 休1H(所定8H) / 実績9:00-18:00 休1H
     * 正常勤務 → 時間外0
     */
    public function normal_shift_with_full_break_results_in_zero_overtime(): void
    {
        $targetDate = CarbonImmutable::parse('2025-06-03');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:00:00',
            'rounded_time' => $dateString.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 13:00:00',
            'rounded_time' => $dateString.' 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(60, $summary->break_minutes, '休憩1H');
        $this->assertEquals(480, $summary->net_work_minutes, '実労働8H');
        $this->assertEquals(0, $summary->late_minutes, '遅刻なし');
        $this->assertEquals(0, $summary->overtime_minutes, '実労働8H = 所定8H → 時間外0');
    }

    /**
     * @test
     *
     * シフト9:00-18:00 休1H(所定8H) / 実績9:00-19:00 休0H
     * 残業1H + 休憩未取得1H → 実働10H、時間外2H
     */
    public function overtime_with_late_end_and_no_break(): void
    {
        $targetDate = CarbonImmutable::parse('2025-06-04');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 19:00:00',
            'rounded_time' => $dateString.' 19:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '休憩0分');
        $this->assertEquals(600, $summary->net_work_minutes, '実労働10H');
        $this->assertEquals(0, $summary->late_minutes, '遅刻なし');
        $this->assertEquals(120, $summary->overtime_minutes, '時間外 = 10H − 8H = 2H');
    }

    /**
     * @test
     *
     * シフト9:00-18:00 休1H(所定8H) / 実績9:00-18:00 休0.5H
     * 休憩30分不足 → 実働8.5H、時間外0.5H
     */
    public function half_break_taken_results_in_half_hour_overtime(): void
    {
        $targetDate = CarbonImmutable::parse('2025-06-05');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:00:00',
            'rounded_time' => $dateString.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 12:30:00',
            'rounded_time' => $dateString.' 12:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->break_minutes, '休憩30分');
        $this->assertEquals(510, $summary->net_work_minutes, '実労働8:30');
        $this->assertEquals(0, $summary->late_minutes, '遅刻なし');
        $this->assertEquals(30, $summary->overtime_minutes, '時間外 = 8:30 − 8:00 = 30分');
    }

    // ============================================================
    // 遅刻と残業が相殺されないことの検証
    // ============================================================

    /**
     * @test
     *
     * シフト9:00-18:00 休1H / 実績9:30-18:30 休1H
     * 遅刻0:30 + 終業後残業0:30 + 休憩不足0 → 時間外0:30（遅刻と相殺されない）
     */
    public function late_and_overtime_are_not_offset(): void
    {
        $targetDate = CarbonImmutable::parse('2025-07-01');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:30:00',
            'rounded_time' => $dateString.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:30:00',
            'rounded_time' => $dateString.' 18:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:00:00',
            'rounded_time' => $dateString.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 13:00:00',
            'rounded_time' => $dateString.' 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->late_minutes, '遅刻30分');
        $this->assertEquals(30, $summary->overtime_minutes, '終業後30分（遅刻と相殺されない）');
    }

    /**
     * @test
     *
     * シフト9:00-18:00 休1H / 実績9:30-18:30 休0.5H
     * 遅刻0:30 + 終業後残業0:30 + 休憩不足0:30 → 時間外1:00
     */
    public function late_overtime_and_break_shortage_all_counted(): void
    {
        $targetDate = CarbonImmutable::parse('2025-07-02');
        $dateString = $targetDate->format('Y-m-d');

        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $dateString,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:30:00',
            'rounded_time' => $dateString.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:30:00',
            'rounded_time' => $dateString.' 18:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $dateString.' 12:00:00',
            'rounded_time' => $dateString.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $dateString.' 12:30:00',
            'rounded_time' => $dateString.' 12:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->service->aggregateByUser($this->company, $this->user, $targetDate);

        $summary = DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $dateString)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->late_minutes, '遅刻30分');
        $this->assertEquals(60, $summary->overtime_minutes, '終業後30分 + 休憩不足30分 = 60分');
    }
}

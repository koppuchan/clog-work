<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Exceptions\BusinessException;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\StampService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * StampService の勤務状態判定（特に日跨ぎシナリオ）に関するテスト
 *
 * 課題3: 退勤後も休憩打刻ができるバグの対策。
 * 「最新のWORK_START以降のシーケンス」で状態を判定するロジックを保証する。
 */
class StampServiceTest extends TestCase
{
    use DatabaseTransactions;

    private StampService $service;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StampService::class);

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
    public function break_start_is_blocked_after_clock_out_added_for_previous_day(): void
    {
        // Arrange: 課題3の核心シナリオ
        // 1) 1/15 09:00 出勤（退勤忘れ）
        // 2) 1/16 09:00 出勤（今日の出勤）
        // 3) 1/16 10:00 1/15 18:00の退勤を後追い追加
        // → 今日の最新レコードは WORK_START のままだが、最新WORK_START(1/16 09:00)以降に
        //    WORK_END_NEXT_DAY や WORK_END があれば退勤済み扱い
        $today = CarbonImmutable::parse('2025-01-16 11:00:00');
        CarbonImmutable::setTestNow($today);

        // 前日の出勤
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-15 09:00:00',
            'rounded_time' => '2025-01-15 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 今日の出勤
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-16 09:00:00',
            'rounded_time' => '2025-01-16 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 今日の退勤も既に打刻済み（管理者or本人が10:00に追加）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2025-01-16 10:00:00',
            'rounded_time' => '2025-01-16 10:00:00',
            'record_source' => RecordSourceEnum::MANUAL,
        ]);

        // Act & Assert: 11:00に休憩開始しようとしたら拒否される
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('出勤していません');

        $this->service->breakStart($this->company->id, $this->user->id);

        CarbonImmutable::setTestNow();
    }

    /**
     * @test
     */
    public function get_current_status_reflects_clock_out_after_today_clock_in(): void
    {
        // Arrange: 今日 出勤→退勤の通常パターン
        $now = CarbonImmutable::parse('2025-01-16 11:00:00');
        CarbonImmutable::setTestNow($now);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-16 09:00:00',
            'rounded_time' => '2025-01-16 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2025-01-16 10:30:00',
            'rounded_time' => '2025-01-16 10:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $status = $this->service->getCurrentStatus($this->company->id, $this->user->id);

        // Assert
        $this->assertFalse($status['isWorking'], '退勤済みなのでisWorkingはfalse');
        $this->assertFalse($status['isOnBreak']);
        $this->assertTrue($status['hasClockInToday']);
        $this->assertTrue($status['hasClockOutToday']);

        CarbonImmutable::setTestNow();
    }

    /**
     * @test
     */
    public function get_current_status_handles_cross_day_work_correctly(): void
    {
        // Arrange: 夜勤 - 1/15 22:00 出勤、1/16 06:00 はまだ勤務中
        $now = CarbonImmutable::parse('2025-01-16 03:00:00');
        CarbonImmutable::setTestNow($now);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-15 22:00:00',
            'rounded_time' => '2025-01-15 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $status = $this->service->getCurrentStatus($this->company->id, $this->user->id);

        // Assert: 前日出勤・退勤なしなので継続勤務中
        $this->assertTrue($status['isWorking'], '前日からの継続勤務中');
        $this->assertFalse($status['isOnBreak']);
        $this->assertEquals('22:00', $status['clockInTime']);

        CarbonImmutable::setTestNow();
    }

    /**
     * @test
     */
    public function get_current_status_returns_not_working_after_cross_day_clock_out(): void
    {
        // Arrange: 夜勤 1/15 22:00 出勤 → 1/16 06:00 退勤(WORK_END_NEXT_DAY)
        $now = CarbonImmutable::parse('2025-01-16 09:00:00');
        CarbonImmutable::setTestNow($now);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-15 22:00:00',
            'rounded_time' => '2025-01-15 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END_NEXT_DAY,
            'record_time' => '2025-01-16 06:00:00',
            'rounded_time' => '2025-01-16 06:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $status = $this->service->getCurrentStatus($this->company->id, $this->user->id);

        // Assert: 退勤済み
        $this->assertFalse($status['isWorking']);
        $this->assertFalse($status['isOnBreak']);
        // hasClockInTodayは「当日(1/16)の出勤打刻」なのでfalse
        $this->assertFalse($status['hasClockInToday']);
        // 当日(1/16)にWORK_END_NEXT_DAYがあるのでhasClockOutTodayはtrue
        $this->assertTrue($status['hasClockOutToday']);

        CarbonImmutable::setTestNow();
    }

    /**
     * @test
     */
    public function break_start_succeeds_for_normal_working_user(): void
    {
        // Arrange: 通常の勤務中ユーザー
        $now = CarbonImmutable::parse('2025-01-16 12:00:00');
        CarbonImmutable::setTestNow($now);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-16 09:00:00',
            'rounded_time' => '2025-01-16 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $record = $this->service->breakStart($this->company->id, $this->user->id);

        // Assert
        $this->assertEquals(TimeRecordTypeEnum::BREAK_START, $record->record_type);

        CarbonImmutable::setTestNow();
    }

    /**
     * @test
     */
    public function clock_out_is_blocked_while_on_break(): void
    {
        // Arrange: 休憩開始打刻済みで休憩中
        $now = CarbonImmutable::parse('2025-01-16 12:00:00');
        CarbonImmutable::setTestNow($now);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-16 09:00:00',
            'rounded_time' => '2025-01-16 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => '2025-01-16 12:00:00',
            'rounded_time' => '2025-01-16 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act & Assert: 休憩終了せずに退勤しようとしたら拒否される
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('休憩中は退勤できません');

        $this->service->clockOut($this->company->id, $this->user->id);

        CarbonImmutable::setTestNow();
    }

    /**
     * @test
     */
    public function clock_in_is_blocked_when_already_working_from_yesterday(): void
    {
        // Arrange: 前日からの継続勤務中
        $now = CarbonImmutable::parse('2025-01-16 09:00:00');
        CarbonImmutable::setTestNow($now);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-15 22:00:00',
            'rounded_time' => '2025-01-15 22:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act & Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('既に出勤中');

        $this->service->clockIn($this->company->id, $this->user->id);

        CarbonImmutable::setTestNow();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TimeRecordTypeEnum;
use App\Repositories\Contracts\CompanyShiftRoundingSettingRepositoryInterface;
use App\Services\TimeRoundingService;
use Carbon\CarbonImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TimeRoundingServiceTest extends TestCase
{
    private TimeRoundingService $service;

    private MockInterface $roundingSettingRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roundingSettingRepository = Mockery::mock(CompanyShiftRoundingSettingRepositoryInterface::class);
        $this->service = new TimeRoundingService($this->roundingSettingRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function round_time_returns_original_when_no_setting(): void
    {
        // Arrange
        $this->roundingSettingRepository
            ->shouldReceive('getRoundingMinutes')
            ->with(1)
            ->andReturn(null);

        $time = CarbonImmutable::parse('2025-01-15 08:47:00');

        // Act
        $result = $this->service->roundTime(1, $time, TimeRecordTypeEnum::WORK_START);

        // Assert
        $this->assertEquals('08:47:00', $result->format('H:i:s'));
    }

    /**
     * @test
     */
    public function round_time_returns_original_when_unit_is_one(): void
    {
        // Arrange
        $this->roundingSettingRepository
            ->shouldReceive('getRoundingMinutes')
            ->with(1)
            ->andReturn(1);

        $time = CarbonImmutable::parse('2025-01-15 08:47:00');

        // Act
        $result = $this->service->roundTime(1, $time, TimeRecordTypeEnum::WORK_START);

        // Assert
        $this->assertEquals('08:47:00', $result->format('H:i:s'));
    }

    /**
     * @test
     *
     * @dataProvider roundUpProvider
     */
    public function round_up_rounds_to_next_interval(string $inputTime, int $unitMinutes, string $expectedTime): void
    {
        // Arrange
        $time = CarbonImmutable::parse("2025-01-15 {$inputTime}");

        // Act
        $result = $this->service->roundUp($time, $unitMinutes);

        // Assert
        $this->assertEquals($expectedTime, $result->format('H:i:s'));
    }

    public static function roundUpProvider(): array
    {
        return [
            // 5分単位
            '8:47 with 5min -> 8:50' => ['08:47:00', 5, '08:50:00'],
            '8:50 with 5min -> 8:50 (no change)' => ['08:50:00', 5, '08:50:00'],
            '8:51 with 5min -> 8:55' => ['08:51:00', 5, '08:55:00'],
            '8:00 with 5min -> 8:00 (no change)' => ['08:00:00', 5, '08:00:00'],
            '8:01 with 5min -> 8:05' => ['08:01:00', 5, '08:05:00'],

            // 10分単位
            '8:47 with 10min -> 8:50' => ['08:47:00', 10, '08:50:00'],
            '8:50 with 10min -> 8:50 (no change)' => ['08:50:00', 10, '08:50:00'],
            '8:51 with 10min -> 9:00' => ['08:51:00', 10, '09:00:00'],

            // 15分単位
            '9:07 with 15min -> 9:15' => ['09:07:00', 15, '09:15:00'],
            '9:15 with 15min -> 9:15 (no change)' => ['09:15:00', 15, '09:15:00'],
            '9:16 with 15min -> 9:30' => ['09:16:00', 15, '09:30:00'],

            // 30分単位
            '8:15 with 30min -> 8:30' => ['08:15:00', 30, '08:30:00'],
            '8:30 with 30min -> 8:30 (no change)' => ['08:30:00', 30, '08:30:00'],
            '8:31 with 30min -> 9:00' => ['08:31:00', 30, '09:00:00'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider roundDownProvider
     */
    public function round_down_rounds_to_previous_interval(string $inputTime, int $unitMinutes, string $expectedTime): void
    {
        // Arrange
        $time = CarbonImmutable::parse("2025-01-15 {$inputTime}");

        // Act
        $result = $this->service->roundDown($time, $unitMinutes);

        // Assert
        $this->assertEquals($expectedTime, $result->format('H:i:s'));
    }

    public static function roundDownProvider(): array
    {
        return [
            // 5分単位
            '17:13 with 5min -> 17:10' => ['17:13:00', 5, '17:10:00'],
            '17:10 with 5min -> 17:10 (no change)' => ['17:10:00', 5, '17:10:00'],
            '17:14 with 5min -> 17:10' => ['17:14:00', 5, '17:10:00'],
            '17:15 with 5min -> 17:15 (no change)' => ['17:15:00', 5, '17:15:00'],

            // 10分単位
            '17:19 with 10min -> 17:10' => ['17:19:00', 10, '17:10:00'],
            '17:20 with 10min -> 17:20 (no change)' => ['17:20:00', 10, '17:20:00'],

            // 15分単位
            '17:22 with 15min -> 17:15' => ['17:22:00', 15, '17:15:00'],
            '17:29 with 15min -> 17:15' => ['17:29:00', 15, '17:15:00'],
            '17:30 with 15min -> 17:30 (no change)' => ['17:30:00', 15, '17:30:00'],

            // 30分単位
            '17:45 with 30min -> 17:30' => ['17:45:00', 30, '17:30:00'],
            '17:59 with 30min -> 17:30' => ['17:59:00', 30, '17:30:00'],
            '18:00 with 30min -> 18:00 (no change)' => ['18:00:00', 30, '18:00:00'],
        ];
    }

    /**
     * @test
     */
    public function round_up_handles_midnight_crossing(): void
    {
        // Arrange
        $time = CarbonImmutable::parse('2025-01-15 23:58:00');

        // Act
        $result = $this->service->roundUp($time, 5);

        // Assert
        $this->assertEquals('2025-01-16', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    /**
     * @test
     */
    public function round_time_applies_round_up_for_work_start(): void
    {
        // Arrange
        $this->roundingSettingRepository
            ->shouldReceive('getRoundingMinutes')
            ->with(1)
            ->andReturn(5);

        $time = CarbonImmutable::parse('2025-01-15 08:47:00');

        // Act
        $result = $this->service->roundTime(1, $time, TimeRecordTypeEnum::WORK_START);

        // Assert
        $this->assertEquals('08:50:00', $result->format('H:i:s'));
    }

    /**
     * @test
     */
    public function round_time_applies_round_down_for_work_end(): void
    {
        // Arrange
        $this->roundingSettingRepository
            ->shouldReceive('getRoundingMinutes')
            ->with(1)
            ->andReturn(5);

        $time = CarbonImmutable::parse('2025-01-15 17:13:00');

        // Act
        $result = $this->service->roundTime(1, $time, TimeRecordTypeEnum::WORK_END);

        // Assert
        $this->assertEquals('17:10:00', $result->format('H:i:s'));
    }

    /**
     * @test
     */
    public function round_time_applies_round_down_for_work_end_next_day(): void
    {
        // Arrange
        $this->roundingSettingRepository
            ->shouldReceive('getRoundingMinutes')
            ->with(1)
            ->andReturn(5);

        $time = CarbonImmutable::parse('2025-01-16 01:13:00');

        // Act
        $result = $this->service->roundTime(1, $time, TimeRecordTypeEnum::WORK_END_NEXT_DAY);

        // Assert
        $this->assertEquals('01:10:00', $result->format('H:i:s'));
    }

    /**
     * @test
     */
    public function round_time_applies_round_down_for_break_start(): void
    {
        // Arrange
        $this->roundingSettingRepository
            ->shouldReceive('getRoundingMinutes')
            ->with(1)
            ->andReturn(5);

        $time = CarbonImmutable::parse('2025-01-15 12:03:00');

        // Act
        $result = $this->service->roundTime(1, $time, TimeRecordTypeEnum::BREAK_START);

        // Assert
        $this->assertEquals('12:00:00', $result->format('H:i:s'));
    }

    /**
     * @test
     */
    public function round_time_applies_round_up_for_break_end(): void
    {
        // Arrange
        $this->roundingSettingRepository
            ->shouldReceive('getRoundingMinutes')
            ->with(1)
            ->andReturn(5);

        $time = CarbonImmutable::parse('2025-01-15 12:57:00');

        // Act
        $result = $this->service->roundTime(1, $time, TimeRecordTypeEnum::BREAK_END);

        // Assert
        $this->assertEquals('13:00:00', $result->format('H:i:s'));
    }
}

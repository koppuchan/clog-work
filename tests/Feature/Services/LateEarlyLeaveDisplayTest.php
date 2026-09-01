<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Services\LateEarlyLeaveDisplay;
use Tests\TestCase;

/**
 * 承認済み休暇の日に遅刻早退を出さないことの検証。
 *
 * 半日有給で午後から出勤した場合、始業時刻との差をそのまま遅刻として
 * 出してしまうと、休暇を取ったこと自体が遅刻として集計されてしまう。
 */
class LateEarlyLeaveDisplayTest extends TestCase
{
    private LateEarlyLeaveDisplay $display;

    protected function setUp(): void
    {
        parent::setUp();

        $this->display = app(LateEarlyLeaveDisplay::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function summary(array $attributes): object
    {
        return (object) array_merge([
            'leave_type' => null,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
        ], $attributes);
    }

    /**
     * @test
     */
    public function 休暇のない日は遅刻早退をそのまま出す(): void
    {
        $summary = $this->summary(['late_minutes' => 30, 'early_leave_minutes' => 15]);

        $this->assertTrue($this->display->shouldShow($summary));
        $this->assertSame(30, $this->display->lateMinutes($summary));
        $this->assertSame(15, $this->display->earlyLeaveMinutes($summary));
    }

    /**
     * @test
     */
    public function 半日有給の日は遅刻を出さない(): void
    {
        // 9時始業のシフトに13時出勤でも、休暇なので遅刻としない
        $summary = $this->summary([
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'late_minutes' => 240,
        ]);

        $this->assertFalse($this->display->shouldShow($summary));
        $this->assertSame(0, $this->display->lateMinutes($summary));
    }

    /**
     * @test
     */
    public function 特別休暇の日も出さない(): void
    {
        $summary = $this->summary([
            'leave_type' => LeaveTypeEnum::SPECIAL_LEAVE,
            'early_leave_minutes' => 120,
        ]);

        $this->assertSame(0, $this->display->earlyLeaveMinutes($summary));
    }

    /**
     * @test
     */
    public function 欠勤の日も出さない(): void
    {
        $summary = $this->summary([
            'leave_type' => LeaveTypeEnum::ABSENCE,
            'late_minutes' => 60,
        ]);

        $this->assertSame(0, $this->display->lateMinutes($summary));
    }

    /**
     * @test
     */
    public function 勤務実績がない日は出さない(): void
    {
        $this->assertFalse($this->display->shouldShow(null));
        $this->assertSame(0, $this->display->lateMinutes(null));
        $this->assertSame(0, $this->display->earlyLeaveMinutes(null));
    }
}

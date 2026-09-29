<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Services\DailyWorkSummaryService;
use Tests\TestCase;

/**
 * CSV出力の勤務区分の判定（task#71）。
 *
 * DailyWorkSummaryService::resolveWorkType は帳票（Excel）の
 * AttendanceExcelExportService::getWorkType と同じ基準で判定する
 * （WorkTypeExportTest.php 参照）。
 */
class CsvWorkTypeExportTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function workType(array $attributes, ?string $effectiveWorkStart): string
    {
        $summary = $attributes === [] ? null : (object) $attributes;

        $method = new \ReflectionMethod(DailyWorkSummaryService::class, 'resolveWorkType');

        return $method->invoke(
            app(DailyWorkSummaryService::class),
            $summary,
            $effectiveWorkStart,
        );
    }

    /**
     * @test
     */
    public function シフトあり_出退勤ありは出勤になる(): void
    {
        $this->assertSame('出勤', $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
        ], '09:00'));
    }

    /**
     * @test
     */
    public function シフトあり_出退勤なしは欠勤になる(): void
    {
        $this->assertSame('欠勤', $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
        ], null));
    }

    /**
     * @test
     */
    public function シフトなし_出退勤ありは休出になる(): void
    {
        $this->assertSame('休出', $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => null,
        ], '09:00'));
    }

    /**
     * @test
     */
    public function シフトなし_出退勤なしは休日になる(): void
    {
        $this->assertSame('休日', $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => null,
        ], null));
    }

    /**
     * @test
     *
     * summaryのwork_startが無くても、呼び出し側が実打刻から渡した値
     * （effectiveWorkStart）があれば出勤と判定される（クライアント報告:
     * シフト・出退勤があるのに欠勤で出力される不具合の原因）。
     */
    public function summaryのwork_startが無くても渡された実打刻があれば出勤になる(): void
    {
        $this->assertSame('出勤', $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
            'work_start' => null,
        ], '08:58'));
    }

    /**
     * @test
     */
    public function 休暇種別があればシフト_出退勤の有無に関わらずそれを優先する(): void
    {
        $this->assertSame('特別休暇', $this->workType([
            'leave_type' => LeaveTypeEnum::SPECIAL_LEAVE,
            'scheduled_start_time' => '09:00',
        ], '09:00'));
    }

    /**
     * @test
     *
     * 「欠勤」の休暇種別だけは例外で、実打刻があれば優先しない
     * （WorkTypeExportTest.php参照）。
     */
    public function 欠勤の休暇種別があっても実打刻があれば出勤になる(): void
    {
        $this->assertSame('出勤', $this->workType([
            'leave_type' => LeaveTypeEnum::ABSENCE,
            'scheduled_start_time' => '09:00',
        ], '08:51'));
    }
}

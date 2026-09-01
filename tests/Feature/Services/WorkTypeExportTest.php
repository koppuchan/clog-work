<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\AttendanceExcelExportService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * 帳票・CSVの勤務区分の判定。
 *
 * 集計欄の欠勤日数は =COUNTIFS(B7:B37,"欠勤") でこの表記を数えるため、
 * 文字列がそのまま集計に効く。
 */
class WorkTypeExportTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function workType(array $attributes, string $date): string
    {
        $summary = $attributes === [] ? null : (object) $attributes;

        $method = new \ReflectionMethod(AttendanceExcelExportService::class, 'getWorkType');

        return $method->invoke(
            app(AttendanceExcelExportService::class),
            $summary,
            CarbonImmutable::parse($date),
        );
    }

    /**
     * @test
     */
    public function シフトがあり出退勤がなければ欠勤になる(): void
    {
        // 平日にシフトが割り当てられているのに打刻がなく、休暇の申請もない
        $type = $this->workType([
            'work_start' => null,
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
        ], '2026-06-24');

        $this->assertSame('欠勤', $type);
    }

    /**
     * @test
     */
    public function 出勤していれば出勤になる(): void
    {
        $type = $this->workType([
            'work_start' => CarbonImmutable::parse('2026-06-24 09:00'),
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
        ], '2026-06-24');

        $this->assertSame('出勤', $type);
    }

    /**
     * @test
     */
    public function シフトがない平日は欠勤にしない(): void
    {
        // もともと勤務予定がない日を欠勤として数えない
        $type = $this->workType([
            'work_start' => null,
            'leave_type' => null,
            'scheduled_start_time' => null,
        ], '2026-06-24');

        $this->assertSame('', $type);
    }

    /**
     * @test
     */
    public function 勤務実績そのものがない日は欠勤にしない(): void
    {
        $this->assertSame('', $this->workType([], '2026-06-24'));
    }

    /**
     * @test
     */
    public function 土日は打刻がなければ休日になる(): void
    {
        $type = $this->workType([
            'work_start' => null,
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
        ], '2026-06-27');

        $this->assertSame('休日', $type);
    }

    /**
     * @test
     */
    public function 土日に出勤していれば休出になる(): void
    {
        $type = $this->workType([
            'work_start' => CarbonImmutable::parse('2026-06-27 09:00'),
            'leave_type' => null,
            'scheduled_start_time' => null,
        ], '2026-06-27');

        $this->assertSame('休出', $type);
    }
}

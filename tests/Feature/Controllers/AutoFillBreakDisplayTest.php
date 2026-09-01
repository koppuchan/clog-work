<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Company;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 自動入力した休憩を画面で区別できることの検証。
 *
 * 打刻された休憩と、シフトから当てた休憩を見分けられないと、
 * 実際に休憩を取ったのかどうかが画面から判断できない。
 */
class AutoFillBreakDisplayTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create(['name' => '管理者', 'is_retired' => false]);
    }

    private function createShift(bool $autoFillBreak): void
    {
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_start' => '12:00',
            'break_end' => '13:00',
            'auto_fill_break' => $autoFillBreak,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'shift_date' => '2026-06-24',
            'shift_pattern_id' => $pattern->id,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shiftPropForDate(): ?array
    {
        $response = $this->actingAs($this->admin, 'admin')->get(
            '/admin/reports?start_date=2026-06-01&end_date=2026-06-30&user_id='.$this->admin->id
        );

        $response->assertStatus(200);

        $shifts = $response->viewData('page')['props']['shifts'] ?? [];

        return $shifts['2026-06-24'] ?? null;
    }

    /**
     * @test
     */
    public function 自動入力が有効なシフトはその旨を画面へ渡す(): void
    {
        $this->createShift(true);

        $shift = $this->shiftPropForDate();

        $this->assertNotNull($shift);
        $this->assertTrue($shift['auto_fill_break']);
        $this->assertSame('12:00', $shift['break_start']);
    }

    /**
     * @test
     */
    public function 自動入力が無効なシフトは区別されない(): void
    {
        $this->createShift(false);

        $shift = $this->shiftPropForDate();

        $this->assertNotNull($shift);
        $this->assertFalse($shift['auto_fill_break']);
    }
}

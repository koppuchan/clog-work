<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 課題4&5: 勤務実績画面のシフト情報に break_minutes / break_mode が
 * 含まれることを確認するテスト
 */
class WorkSummaryShiftsPropsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'name' => 'テスト会社',
        ]);

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create([
                'name' => '管理者',
                'employee_code' => '900001',
                'is_retired' => false,
            ]);

        $this->employee = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create([
                'name' => '一般スタッフ',
                'employee_code' => '900002',
                'is_retired' => false,
            ]);
    }

    /**
     * @test
     */
    public function shifts_props_include_break_minutes_for_break_mode_1(): void
    {
        // Arrange: break_mode=1（分数のみ）のシフトパターン
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '営業2',
            'start_time' => '10:00',
            'end_time' => '19:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            // break_mode=1なので break_start/break_end は NULL
            'break_start' => null,
            'break_end' => null,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'shift_date' => '2025-04-01',
            'shift_pattern_id' => $pattern->id,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get(
            '/admin/reports?user_id='.$this->employee->id
            .'&start_date=2025-04-01&end_date=2025-04-30'
        );

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Reports')
            ->where('shifts.2025-04-01.label', '10:00~19:00')
            ->where('shifts.2025-04-01.break_minutes', 60)
            ->where('shifts.2025-04-01.break_mode', 1)
            ->where('shifts.2025-04-01.break_start', null)
            ->where('shifts.2025-04-01.break_end', null)
        );
    }

    /**
     * @test
     */
    public function shifts_props_include_break_start_end_for_break_mode_2(): void
    {
        // Arrange: break_mode=2（時間帯指定）のシフトパターン
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '基本',
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
            'user_id' => $this->employee->id,
            'shift_date' => '2025-04-02',
            'shift_pattern_id' => $pattern->id,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get(
            '/admin/reports?user_id='.$this->employee->id
            .'&start_date=2025-04-01&end_date=2025-04-30'
        );

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Reports')
            ->where('shifts.2025-04-02.label', '09:00~18:00')
            ->where('shifts.2025-04-02.break_start', '12:00')
            ->where('shifts.2025-04-02.break_end', '13:00')
            ->where('shifts.2025-04-02.break_minutes', 60)
            ->where('shifts.2025-04-02.break_mode', 2)
        );
    }
}

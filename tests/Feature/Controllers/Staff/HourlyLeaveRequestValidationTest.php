<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Staff;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 時間有給申請が1時間単位（60分の倍数）になっているかの検証。
 *
 * 12:15-13:15（60分）はOK、12:30-13:15（45分）はNG。
 */
class HourlyLeaveRequestValidationTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['paid_leave_hourly' => true]);

        $this->staff = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create(['is_retired' => false]);
    }

    /**
     * @test
     */
    public function 一時間ちょうどの時間有給申請は成功する(): void
    {
        $response = $this->actingAs($this->staff, 'staff')->post('/staff/requests', [
            'target_date' => '2026-06-24',
            'type' => 'hourly-leave',
            'reason' => '通院のため',
            'start_time' => '12:15',
            'end_time' => '13:15',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('requests', [
            'company_id' => $this->company->id,
            'requested_by' => $this->staff->id,
            'target_date' => '2026-06-24',
        ]);
    }

    /**
     * @test
     */
    public function 四十五分の時間有給申請は警告になる(): void
    {
        $response = $this->actingAs($this->staff, 'staff')->post('/staff/requests', [
            'target_date' => '2026-06-24',
            'type' => 'hourly-leave',
            'reason' => '通院のため',
            'start_time' => '12:30',
            'end_time' => '13:15',
        ]);

        $response->assertSessionHasErrors(['end_time']);
        $this->assertDatabaseMissing('requests', [
            'company_id' => $this->company->id,
            'requested_by' => $this->staff->id,
            'target_date' => '2026-06-24',
        ]);
    }

    /**
     * @test
     *
     * 時間系ではない申請種別(残業申請)には1時間単位の制約をかけない
     */
    public function 残業申請は1時間単位でなくても成功する(): void
    {
        $response = $this->actingAs($this->staff, 'staff')->post('/staff/requests', [
            'target_date' => '2026-06-24',
            'type' => 'overtime',
            'reason' => '月次締め作業のため',
            'start_time' => '18:00',
            'end_time' => '18:45',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
    }
}

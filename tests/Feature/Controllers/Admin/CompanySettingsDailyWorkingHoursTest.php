<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 時間有給の計算に使う「1日あたりの所定労働時間」設定は、
 * 1時間単位（整数）のみ受け付ける。8.5時間のような小数点以下の
 * 入力はできないようにする。
 */
class CompanySettingsDailyWorkingHoursTest extends TestCase
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
            ->create(['is_retired' => false]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'companyName' => $this->company->name,
            'paidLeaveHourly' => true,
            'dailyWorkingHours' => 8,
        ], $overrides);
    }

    /**
     * @test
     */
    public function 整数の所定労働時間は保存できる(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', $this->payload(['dailyWorkingHours' => 7]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(7, $this->company->fresh()->daily_working_hours);
    }

    /**
     * @test
     */
    public function 小数点以下の所定労働時間は保存できない(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', $this->payload(['dailyWorkingHours' => 8.5]));

        $response->assertSessionHasErrors(['dailyWorkingHours']);
    }
}

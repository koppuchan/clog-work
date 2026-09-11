<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 「1日あたりの所定労働時間」設定は、時間単位有給を使う場合のみ
 * 1時間単位（整数）を受け付ける。8.5時間のような小数点以下の入力は
 * 時間単位有給を使う場合にはできないが、使わない場合はDBの精度
 * （小数第1位まで）の範囲で受け付ける。
 *
 * 以前は時間単位有給の利用有無にかかわらず常に整数のみを要求して
 * いたため、時間単位有給を使わない新規事業所でも7.5時間のような
 * 一般的な所定労働時間を設定できなかった
 * （No.49: 新規事業所で1時間単位でしか設定できないという報告）。
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
    public function 時間単位有給を使う場合は小数点以下の所定労働時間を保存できない(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', $this->payload(['paidLeaveHourly' => true, 'dailyWorkingHours' => 8.5]));

        $response->assertSessionHasErrors(['dailyWorkingHours']);
    }

    /**
     * @test
     */
    public function 時間単位有給を使わない場合は小数点以下の所定労働時間を保存できる(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', $this->payload(['paidLeaveHourly' => false, 'dailyWorkingHours' => 7.5]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(7.5, $this->company->fresh()->daily_working_hours);
    }

    /**
     * @test
     *
     * DBの精度（DECIMAL(4,1)）を超える小数第2位以下は、時間単位有給の
     * 利用有無にかかわらず受け付けない。
     */
    public function 小数第2位以下の所定労働時間は保存できない(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', $this->payload(['paidLeaveHourly' => false, 'dailyWorkingHours' => 7.55]));

        $response->assertSessionHasErrors(['dailyWorkingHours']);
    }
}

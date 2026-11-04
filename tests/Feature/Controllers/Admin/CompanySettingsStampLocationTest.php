<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 「スマホ打刻のときに位置情報を記録する」設定を、管理者が会社ごとに変更できること
 */
class CompanySettingsStampLocationTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->forCompany($this->company->id)->admin()->create(['is_retired' => false]);
    }

    /**
     * @test
     */
    public function 初期状態では位置情報を記録しない(): void
    {
        $this->assertFalse($this->company->fresh()->records_stamp_location);
    }

    /**
     * @test
     */
    public function 位置情報の記録をオンにして保存できる(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', ['companyName' => $this->company->name, 'recordsStampLocation' => true]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertTrue($this->company->fresh()->records_stamp_location);
    }

    /**
     * @test
     */
    public function 設定をオフに戻せる(): void
    {
        $this->company->forceFill(['records_stamp_location' => true])->save();

        $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', ['companyName' => $this->company->name, 'recordsStampLocation' => false])
            ->assertSessionDoesntHaveErrors();

        $this->assertFalse($this->company->fresh()->records_stamp_location);
    }

    /**
     * @test
     */
    public function 項目を送らない保存では設定を変えない(): void
    {
        $this->company->forceFill(['records_stamp_location' => true])->save();

        $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', ['companyName' => $this->company->name])
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue($this->company->fresh()->records_stamp_location);
    }

    /**
     * @test
     */
    public function 設定画面に現在の設定が渡される(): void
    {
        $this->company->forceFill(['records_stamp_location' => true])->save();

        $this->actingAs($this->admin, 'admin')->get('/admin/settings')->assertInertia(
            fn (Assert $page) => $page->component('Admin/Settings')->where('company.records_stamp_location', true)
        );
    }

    /**
     * @test
     */
    public function 他の会社の設定は変わらない(): void
    {
        $other = Company::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->put('/admin/settings', ['companyName' => $this->company->name, 'recordsStampLocation' => true])
            ->assertSessionDoesntHaveErrors();

        $this->assertFalse($other->fresh()->records_stamp_location);
    }
}

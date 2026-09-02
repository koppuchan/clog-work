<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * スーパー管理画面（SaaS運営者向け）の検証。
 *
 * 各事業所の管理者ではアクセスできず、運営者として明示的に指定された
 * ユーザーのみが利用できることを確認する。
 */
class SuperAdminTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $superAdmin;

    private User $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'company_code' => '930001',
            'name' => '運営テスト株式会社',
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => '運営 太郎',
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);
        $this->superAdmin->companies()->attach($this->company->id, ['is_primary' => true]);

        $this->companyAdmin = User::factory()->create([
            'name' => '事業所 管理者',
            'is_owner' => true,
            'email_verified_at' => now(),
        ]);
        $this->companyAdmin->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    /**
     * @test
     */
    public function 未認証ではスーパー管理画面にアクセスできない(): void
    {
        $this->get('/super-admin')->assertRedirect();
    }

    /**
     * @test
     */
    public function 事業所の管理者はスーパー管理画面にアクセスできない(): void
    {
        $this->actingAs($this->companyAdmin, 'admin')
            ->get('/super-admin')
            ->assertForbidden();
    }

    /**
     * @test
     */
    public function スーパー管理者はダッシュボードを表示できる(): void
    {
        $this->actingAs($this->superAdmin, 'admin')
            ->get('/super-admin')
            ->assertOk();
    }

    /**
     * @test
     */
    public function スーパー管理者は事業所一覧を表示できる(): void
    {
        $this->actingAs($this->superAdmin, 'admin')
            ->get('/super-admin/companies')
            ->assertOk();
    }

    /**
     * @test
     */
    public function スーパー管理者はユーザー一覧を表示できる(): void
    {
        $this->actingAs($this->superAdmin, 'admin')
            ->get('/super-admin/users')
            ->assertOk();
    }

    /**
     * @test
     */
    public function 事業所を作成すると管理者も同時に作成される(): void
    {
        // Act
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->post('/super-admin/companies', [
                'name' => '新設 株式会社',
                'owner_name' => '新任 花子',
                'owner_email' => 'newowner@example.com',
                'owner_password' => 'password1234',
            ]);

        // Assert
        $response->assertRedirect('/super-admin/companies');

        $company = Company::where('name', '新設 株式会社')->first();
        $this->assertNotNull($company);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $company->company_code);

        $owner = User::where('email', 'newowner@example.com')->first();
        $this->assertNotNull($owner);
        $this->assertTrue((bool) $owner->is_owner);
        $this->assertSame('009999', $owner->employee_code);
        $this->assertTrue($owner->companies->contains($company->id));
    }

    /**
     * @test
     */
    public function 事業所の管理者は事業所を作成できない(): void
    {
        $this->actingAs($this->companyAdmin, 'admin')
            ->post('/super-admin/companies', [
                'name' => '不許可 株式会社',
                'owner_name' => '不許可 太郎',
                'owner_email' => 'denied@example.com',
                'owner_password' => 'password1234',
            ])
            ->assertForbidden();

        $this->assertNull(Company::where('name', '不許可 株式会社')->first());
    }

    /**
     * @test
     */
    public function 既に使われているメールアドレスでは事業所を作成できない(): void
    {
        // Arrange
        User::factory()->create(['email' => 'duplicate@example.com']);

        // Act
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->post('/super-admin/companies', [
                'name' => '重複 株式会社',
                'owner_name' => '重複 太郎',
                'owner_email' => 'duplicate@example.com',
                'owner_password' => 'password1234',
            ]);

        // Assert
        $response->assertSessionHasErrors('owner_email');
        $this->assertNull(Company::where('name', '重複 株式会社')->first());
    }

    /**
     * @test
     */
    public function スーパー管理者は事業所を削除できる(): void
    {
        // Arrange
        $target = Company::factory()->create(['company_code' => '930002', 'name' => '削除対象株式会社']);
        $owner = User::factory()->create(['email' => 'target-owner@example.com', 'is_owner' => true]);
        $owner->companies()->attach($target->id, ['is_primary' => true]);

        // Act
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->delete("/super-admin/companies/{$target->id}");

        // Assert
        $response->assertRedirect('/super-admin/companies');
        $this->assertNull(Company::find($target->id));
        $this->assertNull(User::find($owner->id));
    }

    /**
     * @test
     */
    public function 事業所の管理者は事業所を削除できない(): void
    {
        // Arrange
        $target = Company::factory()->create(['company_code' => '930003', 'name' => '保護対象株式会社']);

        // Act
        $response = $this->actingAs($this->companyAdmin, 'admin')
            ->delete("/super-admin/companies/{$target->id}");

        // Assert
        $response->assertForbidden();
        $this->assertNotNull(Company::find($target->id));
    }

    /**
     * @test
     */
    public function 事業所一覧に打刻用_uui_dと打刻_ur_lを表示する(): void
    {
        // FeliCa端末の設定に必要なため、事業所へ伝えられる必要がある
        $target = Company::factory()->create(['company_code' => '930010', 'name' => 'UUID確認株式会社']);

        $response = $this->actingAs($this->superAdmin, 'admin')->get('/super-admin/companies');

        $response->assertStatus(200);

        $companies = collect($response->viewData('page')['props']['companies']);
        $row = $companies->firstWhere('company_code', '930010');

        $this->assertNotNull($row);
        $this->assertSame($target->uuid, $row['uuid']);
        $this->assertStringContainsString('/stamp/'.$target->uuid, $row['public_stamp_url']);
    }
}

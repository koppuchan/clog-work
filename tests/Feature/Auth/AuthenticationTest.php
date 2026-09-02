<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 管理者ログインの検証。
 *
 * 本アプリの認証はメールアドレスではなく、会社コードと個人コードの
 * 組み合わせで行う。管理者とスタッフでガードが分かれている。
 */
class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['company_code' => '900001']);

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create([
                'employee_code' => '100001',
                'password' => bcrypt('password'),
                'is_retired' => false,
            ]);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $this->post('/admin/login', [
            'company_code' => '900001',
            'employee_code' => '100001',
            'password' => 'password',
        ]);

        $this->assertAuthenticated('admin');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $this->post('/admin/login', [
            'company_code' => '900001',
            'employee_code' => '100001',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('admin');
    }

    public function test_users_can_not_authenticate_with_invalid_company_code(): void
    {
        $this->post('/admin/login', [
            'company_code' => '999999',
            'employee_code' => '100001',
            'password' => 'password',
        ]);

        $this->assertGuest('admin');
    }

    public function test_users_can_logout(): void
    {
        $this->actingAs($this->admin, 'admin')->post('/logout');

        $this->assertGuest('admin');
    }
}

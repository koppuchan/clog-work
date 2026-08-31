<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * adminガードとstaffガードの分離が正しく機能することを保証するテスト。
 *
 * 以前は、同一ブラウザでadmin → staffの順にログインすると、両ガードのセッションが
 * 同時に残ってしまい、HandleInertiaRequestsがadminを優先する結果として、
 * スタッフ画面に管理者の名前/情報が表示される不具合があった。
 */
class GuardIsolationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function staff_login_logs_out_admin_guard(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->forCompany($company->id)->create([
            'employee_code' => 'A0001',
        ]);
        $staff = User::factory()->employee()->forCompany($company->id)->create([
            'employee_code' => 'S0001',
        ]);

        // adminでログイン
        $this->post('/admin/login', [
            'company_code' => $company->company_code,
            'employee_code' => 'A0001',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin, 'admin');

        // 続けてstaffでログイン
        $this->post('/staff/login', [
            'company_code' => $company->company_code,
            'employee_code' => 'S0001',
            'password' => 'password',
        ]);

        // staffガードはログイン済み、adminガードはログアウトされている
        $this->assertAuthenticatedAs($staff, 'staff');
        $this->assertFalse(Auth::guard('admin')->check());
    }

    /**
     * @test
     */
    public function admin_login_logs_out_staff_guard(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->forCompany($company->id)->create([
            'employee_code' => 'A0001',
        ]);
        $staff = User::factory()->employee()->forCompany($company->id)->create([
            'employee_code' => 'S0001',
        ]);

        // staffでログイン
        $this->post('/staff/login', [
            'company_code' => $company->company_code,
            'employee_code' => 'S0001',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($staff, 'staff');

        // 続けてadminでログイン
        $this->post('/admin/login', [
            'company_code' => $company->company_code,
            'employee_code' => 'A0001',
            'password' => 'password',
        ]);

        // adminガードはログイン済み、staffガードはログアウトされている
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertFalse(Auth::guard('staff')->check());
    }

    /**
     * @test
     */
    public function inertia_share_returns_staff_user_on_staff_routes_even_if_admin_session_remains(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->forCompany($company->id)->create([
            'name' => '管理者 太郎',
        ]);
        $staff = User::factory()->employee()->forCompany($company->id)->create([
            'name' => 'スタッフ 花子',
        ]);

        // 両ガードに同時にログインしている状態を強制的に作る
        // （ログイン経路の修正以前のリグレッションを防ぐためのガード）
        Auth::guard('admin')->login($admin);
        Auth::guard('staff')->login($staff);

        // /staff/shifts にアクセスすると、HandleInertiaRequests::shareは
        // staffユーザー（花子）を返さなければならない。adminを優先して太郎を
        // 返してはならない。
        $response = $this->get('/staff/shifts');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.name', 'スタッフ 花子')
        );
    }

    /**
     * @test
     */
    public function inertia_share_returns_admin_user_on_admin_routes_even_if_staff_session_remains(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->forCompany($company->id)->create([
            'name' => '管理者 太郎',
            'email_verified_at' => now(),
        ]);
        $staff = User::factory()->employee()->forCompany($company->id)->create([
            'name' => 'スタッフ 花子',
        ]);

        // 両ガードに同時にログインしている状態を強制的に作る
        Auth::guard('admin')->login($admin);
        Auth::guard('staff')->login($staff);

        // /admin/shifts にアクセスすると、auth.user.nameはadmin（太郎）になる
        $response = $this->get('/admin/shifts');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.name', '管理者 太郎')
        );
    }
}

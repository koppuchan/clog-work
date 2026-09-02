<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 編集画面に渡す権限の既定値の検証。
 *
 * ここが固定値になっていると、個別設定のないユーザーの編集画面を
 * 開いて保存しただけで、ロールの既定（例: 管理者は全社）が
 * 「本人のみ」に書き換わってしまう。
 */
class UserPermissionDefaultTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
    }

    private function assignRoleScope(User $user, int $roleId, array $scopesByResource): void
    {
        $user->roles()->sync([$roleId]);

        foreach ($scopesByResource as $resourceId => $scopeId) {
            RolePermission::query()->updateOrCreate(
                ['role_id' => $roleId, 'resource_id' => $resourceId],
                ['default_scope_id' => $scopeId, 'is_fixed' => false],
            );
        }
    }

    /**
     * @test
     */
    public function 個別設定がなければロールの既定を返す(): void
    {
        // 責任者ロールの既定が「部署」であれば、個別設定がない場合はそれを返す
        $user = User::factory()->forCompany($this->company->id)->create(['is_retired' => false]);
        $this->assignRoleScope($user, 2, [1 => 2, 2 => 2, 3 => 2, 4 => 2]);

        $permissions = app(UserService::class)->getPermissionsForFrontend($user->id);

        $this->assertSame('department', $permissions['shift_view_permission']);
        $this->assertSame('department', $permissions['attendance_view_permission']);
    }

    /**
     * @test
     */
    public function 管理者は個別設定に関わらず全社になる(): void
    {
        // isAdmin() は常に company を返す実際の閲覧範囲と、
        // 編集画面に見せる既定値を一致させる
        $user = User::factory()->forCompany($this->company->id)->create(['is_retired' => false]);
        $this->assignRoleScope($user, 1, [1 => 3, 2 => 3, 3 => 3, 4 => 3]);

        $permissions = app(UserService::class)->getPermissionsForFrontend($user->id);

        $this->assertSame('company', $permissions['shift_view_permission']);
        $this->assertSame('company', $permissions['attendance_view_permission']);
        $this->assertSame('company', $permissions['approval_permission']);
        $this->assertSame('company', $permissions['shift_edit_permission']);
    }

    /**
     * @test
     */
    public function 個別設定があればそちらを優先する(): void
    {
        $user = User::factory()->forCompany($this->company->id)->create(['is_retired' => false]);
        $this->assignRoleScope($user, 2, [1 => 2]);

        app(\App\Repositories\Contracts\UserPermissionRepositoryInterface::class)->syncPermissions(
            $user->id,
            [['resource_id' => 1, 'scope_id' => 3]],
        );

        $permissions = app(UserService::class)->getPermissionsForFrontend($user->id);

        $this->assertSame('company', $permissions['shift_view_permission']);
    }

    /**
     * @test
     */
    public function ロールの既定も無ければ本人のみになる(): void
    {
        $user = User::factory()->forCompany($this->company->id)->create(['is_retired' => false]);
        $role = Role::query()->firstOrCreate(['id' => 3], ['name' => '一般']);
        $user->roles()->sync([$role->id]);

        $permissions = app(UserService::class)->getPermissionsForFrontend($user->id);

        $this->assertSame('self', $permissions['approval_permission']);
    }
}

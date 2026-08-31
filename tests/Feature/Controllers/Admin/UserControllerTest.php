<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'name' => 'Test Company',
        ]);

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create([
                'name' => 'Admin User',
                'is_retired' => false,
            ]);
    }

    // ========================================
    // index アクション テスト
    // ========================================

    /**
     * @test
     */
    public function index_marks_authenticated_user_as_self(): void
    {
        // Arrange
        $other = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create(['name' => 'Other User']);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/users');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->where('users', function ($users) use ($other) {
                $usersArray = is_array($users) ? $users : $users->toArray();
                $self = collect($usersArray)->firstWhere('id', $this->admin->id);
                $otherUser = collect($usersArray)->firstWhere('id', $other->id);

                return $self['isSelf'] === true && $otherUser['isSelf'] === false;
            })
        );
    }

    // ========================================
    // destroy アクション テスト
    // ========================================

    /**
     * @test
     */
    public function destroy_prevents_deleting_self(): void
    {
        // Arrange - 削除制限を回避するため、もう1人管理者を追加
        User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create();

        // Act
        $response = $this->actingAs($this->admin, 'admin')
            ->delete('/admin/users/'.$this->admin->id);

        // Assert
        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('error', '自分自身を削除することはできません。');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    /**
     * @test
     */
    public function destroy_allows_deleting_other_user(): void
    {
        // Arrange
        $target = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create();

        // Act
        $response = $this->actingAs($this->admin, 'admin')
            ->delete('/admin/users/'.$target->id);

        // Assert
        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('success', 'ユーザーを削除しました。');
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    // ========================================
    // オーナー保護 テスト
    // ========================================

    /**
     * @test
     */
    public function destroy_prevents_deleting_owner_user(): void
    {
        // Arrange - オーナーユーザーを作成
        $owner = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->owner()
            ->create(['name' => 'Owner User']);

        // Act - 別の管理者がオーナーを削除しようとする
        $response = $this->actingAs($this->admin, 'admin')
            ->delete('/admin/users/'.$owner->id);

        // Assert
        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    /**
     * @test
     */
    public function index_returns_is_owner_flag(): void
    {
        // Arrange - オーナーユーザーを作成
        $owner = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->owner()
            ->create(['name' => 'Owner User']);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/users');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->where('users', function ($users) use ($owner) {
                $usersArray = is_array($users) ? $users : $users->toArray();
                $ownerUser = collect($usersArray)->firstWhere('id', $owner->id);
                $normalUser = collect($usersArray)->firstWhere('id', $this->admin->id);

                return $ownerUser['isOwner'] === true && $normalUser['isOwner'] === false;
            })
        );
    }

    /**
     * @test
     */
    public function update_prevents_changing_owner_role(): void
    {
        // Arrange - オーナーユーザーを作成
        $owner = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->owner()
            ->create(['name' => 'Owner User', 'email' => 'owner@example.com']);

        // Act - オーナーの役割を一般に変更しようとする
        $response = $this->actingAs($this->admin, 'admin')
            ->from('/admin/users/'.$owner->id.'/edit')
            ->put('/admin/users/'.$owner->id, [
                'name' => 'Owner User',
                'email' => 'owner@example.com',
                'role_id' => 3, // 一般ユーザーに変更
                'department_id' => null,
            ]);

        // Assert - エラーになる（redirect()->back()なので元のページに戻る）
        $response->assertRedirect('/admin/users/'.$owner->id.'/edit');
        $response->assertSessionHas('error');
    }
}

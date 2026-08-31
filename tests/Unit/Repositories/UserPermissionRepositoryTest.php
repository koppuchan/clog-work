<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\PermissionResource;
use App\Models\PermissionScope;
use App\Models\User;
use App\Models\UserPermission;
use App\Repositories\UserPermissionRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/UserPermissionRepositoryTest.php
 */
class UserPermissionRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserPermissionRepository $repository;

    private int $nextResourceId = 201;

    private int $nextScopeId = 201;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserPermissionRepository(new UserPermission);
    }

    /**
     * テスト用のリソースとスコープを作成
     *
     * @return array{resource: PermissionResource, scope: PermissionScope}
     */
    private function createResourceAndScope(): array
    {
        $resource = new PermissionResource;
        $resource->incrementing = false;
        $resource->forceFill([
            'id' => $this->nextResourceId++,
            'resource_code' => 'test_res_'.uniqid(),
            'resource_name' => 'テストリソース',
        ])->save();

        $scope = new PermissionScope;
        $scope->incrementing = false;
        $scope->forceFill([
            'id' => $this->nextScopeId++,
            'scope_code' => 'test_scope_'.uniqid(),
            'scope_name' => 'テストスコープ',
        ])->save();

        return ['resource' => $resource, 'scope' => $scope];
    }

    // ========================================
    // findByUserId
    // ========================================

    public function test_find_by_user_idでユーザーの権限が返される(): void
    {
        // Arrange
        $user = User::factory()->create();
        $fixtures = $this->createResourceAndScope();

        UserPermission::query()->create([
            'user_id' => $user->id,
            'resource_id' => $fixtures['resource']->id,
            'scope_id' => $fixtures['scope']->id,
        ]);

        // Act
        $result = $this->repository->findByUserId($user->id);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($user->id, $result->first()->user_id);
        $this->assertEquals($fixtures['resource']->id, $result->first()->resource_id);
        $this->assertEquals($fixtures['scope']->id, $result->first()->scope_id);
    }

    public function test_find_by_user_idでリレーションがロードされる(): void
    {
        // Arrange
        $user = User::factory()->create();
        $fixtures = $this->createResourceAndScope();

        UserPermission::query()->create([
            'user_id' => $user->id,
            'resource_id' => $fixtures['resource']->id,
            'scope_id' => $fixtures['scope']->id,
        ]);

        // Act
        $result = $this->repository->findByUserId($user->id);

        // Assert
        $this->assertTrue($result->first()->relationLoaded('resource'));
        $this->assertTrue($result->first()->relationLoaded('scope'));
    }

    public function test_find_by_user_idで権限がない場合_空コレクションが返される(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $result = $this->repository->findByUserId($user->id);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // create
    // ========================================

    public function test_createで権限が正常に作成される(): void
    {
        // Arrange
        $user = User::factory()->create();
        $fixtures = $this->createResourceAndScope();

        // Act
        $result = $this->repository->create([
            'user_id' => $user->id,
            'resource_id' => $fixtures['resource']->id,
            'scope_id' => $fixtures['scope']->id,
        ]);

        // Assert
        $this->assertInstanceOf(UserPermission::class, $result);
        $this->assertEquals($user->id, $result->user_id);
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'resource_id' => $fixtures['resource']->id,
            'scope_id' => $fixtures['scope']->id,
        ]);
    }

    // ========================================
    // createMany
    // ========================================

    public function test_create_manyで複数権限が一括作成される(): void
    {
        // Arrange
        $user = User::factory()->create();
        $fixtures1 = $this->createResourceAndScope();
        $fixtures2 = $this->createResourceAndScope();

        $permissions = [
            ['resource_id' => $fixtures1['resource']->id, 'scope_id' => $fixtures1['scope']->id],
            ['resource_id' => $fixtures2['resource']->id, 'scope_id' => $fixtures2['scope']->id],
        ];

        // Act
        $this->repository->createMany($user->id, $permissions);

        // Assert
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'resource_id' => $fixtures1['resource']->id,
            'scope_id' => $fixtures1['scope']->id,
        ]);
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'resource_id' => $fixtures2['resource']->id,
            'scope_id' => $fixtures2['scope']->id,
        ]);
    }

    public function test_create_manyで空配列の場合_何も作成されない(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $this->repository->createMany($user->id, []);

        // Assert
        $result = $this->repository->findByUserId($user->id);
        $this->assertCount(0, $result);
    }

    // ========================================
    // syncPermissions
    // ========================================

    public function test_sync_permissionsで既存権限が削除されて新権限が作成される(): void
    {
        // Arrange
        $user = User::factory()->create();
        $oldFixtures = $this->createResourceAndScope();
        $newFixtures = $this->createResourceAndScope();

        UserPermission::query()->create([
            'user_id' => $user->id,
            'resource_id' => $oldFixtures['resource']->id,
            'scope_id' => $oldFixtures['scope']->id,
        ]);

        $newPermissions = [
            ['resource_id' => $newFixtures['resource']->id, 'scope_id' => $newFixtures['scope']->id],
        ];

        // Act
        $this->repository->syncPermissions($user->id, $newPermissions);

        // Assert
        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $user->id,
            'resource_id' => $oldFixtures['resource']->id,
        ]);
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'resource_id' => $newFixtures['resource']->id,
            'scope_id' => $newFixtures['scope']->id,
        ]);
    }

    public function test_sync_permissionsで空配列の場合_全権限が削除される(): void
    {
        // Arrange
        $user = User::factory()->create();
        $fixtures = $this->createResourceAndScope();

        UserPermission::query()->create([
            'user_id' => $user->id,
            'resource_id' => $fixtures['resource']->id,
            'scope_id' => $fixtures['scope']->id,
        ]);

        // Act
        $this->repository->syncPermissions($user->id, []);

        // Assert
        $result = $this->repository->findByUserId($user->id);
        $this->assertCount(0, $result);
    }

    // ========================================
    // deleteByUserId
    // ========================================

    public function test_delete_by_user_idでユーザーの全権限が削除される(): void
    {
        // Arrange
        $user = User::factory()->create();
        $fixtures1 = $this->createResourceAndScope();
        $fixtures2 = $this->createResourceAndScope();

        UserPermission::query()->create([
            'user_id' => $user->id,
            'resource_id' => $fixtures1['resource']->id,
            'scope_id' => $fixtures1['scope']->id,
        ]);
        UserPermission::query()->create([
            'user_id' => $user->id,
            'resource_id' => $fixtures2['resource']->id,
            'scope_id' => $fixtures2['scope']->id,
        ]);

        // Act
        $this->repository->deleteByUserId($user->id);

        // Assert
        $result = $this->repository->findByUserId($user->id);
        $this->assertCount(0, $result);
    }

    public function test_delete_by_user_idで他ユーザーの権限は削除されない(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $fixtures = $this->createResourceAndScope();

        UserPermission::query()->create([
            'user_id' => $user1->id,
            'resource_id' => $fixtures['resource']->id,
            'scope_id' => $fixtures['scope']->id,
        ]);
        UserPermission::query()->create([
            'user_id' => $user2->id,
            'resource_id' => $fixtures['resource']->id,
            'scope_id' => $fixtures['scope']->id,
        ]);

        // Act
        $this->repository->deleteByUserId($user1->id);

        // Assert
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $user1->id]);
        $this->assertDatabaseHas('user_permissions', ['user_id' => $user2->id]);
    }

    // ========================================
    // deleteByUserIdAndResourceId
    // ========================================

    public function test_delete_by_user_id_and_resource_idで特定のリソース権限のみ削除される(): void
    {
        // Arrange
        $user = User::factory()->create();
        $fixtures1 = $this->createResourceAndScope();
        $fixtures2 = $this->createResourceAndScope();

        UserPermission::query()->create([
            'user_id' => $user->id,
            'resource_id' => $fixtures1['resource']->id,
            'scope_id' => $fixtures1['scope']->id,
        ]);
        UserPermission::query()->create([
            'user_id' => $user->id,
            'resource_id' => $fixtures2['resource']->id,
            'scope_id' => $fixtures2['scope']->id,
        ]);

        // Act
        $this->repository->deleteByUserIdAndResourceId($user->id, $fixtures1['resource']->id);

        // Assert
        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $user->id,
            'resource_id' => $fixtures1['resource']->id,
        ]);
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'resource_id' => $fixtures2['resource']->id,
        ]);
    }

    public function test_delete_by_user_id_and_resource_idで該当レコードがない場合_エラーにならない(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act & Assert (例外が発生しないことを確認)
        $this->repository->deleteByUserIdAndResourceId($user->id, 99999);
        $this->assertTrue(true);
    }
}

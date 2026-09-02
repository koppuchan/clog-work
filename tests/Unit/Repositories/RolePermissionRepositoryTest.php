<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\PermissionResource;
use App\Models\PermissionScope;
use App\Models\Role;
use App\Models\RolePermission;
use App\Repositories\RolePermissionRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/RolePermissionRepositoryTest.php
 */
class RolePermissionRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private RolePermissionRepository $repository;

    private Role $role;

    private PermissionResource $resource;

    private PermissionScope $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RolePermissionRepository(new RolePermission);

        $this->role = Role::query()->create(['name' => 'テストロール']);
        $this->resource = $this->createPermissionResource([
            'id' => 201,
            'resource_code' => 'test_resource',
            'resource_name' => 'テストリソース',
        ]);
        $this->scope = $this->createPermissionScope([
            'id' => 201,
            'scope_code' => 'test_scope',
            'scope_name' => 'テストスコープ',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPermissionResource(array $attributes): PermissionResource
    {
        $model = new PermissionResource;
        $model->incrementing = false;
        $model->forceFill($attributes)->save();

        return $model;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPermissionScope(array $attributes): PermissionScope
    {
        $model = new PermissionScope;
        $model->incrementing = false;
        $model->forceFill($attributes)->save();

        return $model;
    }

    public function test_find_by_role_id_and_resource_idで権限を取得_存在する場合は権限を返す(): void
    {
        // Arrange
        RolePermission::query()->create([
            'role_id' => $this->role->id,
            'resource_id' => $this->resource->id,
            'default_scope_id' => $this->scope->id,
            'is_fixed' => true,
        ]);

        // Act
        $result = $this->repository->findByRoleIdAndResourceId($this->role->id, $this->resource->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->role->id, $result->role_id);
        $this->assertEquals($this->resource->id, $result->resource_id);
        $this->assertEquals($this->scope->id, $result->default_scope_id);
        $this->assertTrue($result->is_fixed);
    }

    public function test_find_by_role_id_and_resource_idで権限を取得_存在しない場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findByRoleIdAndResourceId(9999, 9999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_role_id_and_resource_idで権限を取得_role_idが異なる場合はnullを返す(): void
    {
        // Arrange
        RolePermission::query()->create([
            'role_id' => $this->role->id,
            'resource_id' => $this->resource->id,
            'default_scope_id' => $this->scope->id,
            'is_fixed' => false,
        ]);

        // Act: 権限が紐付いていない別のロールIDで引く
        $otherRoleId = $this->role->id + 100000;
        $result = $this->repository->findByRoleIdAndResourceId($otherRoleId, $this->resource->id);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_role_id_and_resource_idで権限を取得_resource_idが異なる場合はnullを返す(): void
    {
        // Arrange
        $otherResource = $this->createPermissionResource([
            'id' => 202,
            'resource_code' => 'other_resource',
            'resource_name' => '別のリソース',
        ]);
        RolePermission::query()->create([
            'role_id' => $this->role->id,
            'resource_id' => $this->resource->id,
            'default_scope_id' => $this->scope->id,
            'is_fixed' => false,
        ]);

        // Act
        $result = $this->repository->findByRoleIdAndResourceId($this->role->id, $otherResource->id);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_role_id_and_resource_idで権限を取得_is_fixedがfalseの場合も正しく返す(): void
    {
        // Arrange
        RolePermission::query()->create([
            'role_id' => $this->role->id,
            'resource_id' => $this->resource->id,
            'default_scope_id' => $this->scope->id,
            'is_fixed' => false,
        ]);

        // Act
        $result = $this->repository->findByRoleIdAndResourceId($this->role->id, $this->resource->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertFalse($result->is_fixed);
    }

    public function test_find_by_role_id_and_resource_idで権限を取得_複数権限がある中から正しいものを返す(): void
    {
        // Arrange
        $otherResource = $this->createPermissionResource([
            'id' => 202,
            'resource_code' => 'other_resource',
            'resource_name' => '別のリソース',
        ]);
        $otherScope = $this->createPermissionScope([
            'id' => 202,
            'scope_code' => 'other_scope',
            'scope_name' => '別のスコープ',
        ]);

        RolePermission::query()->create([
            'role_id' => $this->role->id,
            'resource_id' => $this->resource->id,
            'default_scope_id' => $this->scope->id,
            'is_fixed' => true,
        ]);
        RolePermission::query()->create([
            'role_id' => $this->role->id,
            'resource_id' => $otherResource->id,
            'default_scope_id' => $otherScope->id,
            'is_fixed' => false,
        ]);

        // Act
        $result = $this->repository->findByRoleIdAndResourceId($this->role->id, $this->resource->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->resource->id, $result->resource_id);
        $this->assertEquals($this->scope->id, $result->default_scope_id);
        $this->assertTrue($result->is_fixed);
    }
}

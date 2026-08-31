<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RolePermission;
use App\Repositories\Contracts\RolePermissionRepositoryInterface;

/**
 * 役割権限リポジトリ実装
 */
class RolePermissionRepository implements RolePermissionRepositoryInterface
{
    public function __construct(
        private readonly RolePermission $rolePermission
    ) {}

    /**
     * 役割IDとリソースIDで権限を取得
     *
     * @param  int  $roleId  役割ID
     * @param  int  $resourceId  リソースID
     */
    public function findByRoleIdAndResourceId(int $roleId, int $resourceId): ?RolePermission
    {
        return $this->rolePermission->query()
            ->where('role_id', $roleId)
            ->where('resource_id', $resourceId)
            ->first();
    }
}

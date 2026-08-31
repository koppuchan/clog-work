<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\RolePermission;

/**
 * 役割権限リポジトリインターフェース
 */
interface RolePermissionRepositoryInterface
{
    /**
     * 役割IDとリソースIDで権限を取得
     *
     * @param  int  $roleId  役割ID
     * @param  int  $resourceId  リソースID
     */
    public function findByRoleIdAndResourceId(int $roleId, int $resourceId): ?RolePermission;
}

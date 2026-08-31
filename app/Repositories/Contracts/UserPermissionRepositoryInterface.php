<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\UserPermission;
use Illuminate\Database\Eloquent\Collection;

/**
 * ユーザー権限リポジトリインターフェース
 */
interface UserPermissionRepositoryInterface
{
    /**
     * ユーザーIDで権限を取得
     *
     * @param  int  $userId  ユーザーID
     * @return Collection<int, UserPermission>
     */
    public function findByUserId(int $userId): Collection;

    /**
     * ユーザー権限を作成
     *
     * @param  array<string, mixed>  $data  権限データ
     */
    public function create(array $data): UserPermission;

    /**
     * ユーザー権限を一括作成
     *
     * @param  int  $userId  ユーザーID
     * @param  array<int, array<string, int>>  $permissions  権限データの配列 [['resource_id' => 1, 'scope_id' => 2], ...]
     */
    public function createMany(int $userId, array $permissions): void;

    /**
     * ユーザーの権限を同期（既存削除 + 新規作成）
     *
     * @param  int  $userId  ユーザーID
     * @param  array<int, array<string, int>>  $permissions  権限データの配列
     */
    public function syncPermissions(int $userId, array $permissions): void;

    /**
     * ユーザーの全権限を削除
     *
     * @param  int  $userId  ユーザーID
     */
    public function deleteByUserId(int $userId): void;

    /**
     * 特定のユーザー権限を削除
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $resourceId  リソースID
     */
    public function deleteByUserIdAndResourceId(int $userId, int $resourceId): void;
}

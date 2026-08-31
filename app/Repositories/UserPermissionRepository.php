<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserPermission;
use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use App\Traits\HasLogService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * ユーザー権限リポジトリ実装
 */
class UserPermissionRepository implements UserPermissionRepositoryInterface
{
    use HasLogService;

    public function __construct(
        private readonly UserPermission $userPermission
    ) {}

    /**
     * ユーザーIDで権限を取得
     *
     * @param  int  $userId  ユーザーID
     * @return Collection<int, UserPermission>
     */
    public function findByUserId(int $userId): Collection
    {
        return $this->userPermission->query()
            ->where('user_id', $userId)
            ->with(['resource', 'scope'])
            ->get();
    }

    /**
     * ユーザー権限を作成
     *
     * @param  array<string, mixed>  $data  権限データ
     */
    public function create(array $data): UserPermission
    {
        return $this->userPermission->query()->create($data);
    }

    /**
     * ユーザー権限を一括作成
     *
     * @param  int  $userId  ユーザーID
     * @param  array<int, array<string, int>>  $permissions  権限データの配列
     */
    public function createMany(int $userId, array $permissions): void
    {
        $now = CarbonImmutable::now();

        $permissionsToInsert = collect($permissions)->map(function ($permission) use ($userId, $now) {
            return [
                'user_id' => $userId,
                'resource_id' => $permission['resource_id'],
                'scope_id' => $permission['scope_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->toArray();

        $this->logInfo('Creating permissions', [
            'user_id' => $userId,
            'permissions_to_insert' => $permissionsToInsert,
            'count' => count($permissionsToInsert),
        ]);

        if (! empty($permissionsToInsert)) {
            $this->userPermission->query()->insert($permissionsToInsert);
            $this->logInfo('Inserted successfully');
        } else {
            $this->logInfo('Nothing to insert');
        }
    }

    /**
     * ユーザーの権限を同期（既存削除 + 新規作成）
     *
     * @param  int  $userId  ユーザーID
     * @param  array<int, array<string, int>>  $permissions  権限データの配列
     */
    public function syncPermissions(int $userId, array $permissions): void
    {
        $this->logInfo('Syncing permissions', [
            'user_id' => $userId,
            'permissions' => $permissions,
            'permissions_count' => count($permissions),
            'is_empty' => empty($permissions),
        ]);

        // 既存の権限を削除
        $this->deleteByUserId($userId);
        $this->logInfo('Deleted existing permissions', [
            'user_id' => $userId,
        ]);

        // 新しい権限を作成
        if (! empty($permissions)) {
            $this->createMany($userId, $permissions);
        } else {
            $this->logInfo('No permissions to create');
        }
    }

    /**
     * ユーザーの全権限を削除
     *
     * @param  int  $userId  ユーザーID
     */
    public function deleteByUserId(int $userId): void
    {
        $this->userPermission->query()
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * 特定のユーザー権限を削除
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $resourceId  リソースID
     */
    public function deleteByUserIdAndResourceId(int $userId, int $resourceId): void
    {
        $this->userPermission->query()
            ->where('user_id', $userId)
            ->where('resource_id', $resourceId)
            ->delete();
    }
}

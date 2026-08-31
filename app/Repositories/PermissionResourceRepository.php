<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PermissionResource;
use App\Repositories\Contracts\PermissionResourceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * 権限リソースリポジトリ実装
 */
class PermissionResourceRepository implements PermissionResourceRepositoryInterface
{
    public function __construct(
        private readonly PermissionResource $permissionResource
    ) {}

    /**
     * 全ての権限リソースを取得
     *
     * @return Collection<int, PermissionResource>
     */
    public function getAll(): Collection
    {
        return $this->permissionResource->query()
            ->orderBy('id')
            ->get();
    }

    /**
     * IDで権限リソースを取得
     *
     * @param  int  $id  リソースID
     */
    public function findById(int $id): ?PermissionResource
    {
        return $this->permissionResource->query()->find($id);
    }

    /**
     * リソースコードで権限リソースを取得
     *
     * @param  string  $resourceCode  リソースコード
     */
    public function findByCode(string $resourceCode): ?PermissionResource
    {
        return $this->permissionResource->query()
            ->where('resource_code', $resourceCode)
            ->first();
    }
}

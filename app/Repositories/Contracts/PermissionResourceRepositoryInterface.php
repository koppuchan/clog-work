<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PermissionResource;
use Illuminate\Database\Eloquent\Collection;

/**
 * 権限リソースリポジトリインターフェース
 */
interface PermissionResourceRepositoryInterface
{
    /**
     * 全ての権限リソースを取得
     *
     * @return Collection<int, PermissionResource>
     */
    public function getAll(): Collection;

    /**
     * IDで権限リソースを取得
     *
     * @param  int  $id  リソースID
     */
    public function findById(int $id): ?PermissionResource;

    /**
     * リソースコードで権限リソースを取得
     *
     * @param  string  $resourceCode  リソースコード
     */
    public function findByCode(string $resourceCode): ?PermissionResource;
}


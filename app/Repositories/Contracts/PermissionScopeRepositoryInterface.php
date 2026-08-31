<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PermissionScope;
use Illuminate\Database\Eloquent\Collection;

/**
 * 権限スコープリポジトリインターフェース
 */
interface PermissionScopeRepositoryInterface
{
    /**
     * 全ての権限スコープを取得
     *
     * @return Collection<int, PermissionScope>
     */
    public function getAll(): Collection;

    /**
     * IDで権限スコープを取得
     *
     * @param  int  $id  スコープID
     */
    public function findById(int $id): ?PermissionScope;

    /**
     * スコープコードで権限スコープを取得
     *
     * @param  string  $scopeCode  スコープコード
     */
    public function findByCode(string $scopeCode): ?PermissionScope;
}

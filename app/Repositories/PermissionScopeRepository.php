<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PermissionScope;
use App\Repositories\Contracts\PermissionScopeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * 権限スコープリポジトリ実装
 */
class PermissionScopeRepository implements PermissionScopeRepositoryInterface
{
    public function __construct(
        private readonly PermissionScope $permissionScope
    ) {}

    /**
     * 全ての権限スコープを取得
     *
     * @return Collection<int, PermissionScope>
     */
    public function getAll(): Collection
    {
        return $this->permissionScope->query()
            ->orderBy('id')
            ->get();
    }

    /**
     * IDで権限スコープを取得
     *
     * @param  int  $id  スコープID
     */
    public function findById(int $id): ?PermissionScope
    {
        return $this->permissionScope->query()->find($id);
    }

    /**
     * スコープコードで権限スコープを取得
     *
     * @param  string  $scopeCode  スコープコード
     */
    public function findByCode(string $scopeCode): ?PermissionScope
    {
        return $this->permissionScope->query()
            ->where('scope_code', $scopeCode)
            ->first();
    }
}

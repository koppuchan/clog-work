<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

/**
 * ロールサービス
 */
class RoleService
{
    /**
     * 全てのロールを取得
     *
     * @return Collection<int, Role>
     */
    public function getAll(): Collection
    {
        // Todo: DBから取得するようにリポジトリクラスに移動
        return Role::query()->orderBy('id')->get();
    }

    /**
     * IDでロールを取得
     *
     * @param  int  $id  ロールID
     */
    public function findById(int $id): ?Role
    {
        // Todo: DBから取得するようにリポジトリクラスに移動
        return Role::query()->find($id);
    }
}

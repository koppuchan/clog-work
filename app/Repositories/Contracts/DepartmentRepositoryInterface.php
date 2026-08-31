<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

/**
 * 部署リポジトリインターフェース
 */
interface DepartmentRepositoryInterface
{
    /**
     * IDで部署を取得
     *
     * @param  int  $id  部署ID
     */
    public function findById(int $id): ?Department;

    /**
     * IDで部署をリレーションと共に取得
     *
     * @param  int  $id  部署ID
     */
    public function findByIdWithRelations(int $id): ?Department;

    /**
     * 会社IDで部署を取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, Department>
     */
    public function findByCompanyId(int $companyId): Collection;

    /**
     * 全ての部署を取得
     *
     * @return Collection<int, Department>
     */
    public function getAll(): Collection;

    /**
     * 部署を作成
     *
     * @param  array<string, mixed>  $data  部署データ
     */
    public function create(array $data): Department;

    /**
     * 部署を更新
     *
     * @param  int  $id  部署ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): Department;

    /**
     * 部署を削除
     *
     * @param  int  $id  部署ID
     */
    public function delete(int $id): bool;
}

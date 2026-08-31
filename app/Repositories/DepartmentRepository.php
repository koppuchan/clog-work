<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Department;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * 部署リポジトリ実装
 */
class DepartmentRepository implements DepartmentRepositoryInterface
{
    public function __construct(
        private readonly Department $department
    ) {}

    /**
     * IDで部署を取得
     *
     * @param  int  $id  部署ID
     */
    public function findById(int $id): ?Department
    {
        return $this->department->query()->find($id);
    }

    /**
     * IDで部署をリレーションと共に取得
     *
     * @param  int  $id  部署ID
     */
    public function findByIdWithRelations(int $id): ?Department
    {
        return $this->department->query()
            ->with(['users'])
            ->find($id);
    }

    /**
     * 会社IDで部署を取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, Department>
     */
    public function findByCompanyId(int $companyId): Collection
    {
        return $this->department->query()
            ->where('company_id', $companyId)
            ->withCount('users')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * 全ての部署を取得
     *
     * @return Collection<int, Department>
     */
    public function getAll(): Collection
    {
        return $this->department->query()->get();
    }

    /**
     * 部署を作成
     *
     * @param  array<string, mixed>  $data  部署データ
     */
    public function create(array $data): Department
    {
        return $this->department->query()->create($data);
    }

    /**
     * 部署を更新
     *
     * @param  int  $id  部署ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): Department
    {
        $department = $this->findById($id);

        if (! $department) {
            throw new \RuntimeException("Department with ID {$id} not found");
        }

        $department->update($data);

        return $department->fresh();
    }

    /**
     * 部署を削除
     *
     * @param  int  $id  部署ID
     */
    public function delete(int $id): bool
    {
        $department = $this->findById($id);

        if (! $department) {
            return false;
        }

        return $department->delete();
    }
}

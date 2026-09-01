<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * 部署サービス
 *
 * 部署の取得・作成・更新・削除を行う
 */
class DepartmentService
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departmentRepository
    ) {}

    /**
     * 全ての部署を取得
     *
     * @return Collection<int, Department>
     */
    public function getAll(): Collection
    {
        return $this->departmentRepository->getAll();
    }

    /**
     * 会社IDで部署を取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, Department>
     */
    public function findByCompanyId(int $companyId): Collection
    {
        return $this->departmentRepository->findByCompanyId($companyId);
    }

    /**
     * IDで部署を取得
     *
     * @param  int  $id  部署ID
     */
    public function findById(int $id): ?Department
    {
        return $this->departmentRepository->findById($id);
    }

    /**
     * 会社の部署一覧を取得（フロントエンド用にフォーマット）
     *
     * @param  int  $companyId  会社ID
     * @return SupportCollection<int, array{id: int, name: string, userCount: int, isStampHidden: bool}>
     */
    public function getDepartmentsForSettings(int $companyId): SupportCollection
    {
        $departments = $this->departmentRepository->findByCompanyId($companyId);

        return $departments->map(function (Department $department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
                'userCount' => $department->users_count ?? 0,
                'isStampHidden' => $department->is_stamp_hidden ?? false,
            ];
        });
    }

    /**
     * 部署を取得（フロントエンド用にフォーマット）
     *
     * @param  int  $id  部署ID
     * @return array{id: int, name: string, userCount: int, isStampHidden: bool}|null
     */
    public function getDepartmentForSettings(int $id): ?array
    {
        $department = $this->departmentRepository->findByIdWithRelations($id);

        if (! $department) {
            return null;
        }

        return [
            'id' => $department->id,
            'name' => $department->name,
            'userCount' => $department->users->count(),
            'isStampHidden' => $department->is_stamp_hidden ?? false,
        ];
    }

    /**
     * 部署を作成
     *
     * @param  int  $companyId  会社ID
     * @param  array{name: string, is_stamp_hidden?: bool}  $data  部署データ
     * @return array{id: int, name: string, userCount: int, isStampHidden: bool}
     */
    public function createDepartment(int $companyId, array $data): array
    {
        $department = $this->departmentRepository->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'is_stamp_hidden' => $data['is_stamp_hidden'] ?? false,
        ]);

        return [
            'id' => $department->id,
            'name' => $department->name,
            'userCount' => 0,
            'isStampHidden' => $department->is_stamp_hidden,
        ];
    }

    /**
     * 部署を更新
     *
     * @param  int  $id  部署ID
     * @param  array{name: string, is_stamp_hidden?: bool}  $data  更新データ
     * @return array{id: int, name: string, userCount: int, isStampHidden: bool}
     */
    public function updateDepartment(int $id, array $data): array
    {
        $updateData = ['name' => $data['name']];

        if (array_key_exists('is_stamp_hidden', $data)) {
            $updateData['is_stamp_hidden'] = $data['is_stamp_hidden'];
        }

        $department = $this->departmentRepository->update($id, $updateData);

        return [
            'id' => $department->id,
            'name' => $department->name,
            'userCount' => $department->users()->count(),
            'isStampHidden' => $department->is_stamp_hidden,
        ];
    }

    /**
     * 部署を削除
     *
     * @param  int  $id  部署ID
     * @return bool 削除成功したかどうか
     *
     * @throws \RuntimeException 部署に所属するユーザーがいる場合
     */
    public function deleteDepartment(int $id): bool
    {
        $department = $this->departmentRepository->findByIdWithRelations($id);

        if (! $department) {
            return false;
        }

        // 所属ユーザーがいる場合は削除不可
        if ($department->users->count() > 0) {
            throw new \RuntimeException('部署に所属するスタッフがいるため削除できません');
        }

        return $this->departmentRepository->delete($id);
    }

    /**
     * 部署が会社に属しているか確認
     *
     * @param  int  $departmentId  部署ID
     * @param  int  $companyId  会社ID
     */
    public function belongsToCompany(int $departmentId, int $companyId): bool
    {
        $department = $this->departmentRepository->findById($departmentId);

        if (! $department) {
            return false;
        }

        return $department->company_id === $companyId;
    }

    /**
     * 部署を一括同期（追加・更新・削除）
     *
     * @param  int  $companyId  会社ID
     * @param  array<int, array{id?: int, name: string}>  $departments  部署データ配列
     * @param  array<int>  $deletedIds  削除対象のID配列
     */
    public function syncDepartments(int $companyId, array $departments, array $deletedIds = []): void
    {
        $departmentsCollection = collect($departments);
        $deletedIdsCollection = collect($deletedIds);

        // 会社所属の部署IDを一括取得（N+1回避）
        $companyDeptIds = $this->departmentRepository->findByCompanyId($companyId)->pluck('id');

        // 削除対象の部署を削除（会社所属チェック後）
        $deletedIdsCollection
            ->filter(fn (int $id) => $companyDeptIds->contains($id))
            ->each(function (int $id): void {
                try {
                    $this->deleteDepartment($id);
                } catch (\RuntimeException) {
                    // 所属ユーザーがいる場合はスキップ
                }
            });

        // 既存の部署を更新
        $departmentsCollection
            ->filter(fn (array $dept) => isset($dept['id']) && $dept['id'] > 0)
            ->filter(fn (array $dept) => $companyDeptIds->contains($dept['id']))
            ->each(fn (array $dept) => $this->updateDepartment($dept['id'], [
                'name' => $dept['name'],
            ]));

        // 新規部署を作成
        $departmentsCollection
            ->filter(fn (array $dept) => ! isset($dept['id']) || $dept['id'] <= 0)
            ->each(fn (array $dept) => $this->createDepartment($companyId, [
                'name' => $dept['name'],
            ]));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissionResource;
use App\Models\PermissionScope;
use App\Models\User;
use App\Repositories\Contracts\PermissionResourceRepositoryInterface;
use App\Repositories\Contracts\PermissionScopeRepositoryInterface;
use App\Repositories\Contracts\RolePermissionRepositoryInterface;
use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * 権限サービス
 */
class PermissionService
{
    /**
     * リソース定数
     */
    public const RESOURCE_SHIFT_VIEW = 1;

    public const RESOURCE_ATTENDANCE_VIEW = 2;

    public const RESOURCE_REQUEST_APPROVAL = 3;

    public const RESOURCE_SHIFT_EDIT = 4;

    /**
     * スコープ定数
     */
    public const SCOPE_SELF = 1;

    public const SCOPE_DEPARTMENT = 2;

    public const SCOPE_COMPANY = 3;

    public function __construct(
        private readonly PermissionResourceRepositoryInterface $permissionResourceRepository,
        private readonly PermissionScopeRepositoryInterface $permissionScopeRepository,
        private readonly RolePermissionRepositoryInterface $rolePermissionRepository,
        private readonly UserPermissionRepositoryInterface $userPermissionRepository
    ) {}

    /**
     * 全ての権限リソースを取得
     *
     * @return Collection<int, PermissionResource>
     */
    public function getAllResources(): Collection
    {
        return $this->permissionResourceRepository->getAll();
    }

    /**
     * 全ての権限スコープを取得
     *
     * @return Collection<int, PermissionScope>
     */
    public function getAllScopes(): Collection
    {
        return $this->permissionScopeRepository->getAll();
    }

    /**
     * リソースコードで権限リソースを取得
     *
     * @param  string  $resourceCode  リソースコード
     */
    public function findResourceByCode(string $resourceCode): ?PermissionResource
    {
        return $this->permissionResourceRepository->findByCode($resourceCode);
    }

    /**
     * スコープコードで権限スコープを取得
     *
     * @param  string  $scopeCode  スコープコード
     */
    public function findScopeByCode(string $scopeCode): ?PermissionScope
    {
        return $this->permissionScopeRepository->findByCode($scopeCode);
    }

    /**
     * ユーザーのリソース権限スコープを取得
     *
     * 優先順位:
     * 1. user_permissions テーブルの個別設定
     * 2. role_permissions テーブルの役割デフォルト設定
     *
     * @param  User  $user  ユーザー
     * @param  int  $resourceId  リソースID
     * @return int スコープID（SCOPE_SELF, SCOPE_DEPARTMENT, SCOPE_COMPANY）
     */
    public function getUserResourceScope(User $user, int $resourceId): int
    {
        // 1. user_permissions から個別設定を確認
        $userPermission = $this->userPermissionRepository->findByUserId($user->id)
            ->first(fn ($permission) => $permission->resource_id === $resourceId);

        if ($userPermission) {
            return $userPermission->scope_id;
        }

        // 2. role_permissions からデフォルト設定を取得
        $roleId = $user->roles()->first()?->id;

        if ($roleId) {
            $rolePermission = $this->rolePermissionRepository->findByRoleIdAndResourceId($roleId, $resourceId);

            if ($rolePermission) {
                return $rolePermission->default_scope_id;
            }
        }

        // デフォルトは本人のみ
        return self::SCOPE_SELF;
    }

    /**
     * ユーザーのシフト閲覧スコープを取得
     *
     * @param  User  $user  ユーザー
     * @return string スコープ文字列（'self', 'department', 'company'）
     */
    public function getShiftViewScope(User $user): string
    {
        if ($user->isAdmin()) {
            return 'company';
        }

        $scopeId = $this->getUserResourceScope($user, self::RESOURCE_SHIFT_VIEW);

        return match ($scopeId) {
            self::SCOPE_COMPANY => 'company',
            self::SCOPE_DEPARTMENT => 'department',
            default => 'self',
        };
    }

    /**
     * ユーザーの勤務実績閲覧スコープを取得
     *
     * @param  User  $user  ユーザー
     * @return string スコープ文字列（'self', 'department', 'company'）
     */
    public function getAttendanceViewScope(User $user): string
    {
        if ($user->isAdmin()) {
            return 'company';
        }

        $scopeId = $this->getUserResourceScope($user, self::RESOURCE_ATTENDANCE_VIEW);

        return match ($scopeId) {
            self::SCOPE_COMPANY => 'company',
            self::SCOPE_DEPARTMENT => 'department',
            default => 'self',
        };
    }

    /**
     * ユーザーの申請承認スコープを取得
     *
     * @param  User  $user  ユーザー
     * @return string スコープ文字列（'self', 'department', 'company'）
     */
    public function getApprovalScope(User $user): string
    {
        if ($user->isAdmin()) {
            return 'company';
        }

        $scopeId = $this->getUserResourceScope($user, self::RESOURCE_REQUEST_APPROVAL);

        return match ($scopeId) {
            self::SCOPE_COMPANY => 'company',
            self::SCOPE_DEPARTMENT => 'department',
            default => 'self',
        };
    }

    /**
     * ユーザーのシフト編集スコープを取得
     *
     * @param  User  $user  ユーザー
     * @return string スコープ文字列（'self', 'department', 'company'）
     */
    public function getShiftEditScope(User $user): string
    {
        if ($user->isAdmin()) {
            return 'company';
        }

        $scopeId = $this->getUserResourceScope($user, self::RESOURCE_SHIFT_EDIT);

        return match ($scopeId) {
            self::SCOPE_COMPANY => 'company',
            self::SCOPE_DEPARTMENT => 'department',
            default => 'self',
        };
    }

    /**
     * ユーザーがアクセス可能なユーザーIDリストを取得
     *
     * @param  User  $user  操作ユーザー
     * @param  int  $resourceId  リソースID
     * @return array<int> アクセス可能なユーザーIDの配列
     */
    public function getAccessibleUserIds(User $user, int $resourceId): array
    {
        // 管理者は常に会社全員にアクセス可能
        if ($user->isAdmin()) {
            return $this->getUserIdsInSameCompany($user);
        }

        $scopeId = $this->getUserResourceScope($user, $resourceId);

        return match ($scopeId) {
            self::SCOPE_COMPANY => $this->getUserIdsInSameCompany($user),
            self::SCOPE_DEPARTMENT => $this->getUserIdsInSameDepartments($user),
            default => [$user->id], // SCOPE_SELF
        };
    }

    /**
     * 同じ会社に所属するユーザーIDを取得
     *
     * @param  User  $user  基準ユーザー
     * @return array<int> 同社ユーザーIDの配列
     */
    private function getUserIdsInSameCompany(User $user): array
    {
        $companyId = $user->company_id;

        if (! $companyId) {
            return [$user->id];
        }

        // user_companiesテーブルを通じて同じ会社のユーザーを取得
        // Todo: repositoryに切り出す
        $userIds = User::query()
            ->whereHas('companies', function ($query) use ($companyId) {
                $query->where('companies.id', $companyId);
            })
            ->pluck('id')
            ->toArray();

        // 自分自身が含まれていない場合は追加（user_companiesにエントリがないケース）
        if (! in_array($user->id, $userIds, true)) {
            $userIds[] = $user->id;
        }

        return $userIds;
    }

    /**
     * 同じ部署に所属するユーザーIDを取得
     *
     * @param  User  $user  基準ユーザー
     * @return array<int> 同部署ユーザーIDの配列
     */
    private function getUserIdsInSameDepartments(User $user): array
    {
        // ユーザーが所属する部署を取得
        $departments = $user->departments;

        if ($departments->isEmpty()) {
            return [$user->id];
        }

        $companyId = $user->company_id;

        // 各部署に所属するユーザーを取得して統合
        $userIds = collect();

        // Todo: ここコレクションにする
        foreach ($departments as $department) {
            $departmentUserIds = $department->users()
                ->whereHas('companies', function ($query) use ($companyId) {
                    $query->where('companies.id', $companyId);
                })
                ->pluck('users.id');
            $userIds = $userIds->merge($departmentUserIds);
        }

        return $userIds->unique()->values()->toArray();
    }
}

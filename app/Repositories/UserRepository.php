<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Models\UserAvailableWorkDay;
use App\Models\UserShiftPattern;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ユーザーリポジトリ実装
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * 曜日名とweekday_idのマッピング
     *
     * @var array<string, int>
     */
    private const WEEKDAY_MAP = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
    ];

    public function __construct(
        private readonly User $user
    ) {}

    /**
     * IDでユーザーを取得
     *
     * @param  int  $id  ユーザーID
     */
    public function findById(int $id): ?User
    {
        return $this->user->query()->find($id);
    }

    /**
     * IDでユーザーをリレーションと共に取得
     *
     * @param  int  $id  ユーザーID
     */
    public function findByIdWithRelations(int $id): ?User
    {
        return $this->user->query()
            ->with(['roles', 'companies', 'departments'])
            ->find($id);
    }

    /**
     * メールアドレスでユーザーを取得
     *
     * @param  string  $email  メールアドレス
     */
    public function findByEmail(string $email): ?User
    {
        return $this->user->query()
            ->where('email', $email)
            ->first();
    }

    /**
     * 従業員コードと会社IDでユーザーを取得
     *
     * @param  string  $employeeCode  従業員コード
     * @param  int  $companyId  会社ID
     */
    public function findByEmployeeCodeAndCompanyId(string $employeeCode, int $companyId): ?User
    {
        return $this->user->query()
            ->where('employee_code', $employeeCode)
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $companyId))
            ->first();
    }

    /**
     * 全てのユーザーを取得
     *
     * @return Collection<int, User>
     */
    public function getAll(): Collection
    {
        return $this->user->query()->get();
    }

    /**
     * 会社IDでユーザーを取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, User>
     */
    public function findByCompanyId(int $companyId): Collection
    {
        return $this->user->query()
            ->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->with(['roles'])
            ->get();
    }

    /**
     * シフト表示用に会社IDと対象日付でユーザーを取得
     * 対象日付時点で在籍しており、かつシフト表への表示が有効なユーザーのみを返す
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日付（Y-m-d形式）
     * @return Collection<int, User>
     */
    public function findByCompanyIdForShift(int $companyId, string $targetDate): Collection
    {
        return $this->user->query()
            ->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->where('is_shift_hidden', false)
            ->where(function ($query) use ($targetDate) {
                // 退職していない、または退職日が対象日付以降のユーザー
                $query->where('is_retired', false)
                    ->orWhere(function ($q) use ($targetDate) {
                        $q->where('is_retired', true)
                            ->where('retirement_date', '>=', $targetDate);
                    });
            })
            ->with(['roles', 'departments'])
            ->orderBy('name')
            ->get();
    }

    /**
     * ページネーション付きでユーザーを取得
     *
     * @param  int  $perPage  1ページあたりの件数
     * @return LengthAwarePaginator<User>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->user->query()
            ->with(['roles'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * ユーザーを作成
     *
     * @param  array<string, mixed>  $data  ユーザーデータ
     */
    public function create(array $data): User
    {
        return $this->user->query()->create($data);
    }

    /**
     * ユーザーを更新
     *
     * @param  int  $id  ユーザーID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): User
    {
        $user = $this->findById($id);

        if (! $user) {
            throw new \RuntimeException("User with ID {$id} not found");
        }

        $user->update($data);

        return $user->fresh();
    }

    /**
     * ユーザーを削除
     *
     * @param  int  $id  ユーザーID
     */
    public function delete(int $id): bool
    {
        $user = $this->findById($id);

        if (! $user) {
            return false;
        }

        return $user->delete();
    }

    /**
     * 部署でユーザーを取得
     *
     * @param  string  $department  部署名
     * @return Collection<int, User>
     */
    public function findByDepartment(string $department): Collection
    {
        return $this->user->query()
            ->where('department', $department)
            ->get();
    }

    /**
     * ロールでユーザーを取得
     *
     * @param  int  $roleId  ロールID
     * @return Collection<int, User>
     */
    public function findByRole(int $roleId): Collection
    {
        return $this->user->query()
            ->whereHas('roles', function ($query) use ($roleId) {
                $query->where('role_id', $roleId);
            })
            ->get();
    }

    /**
     * ユーザーにロールを割り当て
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $roleId  ロールID
     */
    public function attachRole(int $userId, int $roleId): void
    {
        $user = $this->findById($userId);

        if (! $user) {
            throw new \RuntimeException("User with ID {$userId} not found");
        }

        $user->roles()->attach($roleId);
    }

    /**
     * ユーザーからロールを削除
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $roleId  ロールID
     */
    public function detachRole(int $userId, int $roleId): void
    {
        $user = $this->findById($userId);

        if (! $user) {
            throw new \RuntimeException("User with ID {$userId} not found");
        }

        $user->roles()->detach($roleId);
    }

    /**
     * ユーザーのロールを同期
     *
     * @param  int  $userId  ユーザーID
     * @param  array<int>  $roleIds  ロールIDの配列
     */
    public function syncRoles(int $userId, array $roleIds): void
    {
        $user = $this->findById($userId);

        if (! $user) {
            throw new \RuntimeException("User with ID {$userId} not found");
        }

        $user->roles()->sync($roleIds);
    }

    /**
     * ユーザーに会社を紐付け
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $companyId  会社ID
     * @param  bool  $isPrimary  主要な会社かどうか
     */
    public function attachCompany(int $userId, int $companyId, bool $isPrimary = true): void
    {
        $user = $this->findById($userId);

        if (! $user) {
            throw new \RuntimeException("User with ID {$userId} not found");
        }

        $user->companies()->attach($companyId, ['is_primary' => $isPrimary]);
    }

    /**
     * ユーザーに部署を紐付け
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $departmentId  部署ID
     * @param  bool  $isPrimary  主要な部署かどうか
     */
    public function attachDepartment(int $userId, int $departmentId, bool $isPrimary = true): void
    {
        $user = $this->findById($userId);

        if (! $user) {
            throw new \RuntimeException("User with ID {$userId} not found");
        }

        $user->departments()->attach($departmentId, ['is_primary' => $isPrimary]);
    }

    /**
     * ユーザーの勤務可能曜日を取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, bool> 曜日名をキーとした配列
     */
    public function getAvailableWorkDays(int $userId): array
    {
        $results = UserAvailableWorkDay::query()
            ->where('user_id', $userId)
            ->get();

        $availableWorkDays = [
            'sunday' => false,
            'monday' => false,
            'tuesday' => false,
            'wednesday' => false,
            'thursday' => false,
            'friday' => false,
            'saturday' => false,
        ];

        foreach ($results as $row) {
            $dayName = array_search($row->weekday_id, self::WEEKDAY_MAP);
            if ($dayName !== false) {
                $availableWorkDays[$dayName] = $row->is_available;
            }
        }

        return $availableWorkDays;
    }

    /**
     * ユーザーのシフトパターンを取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, int|null> 曜日名をキーとした配列
     */
    public function getShiftPatterns(int $userId): array
    {
        $results = UserShiftPattern::query()
            ->where('user_id', $userId)
            ->get();

        $shiftPatterns = [
            'sunday' => null,
            'monday' => null,
            'tuesday' => null,
            'wednesday' => null,
            'thursday' => null,
            'friday' => null,
            'saturday' => null,
        ];

        foreach ($results as $row) {
            $dayName = array_search($row->weekday_id, self::WEEKDAY_MAP);
            if ($dayName !== false) {
                $shiftPatterns[$dayName] = $row->shift_pattern_id;
            }
        }

        return $shiftPatterns;
    }

    /**
     * ユーザーの勤務可能曜日を保存
     *
     * @param  int  $userId  ユーザーID
     * @param  array<string, bool>  $availableWorkDays  曜日名をキーとした配列
     */
    public function syncAvailableWorkDays(int $userId, array $availableWorkDays): void
    {
        // 既存のデータを削除
        UserAvailableWorkDay::query()
            ->where('user_id', $userId)
            ->delete();

        // 新しいデータを挿入
        $now = CarbonImmutable::now();
        foreach ($availableWorkDays as $dayName => $isAvailable) {
            $weekdayId = self::WEEKDAY_MAP[$dayName] ?? null;
            if ($weekdayId === null) {
                continue;
            }

            UserAvailableWorkDay::query()->create([
                'user_id' => $userId,
                'weekday_id' => $weekdayId,
                'is_available' => $isAvailable,
            ]);
        }
    }

    /**
     * ユーザーのシフトパターンを保存
     *
     * @param  int  $userId  ユーザーID
     * @param  array<string, int|null>  $shiftPatterns  曜日名をキーとした配列
     */
    public function syncShiftPatterns(int $userId, array $shiftPatterns): void
    {
        // 既存のデータを削除
        UserShiftPattern::query()
            ->where('user_id', $userId)
            ->delete();

        // 新しいデータを挿入（値がnullでない場合のみ）
        foreach ($shiftPatterns as $dayName => $shiftPatternId) {
            if ($shiftPatternId === null) {
                continue;
            }

            $weekdayId = self::WEEKDAY_MAP[$dayName] ?? null;
            if ($weekdayId === null) {
                continue;
            }

            UserShiftPattern::query()->create([
                'user_id' => $userId,
                'weekday_id' => $weekdayId,
                'shift_pattern_id' => $shiftPatternId,
            ]);
        }
    }

    /**
     * 複数ユーザーのシフトパターンを一括取得
     *
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @return Collection<int, UserShiftPattern>
     */
    public function getBulkShiftPatterns(array $userIds): Collection
    {
        return UserShiftPattern::query()
            ->whereIn('user_id', $userIds)
            ->get();
    }

    /**
     * 複数ユーザーの勤務可能曜日を一括取得
     *
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @return Collection<int, UserAvailableWorkDay>
     */
    public function getBulkAvailableWorkDays(array $userIds): Collection
    {
        return UserAvailableWorkDay::query()
            ->whereIn('user_id', $userIds)
            ->get();
    }

    /**
     * 部署IDでユーザーを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $departmentId  部署ID
     * @return Collection<int, User>
     */
    public function findByDepartmentId(int $companyId, int $departmentId): Collection
    {
        return $this->user->query()
            ->whereHas('companies', fn ($query) => $query->where('company_id', $companyId))
            ->whereHas('departments', fn ($query) => $query->where('department_id', $departmentId))
            ->with(['roles', 'departments'])
            ->orderBy('name')
            ->get();
    }

    /**
     * 従業員コードの最大値を取得
     *
     * @return string|null 最大の従業員コード、または存在しない場合はnull
     */
    public function getMaxEmployeeCode(): ?string
    {
        return $this->user->query()
            ->whereNotNull('employee_code')
            ->max('employee_code');
    }

    /**
     * 会社内で従業員コードが既に存在するかチェック
     *
     * @param  int  $companyId  会社ID
     * @param  string  $employeeCode  従業員コード
     * @param  int|null  $excludeUserId  除外するユーザーID（更新時に自分自身を除外）
     */
    public function existsByEmployeeCodeInCompany(int $companyId, string $employeeCode, ?int $excludeUserId = null): bool
    {
        $query = $this->user->query()
            ->whereHas('companies', function ($q) use ($companyId) {
                $q->where('companies.id', $companyId);
            })
            ->where('employee_code', $employeeCode);

        if ($excludeUserId !== null) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
    }

    /**
     * 会社内の管理者（role_id=1）の数を取得
     *
     * @param  int  $companyId  会社ID
     */
    public function countAdminsByCompanyId(int $companyId): int
    {
        return $this->user->query()
            ->whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))
            ->whereHas('roles', fn ($q) => $q->where('role_id', 1))
            ->count();
    }

    /**
     * 会社内の従業員コードの最大値を取得
     *
     * @param  int  $companyId  会社ID
     * @return string|null 最大の従業員コード、または存在しない場合はnull
     */
    public function getMaxEmployeeCodeByCompany(int $companyId): ?string
    {
        return $this->user->query()
            ->whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))
            ->whereNotNull('employee_code')
            ->max('employee_code');
    }

    /**
     * 会社内の全従業員コードを取得
     *
     * @param  int  $companyId  会社ID
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getEmployeeCodesByCompany(int $companyId): \Illuminate\Support\Collection
    {
        return $this->user->query()
            ->whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))
            ->whereNotNull('employee_code')
            ->pluck('employee_code');
    }

    /**
     * 全ユーザーの個人コード一覧を取得
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getAllEmployeeCodes(): \Illuminate\Support\Collection
    {
        return $this->user->query()
            ->whereNotNull('employee_code')
            ->pluck('employee_code');
    }
}

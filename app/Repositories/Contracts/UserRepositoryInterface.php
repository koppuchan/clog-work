<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ユーザーリポジトリインターフェース
 */
interface UserRepositoryInterface
{
    /**
     * IDでユーザーを取得
     *
     * @param  int  $id  ユーザーID
     */
    public function findById(int $id): ?User;

    /**
     * FeliCa の IDm と会社IDでユーザーを取得
     *
     * @param  string  $idm  FeliCa IDm（16進数16桁）
     * @param  int  $companyId  会社ID
     */
    public function findByFelicaIdm(string $idm, int $companyId): ?User;

    /**
     * 打刻画面用に、在籍中のユーザーを必要な列だけ取得する
     *
     * @param  int  $companyId  会社ID
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function findActiveForStampByCompanyId(int $companyId): \Illuminate\Database\Eloquent\Collection;

    /**
     * IDでユーザーをリレーションと共に取得
     *
     * @param  int  $id  ユーザーID
     */
    public function findByIdWithRelations(int $id): ?User;

    /**
     * メールアドレスでユーザーを取得
     *
     * @param  string  $email  メールアドレス
     */
    public function findByEmail(string $email): ?User;

    /**
     * 従業員コードと会社IDでユーザーを取得
     *
     * @param  string  $employeeCode  従業員コード
     * @param  int  $companyId  会社ID
     */
    public function findByEmployeeCodeAndCompanyId(string $employeeCode, int $companyId): ?User;

    /**
     * 全てのユーザーを取得
     *
     * @return Collection<int, User>
     */
    public function getAll(): Collection;

    /**
     * 会社IDでユーザーを取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, User>
     */
    public function findByCompanyId(int $companyId): Collection;

    /**
     * シフト表示用に会社IDと対象日付でユーザーを取得
     * 対象日付時点で在籍しているユーザーのみを返す
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日付（Y-m-d形式）
     * @return Collection<int, User>
     */
    public function findByCompanyIdForShift(int $companyId, string $targetDate): Collection;

    /**
     * ページネーション付きでユーザーを取得
     *
     * @param  int  $perPage  1ページあたりの件数
     * @return LengthAwarePaginator<User>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * ユーザーを作成
     *
     * @param  array<string, mixed>  $data  ユーザーデータ
     */
    public function create(array $data): User;

    /**
     * ユーザーを更新
     *
     * @param  int  $id  ユーザーID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): User;

    /**
     * ユーザーを削除
     *
     * @param  int  $id  ユーザーID
     */
    public function delete(int $id): bool;

    /**
     * 部署でユーザーを取得
     *
     * @param  string  $department  部署名
     * @return Collection<int, User>
     */
    public function findByDepartment(string $department): Collection;

    /**
     * ロールでユーザーを取得
     *
     * @param  int  $roleId  ロールID
     * @return Collection<int, User>
     */
    public function findByRole(int $roleId): Collection;

    /**
     * ユーザーにロールを割り当て
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $roleId  ロールID
     */
    public function attachRole(int $userId, int $roleId): void;

    /**
     * ユーザーからロールを削除
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $roleId  ロールID
     */
    public function detachRole(int $userId, int $roleId): void;

    /**
     * ユーザーのロールを同期
     *
     * @param  int  $userId  ユーザーID
     * @param  array<int>  $roleIds  ロールIDの配列
     */
    public function syncRoles(int $userId, array $roleIds): void;

    /**
     * ユーザーに会社を紐付け
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $companyId  会社ID
     * @param  bool  $isPrimary  主要な会社かどうか
     */
    public function attachCompany(int $userId, int $companyId, bool $isPrimary = true): void;

    /**
     * ユーザーに部署を紐付け
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $departmentId  部署ID
     * @param  bool  $isPrimary  主要な部署かどうか
     */
    public function attachDepartment(int $userId, int $departmentId, bool $isPrimary = true): void;

    /**
     * ユーザーの勤務可能曜日を取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, bool> 曜日名をキーとした配列
     */
    public function getAvailableWorkDays(int $userId): array;

    /**
     * ユーザーのシフトパターンを取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, int|null> 曜日名をキーとした配列
     */
    public function getShiftPatterns(int $userId): array;

    /**
     * ユーザーの勤務可能曜日を保存
     *
     * @param  int  $userId  ユーザーID
     * @param  array<string, bool>  $availableWorkDays  曜日名をキーとした配列
     */
    public function syncAvailableWorkDays(int $userId, array $availableWorkDays): void;

    /**
     * ユーザーのシフトパターンを保存
     *
     * @param  int  $userId  ユーザーID
     * @param  array<string, int|null>  $shiftPatterns  曜日名をキーとした配列
     */
    public function syncShiftPatterns(int $userId, array $shiftPatterns): void;

    /**
     * 複数ユーザーのシフトパターンを一括取得
     *
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @return Collection<int, \App\Models\UserShiftPattern>
     */
    public function getBulkShiftPatterns(array $userIds): Collection;

    /**
     * 複数ユーザーの勤務可能曜日を一括取得
     *
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @return Collection<int, \App\Models\UserAvailableWorkDay>
     */
    public function getBulkAvailableWorkDays(array $userIds): Collection;

    /**
     * 部署IDでユーザーを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $departmentId  部署ID
     * @return Collection<int, User>
     */
    public function findByDepartmentId(int $companyId, int $departmentId): Collection;

    /**
     * 従業員コードの最大値を取得
     *
     * @return string|null 最大の従業員コード、または存在しない場合はnull
     */
    public function getMaxEmployeeCode(): ?string;

    /**
     * 会社内で従業員コードが既に存在するかチェック
     *
     * @param  int  $companyId  会社ID
     * @param  string  $employeeCode  従業員コード
     * @param  int|null  $excludeUserId  除外するユーザーID（更新時に自分自身を除外）
     */
    public function existsByEmployeeCodeInCompany(int $companyId, string $employeeCode, ?int $excludeUserId = null): bool;

    /**
     * 会社内の管理者（role_id=1）の数を取得
     *
     * @param  int  $companyId  会社ID
     */
    public function countAdminsByCompanyId(int $companyId): int;

    /**
     * 会社内の従業員コードの最大値を取得
     *
     * @param  int  $companyId  会社ID
     * @return string|null 最大の従業員コード、または存在しない場合はnull
     */
    public function getMaxEmployeeCodeByCompany(int $companyId): ?string;

    /**
     * 会社内の全従業員コードを取得
     *
     * @param  int  $companyId  会社ID
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getEmployeeCodesByCompany(int $companyId): \Illuminate\Support\Collection;

    /**
     * 全ユーザーの個人コード一覧を取得
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getAllEmployeeCodes(): \Illuminate\Support\Collection;
}

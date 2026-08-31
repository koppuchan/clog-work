<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Collection;

/**
 * シフトリポジトリインターフェース
 */
interface ShiftRepositoryInterface
{
    /**
     * IDでシフトを取得
     *
     * @param  int  $id  シフトID
     */
    public function findById(int $id): ?Shift;

    /**
     * 会社IDとユーザーIDと日付範囲でシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function findByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate): Collection;

    /**
     * 会社IDと日付範囲でシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function findByCompanyIdAndDateRange(int $companyId, string $startDate, string $endDate): Collection;

    /**
     * 部署IDと日付範囲でシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $departmentId  部署ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function findByDepartmentIdAndDateRange(int $companyId, int $departmentId, string $startDate, string $endDate): Collection;

    /**
     * シフトを作成
     *
     * @param  array<string, mixed>  $data  シフトデータ
     */
    public function create(array $data): Shift;

    /**
     * シフトを更新
     *
     * @param  int  $id  シフトID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): Shift;

    /**
     * シフトを削除
     *
     * @param  int  $id  シフトID
     */
    public function delete(int $id): bool;

    /**
     * 複数のシフトを一括作成・更新
     *
     * @param  int  $companyId  会社ID
     * @param  array<int, array<string, mixed>>  $shifts  シフトデータの配列
     */
    public function upsertMany(int $companyId, array $shifts): void;
}

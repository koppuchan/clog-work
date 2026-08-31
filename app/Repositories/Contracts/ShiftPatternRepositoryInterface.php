<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ShiftPattern;
use Illuminate\Database\Eloquent\Collection;

/**
 * シフトパターンリポジトリインターフェース
 */
interface ShiftPatternRepositoryInterface
{
    /**
     * 会社IDでシフトパターンを取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, ShiftPattern>
     */
    public function findByCompanyId(int $companyId): Collection;

    /**
     * IDでシフトパターンを取得
     *
     * @param  int  $id  シフトパターンID
     */
    public function findById(int $id): ?ShiftPattern;

    /**
     * IDでシフトパターンをリレーションと共に取得
     *
     * @param  int  $id  シフトパターンID
     */
    public function findByIdWithRelations(int $id): ?ShiftPattern;

    /**
     * シフトパターンを作成
     *
     * @param  array<string, mixed>  $data  シフトパターンデータ
     */
    public function create(array $data): ShiftPattern;

    /**
     * シフトパターンを更新
     *
     * @param  int  $id  シフトパターンID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): ShiftPattern;

    /**
     * シフトパターンを削除
     *
     * @param  int  $id  シフトパターンID
     */
    public function delete(int $id): bool;
}

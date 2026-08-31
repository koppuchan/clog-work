<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;

/**
 * 会社リポジトリインターフェース
 */
interface CompanyRepositoryInterface
{
    /**
     * IDで会社を取得
     *
     * @param  int  $id  会社ID
     */
    public function findById(int $id): ?Company;

    /**
     * 会社コードで会社を取得
     *
     * @param  string  $companyCode  会社コード
     */
    public function findByCompanyCode(string $companyCode): ?Company;

    /**
     * IDで会社をリレーションと共に取得
     *
     * @param  int  $id  会社ID
     */
    public function findByIdWithRelations(int $id): ?Company;

    /**
     * 全ての会社を取得
     *
     * @return Collection<int, Company>
     */
    public function getAll(): Collection;

    /**
     * 会社を作成
     *
     * @param  array<string, mixed>  $data  会社データ
     */
    public function create(array $data): Company;

    /**
     * 会社を更新
     *
     * @param  int  $id  会社ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): Company;

    /**
     * 会社を削除
     *
     * @param  int  $id  会社ID
     */
    public function delete(int $id): bool;

    /**
     * 会社の定休日（曜日ID）を取得
     *
     * @param  int  $companyId  会社ID
     * @return array<int> 定休日の曜日IDの配列
     */
    public function getRegularHolidayWeekdayIds(int $companyId): array;

    /**
     * 最大の会社コードを取得
     *
     * @return string|null 最大の会社コード（数値文字列）、存在しない場合はnull
     */
    public function getMaxCompanyCode(): ?string;

    /**
     * UUIDで会社を取得
     *
     * @param  string  $uuid  会社UUID
     */
    public function findByUuid(string $uuid): ?Company;
}

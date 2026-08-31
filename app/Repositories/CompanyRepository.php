<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * 会社リポジトリ実装
 */
class CompanyRepository implements CompanyRepositoryInterface
{
    public function __construct(
        private readonly Company $company
    ) {}

    /**
     * IDで会社を取得
     *
     * @param  int  $id  会社ID
     */
    public function findById(int $id): ?Company
    {
        return $this->company->query()->find($id);
    }

    /**
     * 会社コードで会社を取得
     *
     * @param  string  $companyCode  会社コード
     */
    public function findByCompanyCode(string $companyCode): ?Company
    {
        return $this->company->query()
            ->where('company_code', $companyCode)
            ->first();
    }

    /**
     * IDで会社をリレーションと共に取得
     *
     * @param  int  $id  会社ID
     */
    public function findByIdWithRelations(int $id): ?Company
    {
        return $this->company->query()
            ->with([
                'regularHolidays',
                'shiftRoundingSettings',
                'departments',
            ])
            ->find($id);
    }

    /**
     * 全ての会社を取得
     *
     * @return Collection<int, Company>
     */
    public function getAll(): Collection
    {
        return $this->company->query()->get();
    }

    /**
     * 会社を作成
     *
     * @param  array<string, mixed>  $data  会社データ
     */
    public function create(array $data): Company
    {
        return $this->company->query()->create($data);
    }

    /**
     * 会社を更新
     *
     * @param  int  $id  会社ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): Company
    {
        $company = $this->findById($id);

        if (! $company) {
            throw new \RuntimeException("Company with ID {$id} not found");
        }

        $company->update($data);

        return $company->fresh();
    }

    /**
     * 会社を削除
     *
     * @param  int  $id  会社ID
     */
    public function delete(int $id): bool
    {
        $company = $this->findById($id);

        if (! $company) {
            return false;
        }

        return $company->delete();
    }

    /**
     * 会社の定休日（曜日ID）を取得
     *
     * @param  int  $companyId  会社ID
     * @return array<int> 定休日の曜日IDの配列
     */
    public function getRegularHolidayWeekdayIds(int $companyId): array
    {
        $company = $this->company->query()
            ->with('regularHolidays')
            ->find($companyId);

        if (! $company) {
            return [];
        }

        return $company->regularHolidays->pluck('weekday_id')->toArray();
    }

    /**
     * 最大の会社コードを取得
     *
     * @return string|null 最大の会社コード（数値文字列）、存在しない場合はnull
     */
    public function getMaxCompanyCode(): ?string
    {
        return $this->company->query()
            ->whereNotNull('company_code')
            ->max('company_code');
    }

    /**
     * UUIDで会社を取得
     *
     * @param  string  $uuid  会社UUID
     */
    public function findByUuid(string $uuid): ?Company
    {
        return $this->company->query()
            ->where('uuid', $uuid)
            ->first();
    }
}

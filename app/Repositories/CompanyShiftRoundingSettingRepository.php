<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CompanyShiftRoundingSetting;
use App\Repositories\Contracts\CompanyShiftRoundingSettingRepositoryInterface;

class CompanyShiftRoundingSettingRepository implements CompanyShiftRoundingSettingRepositoryInterface
{
    public function __construct(
        private readonly CompanyShiftRoundingSetting $model
    ) {}

    /**
     * 会社IDから丸め設定を取得
     *
     * @param  int  $companyId  会社ID
     */
    public function findByCompanyId(int $companyId): ?CompanyShiftRoundingSetting
    {
        return $this->model->query()
            ->with('roundingUnit')
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * 会社の丸め単位（分）を取得
     *
     * @param  int  $companyId  会社ID
     * @return int|null 設定がない場合はnull
     */
    public function getRoundingMinutes(int $companyId): ?int
    {
        $setting = $this->findByCompanyId($companyId);

        return $setting?->roundingUnit?->minutes;
    }
}

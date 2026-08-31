<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CompanyShiftRoundingSetting;

interface CompanyShiftRoundingSettingRepositoryInterface
{
    /**
     * 会社IDから丸め設定を取得
     *
     * @param  int  $companyId  会社ID
     */
    public function findByCompanyId(int $companyId): ?CompanyShiftRoundingSetting;

    /**
     * 会社の丸め単位（分）を取得
     *
     * @param  int  $companyId  会社ID
     * @return int|null 設定がない場合はnull
     */
    public function getRoundingMinutes(int $companyId): ?int;
}

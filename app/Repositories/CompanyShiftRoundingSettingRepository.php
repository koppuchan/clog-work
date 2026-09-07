<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CompanyShiftRoundingSetting;
use App\Repositories\Contracts\CompanyShiftRoundingSettingRepositoryInterface;

class CompanyShiftRoundingSettingRepository implements CompanyShiftRoundingSettingRepositoryInterface
{
    /**
     * 会社IDごとの丸め設定キャッシュ（リクエスト内で使い回す）
     *
     * 打刻のたびにTimeRoundingServiceとDailyWorkSummaryBatchServiceの両方から
     * 同じ会社の設定を引くため、リクエスト内（このリポジトリはscoped登録）で
     * 同じ結果を使い回し、重複クエリを避ける。
     *
     * @var array<int, CompanyShiftRoundingSetting|null>
     */
    private array $cache = [];

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
        if (array_key_exists($companyId, $this->cache)) {
            return $this->cache[$companyId];
        }

        return $this->cache[$companyId] = $this->model->query()
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

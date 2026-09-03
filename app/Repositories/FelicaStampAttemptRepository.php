<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\FelicaStampAttempt;
use App\Repositories\Contracts\FelicaStampAttemptRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * FeliCa打刻試行ログリポジトリ実装
 */
class FelicaStampAttemptRepository implements FelicaStampAttemptRepositoryInterface
{
    public function __construct(
        private readonly FelicaStampAttempt $felicaStampAttempt
    ) {}

    /**
     * 打刻試行ログを作成
     *
     * @param  array<string, mixed>  $data  ログデータ
     */
    public function create(array $data): FelicaStampAttempt
    {
        return $this->felicaStampAttempt->query()->create($data);
    }

    /**
     * 指定したIDより新しい、会社の打刻試行ログを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $sinceId  この ID より大きい試行のみ取得
     * @param  int  $limit  最大取得件数
     * @return Collection<int, FelicaStampAttempt>
     */
    public function findRecentByCompanyId(int $companyId, int $sinceId, int $limit = 20): Collection
    {
        return $this->felicaStampAttempt->query()
            ->with('user:id,name')
            ->where('company_id', $companyId)
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * 会社の最新の打刻試行ログIDを取得（ポーリング開始地点の基準に使用）
     *
     * @param  int  $companyId  会社ID
     */
    public function getLatestIdByCompanyId(int $companyId): int
    {
        return (int) $this->felicaStampAttempt->query()
            ->where('company_id', $companyId)
            ->max('id');
    }
}

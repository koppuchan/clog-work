<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ApplicationType;
use App\Repositories\Contracts\ApplicationTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * 申請タイプリポジトリ実装
 */
class ApplicationTypeRepository implements ApplicationTypeRepositoryInterface
{
    public function __construct(
        private readonly ApplicationType $applicationType
    ) {}

    /**
     * IDで申請タイプを取得
     *
     * @param  int  $id  申請タイプID
     */
    public function findById(int $id): ?ApplicationType
    {
        return $this->applicationType->query()->find($id);
    }

    /**
     * コードで申請タイプを取得
     *
     * @param  string  $code  申請タイプコード（例: 'clock-error', 'paid-leave'）
     */
    public function findByCode(string $code): ?ApplicationType
    {
        return $this->applicationType->query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 有効な申請タイプを全て取得
     *
     * @return Collection<int, ApplicationType>
     */
    public function findAllActive(): Collection
    {
        return $this->applicationType->query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }
}

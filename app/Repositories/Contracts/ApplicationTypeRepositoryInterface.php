<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ApplicationType;
use Illuminate\Database\Eloquent\Collection;

/**
 * 申請タイプリポジトリインターフェース
 */
interface ApplicationTypeRepositoryInterface
{
    /**
     * IDで申請タイプを取得
     *
     * @param  int  $id  申請タイプID
     */
    public function findById(int $id): ?ApplicationType;

    /**
     * コードで申請タイプを取得
     *
     * @param  string  $code  申請タイプコード（例: 'clock-error', 'paid-leave'）
     */
    public function findByCode(string $code): ?ApplicationType;

    /**
     * 有効な申請タイプを全て取得
     *
     * @return Collection<int, ApplicationType>
     */
    public function findAllActive(): Collection;
}

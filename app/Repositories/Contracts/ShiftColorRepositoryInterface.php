<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ShiftColor;
use Illuminate\Database\Eloquent\Collection;

interface ShiftColorRepositoryInterface
{
    /**
     * 全シフトカラーを取得
     *
     * @return Collection<int, ShiftColor>
     */
    public function findAll(): Collection;
}

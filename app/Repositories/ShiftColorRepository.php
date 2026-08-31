<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ShiftColor;
use App\Repositories\Contracts\ShiftColorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ShiftColorRepository implements ShiftColorRepositoryInterface
{
    public function __construct(
        private readonly ShiftColor $shiftColor
    ) {}

    /**
     * 全シフトカラーを取得
     *
     * @return Collection<int, ShiftColor>
     */
    public function findAll(): Collection
    {
        return $this->shiftColor->query()->orderBy('id')->get();
    }
}

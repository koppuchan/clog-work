<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 打刻したときの位置情報（スマホ打刻で端末から送られてくるもの）
 */
final class StampLocation
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?int $accuracy = null,
    ) {}
}

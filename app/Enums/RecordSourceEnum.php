<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 記録元Enum
 *
 * 打刻や勤務実績の記録元を表す
 */
enum RecordSourceEnum: int
{
    case AUTO = 1;
    case MANUAL = 2;
    case REQUEST = 3;

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::AUTO => '自動打刻',
            self::MANUAL => '手動入力',
            self::REQUEST => '申請修正',
        };
    }

    /**
     * 自動記録かどうか
     */
    public function isAuto(): bool
    {
        return $this === self::AUTO;
    }

    /**
     * 手動記録かどうか
     */
    public function isManual(): bool
    {
        return $this === self::MANUAL;
    }

    /**
     * 申請による修正かどうか
     */
    public function isRequest(): bool
    {
        return $this === self::REQUEST;
    }

    /**
     * 修正可能かどうか
     */
    public function canEdit(): bool
    {
        return $this !== self::REQUEST;
    }

    /**
     * バッジカラーを取得
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::AUTO => 'bg-blue-100 text-blue-800',
            self::MANUAL => 'bg-yellow-100 text-yellow-800',
            self::REQUEST => 'bg-green-100 text-green-800',
        };
    }
}

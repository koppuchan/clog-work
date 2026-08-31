<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 承認ステータスEnum
 *
 * 申請の承認状態を表す
 */
enum ApprovalStatusEnum: int
{
    case PENDING = 1;
    case APPROVED = 2;
    case REJECTED = 3;

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '承認待ち',
            self::APPROVED => '承認済み',
            self::REJECTED => '却下',
        };
    }

    /**
     * 承認可能かどうか
     */
    public function canApprove(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 却下可能かどうか
     */
    public function canReject(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 最終ステータスかどうか
     */
    public function isFinal(): bool
    {
        return $this === self::APPROVED || $this === self::REJECTED;
    }

    /**
     * CSSクラス名を取得
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::PENDING => 'text-yellow-600 bg-yellow-50',
            self::APPROVED => 'text-green-600 bg-green-50',
            self::REJECTED => 'text-red-600 bg-red-50',
        };
    }
}

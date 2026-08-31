<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 申請ステータスEnum
 *
 * 各種申請の処理状態を表す
 */
enum RequestStatusEnum: int
{
    case PENDING = 1;
    case APPROVED = 2;
    case REJECTED = 3;
    case CANCELLED = 4;

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '申請中',
            self::APPROVED => '承認',
            self::REJECTED => '却下',
            self::CANCELLED => '取消',
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
     * 取消可能かどうか（申請中または承認済み）
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::PENDING, self::APPROVED], true);
    }

    /**
     * 差し戻し可能かどうか（承認済みのみ）
     */
    public function canRevert(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * 処理済みかどうか
     */
    public function isProcessed(): bool
    {
        return $this !== self::PENDING;
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
            self::CANCELLED => 'text-gray-600 bg-gray-50',
        };
    }
}

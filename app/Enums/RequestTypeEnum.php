<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 申請タイプEnum
 *
 * 各種申請の種類を表す
 */
enum RequestTypeEnum: int
{
    case LEAVE = 1;
    case LATE = 2;
    case EARLY_LEAVE = 3;
    case ABSENCE = 4;
    case BREAK = 5;
    case OTHER = 6;
    case CLOCK_ERROR = 7;
    case OVERTIME = 8;

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::LEAVE => '休暇',
            self::LATE => '遅刻',
            self::EARLY_LEAVE => '早退',
            self::ABSENCE => '欠席',
            self::BREAK => '休憩申請',
            self::OTHER => 'その他',
            self::CLOCK_ERROR => '打刻間違い',
            self::OVERTIME => '残業申請',
        };
    }

    /**
     * 時間指定が必要かどうか
     */
    public function requiresTime(): bool
    {
        return match ($this) {
            self::LATE, self::EARLY_LEAVE, self::BREAK => true,
            self::LEAVE, self::ABSENCE, self::OTHER => false,
        };
    }

    /**
     * アイコン名を取得
     */
    public function icon(): string
    {
        return match ($this) {
            self::LEAVE => 'calendar-days',
            self::LATE => 'clock',
            self::EARLY_LEAVE => 'clock',
            self::ABSENCE => 'user-minus',
            self::BREAK => 'coffee',
            self::OTHER => 'ellipsis',
        };
    }
}

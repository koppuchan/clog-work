<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * アラートレベルEnum
 *
 * 労務アラートの重要度を表す
 */
enum AlertLevelEnum: int
{
    case CAUTION = 1;
    case WARNING = 2;

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::CAUTION => '注意',
            self::WARNING => '警告',
        };
    }

    /**
     * コードを取得
     */
    public function code(): string
    {
        return match ($this) {
            self::CAUTION => 'caution',
            self::WARNING => 'warning',
        };
    }

    /**
     * CSSクラス名を取得
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::CAUTION => 'text-yellow-600 bg-yellow-50',
            self::WARNING => 'text-red-600 bg-red-50',
        };
    }

    /**
     * アイコン名を取得
     */
    public function icon(): string
    {
        return match ($this) {
            self::CAUTION => 'alert-circle',
            self::WARNING => 'alert-triangle',
        };
    }

    /**
     * コードから取得
     */
    public static function fromCode(string $code): ?self
    {
        return match ($code) {
            'caution' => self::CAUTION,
            'warning' => self::WARNING,
            default => null,
        };
    }
}

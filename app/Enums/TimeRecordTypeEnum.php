<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 打刻種別Enum
 *
 * 打刻レコードの種類を表す
 */
enum TimeRecordTypeEnum: int
{
    case WORK_START = 1;
    case WORK_END = 2;
    case WORK_END_NEXT_DAY = 3;
    case BREAK_START = 4;
    case BREAK_END = 5;

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::WORK_START => '勤務開始',
            self::WORK_END => '勤務終了',
            self::WORK_END_NEXT_DAY => '日付越え終了',
            self::BREAK_START => '休憩開始',
            self::BREAK_END => '休憩終了',
        };
    }

    /**
     * 打刻完了時に画面へ表示する文言を取得
     *
     * 日付をまたいだ退勤は通常の退勤と区別できるようにする。
     * 打刻した本人が「いつの勤務として記録されたか」を判断できるようにするため。
     */
    public function stampedMessage(): string
    {
        return match ($this) {
            self::WORK_START => '出勤を記録しました。',
            self::WORK_END => '退勤を記録しました。',
            self::WORK_END_NEXT_DAY => '退勤（日付越え）を記録しました。',
            self::BREAK_START => '休憩開始を記録しました。',
            self::BREAK_END => '休憩終了を記録しました。',
        };
    }

    /**
     * 勤務開始かどうか
     */
    public function isWorkStart(): bool
    {
        return $this === self::WORK_START;
    }

    /**
     * 勤務終了かどうか（通常・日付越え含む）
     */
    public function isWorkEnd(): bool
    {
        return $this === self::WORK_END || $this === self::WORK_END_NEXT_DAY;
    }

    /**
     * 休憩開始かどうか
     */
    public function isBreakStart(): bool
    {
        return $this === self::BREAK_START;
    }

    /**
     * 休憩終了かどうか
     */
    public function isBreakEnd(): bool
    {
        return $this === self::BREAK_END;
    }

    /**
     * 休憩関連かどうか
     */
    public function isBreak(): bool
    {
        return $this === self::BREAK_START || $this === self::BREAK_END;
    }

    /**
     * 丸め方向が切り上げかどうかを判定
     *
     * 労働者不利方式:
     * - 切り上げ: WORK_START, BREAK_END（勤務開始が遅くなる/休憩終了が遅れる）
     * - 切り捨て: WORK_END, WORK_END_NEXT_DAY, BREAK_START（勤務終了が早まる/休憩開始が早まる）
     */
    public function shouldRoundUp(): bool
    {
        return match ($this) {
            self::WORK_START, self::BREAK_END => true,
            self::WORK_END, self::WORK_END_NEXT_DAY, self::BREAK_START => false,
        };
    }

    /**
     * アイコン名を取得
     */
    public function icon(): string
    {
        return match ($this) {
            self::WORK_START => 'arrow-right-to-bracket',
            self::WORK_END, self::WORK_END_NEXT_DAY => 'arrow-right-from-bracket',
            self::BREAK_START => 'coffee',
            self::BREAK_END => 'circle-check',
        };
    }
}

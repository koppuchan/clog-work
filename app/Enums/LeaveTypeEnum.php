<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 休暇種別Enum
 *
 * daily_work_summariesのleave_typeカラムに格納される値
 */
enum LeaveTypeEnum: int
{
    case PAID_LEAVE = 1;
    case SPECIAL_LEAVE = 2;
    case ABSENCE = 3;

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::PAID_LEAVE => '有給休暇',
            self::SPECIAL_LEAVE => '特別休暇',
            self::ABSENCE => '欠勤',
        };
    }

    /**
     * 申請種別（requests.type）からLeaveTypeに変換
     *
     * @param  int  $applicationType  申請種別（1=有給休暇, 5=特別休暇, 6=欠勤, 9=半日有給, 10=時間有給）
     */
    public static function fromApplicationType(int $applicationType): ?self
    {
        return match ($applicationType) {
            1, 9, 10 => self::PAID_LEAVE,
            5 => self::SPECIAL_LEAVE,
            6 => self::ABSENCE,
            default => null,
        };
    }

    /**
     * 休暇申請かどうかを判定
     *
     * @param  int  $applicationType  申請種別
     */
    public static function isLeaveApplication(int $applicationType): bool
    {
        return in_array($applicationType, [1, 5, 6, 9, 10], true);
    }
}

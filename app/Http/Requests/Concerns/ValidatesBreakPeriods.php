<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * 休憩時間が勤務時間の範囲に収まっているかを検証する
 *
 * 夜勤（22:00〜06:00 など）では勤務が日付をまたぐため、時刻の文字列を
 * そのまま比較すると 02:00 の休憩が範囲外と判定されてしまう。
 * 勤務終了が勤務開始以前であれば翌日とみなし、連続した分単位の時間軸に
 * 直したうえで比較する。
 */
trait ValidatesBreakPeriods
{
    /**
     * 勤務時間と休憩時間の整合を検証する
     */
    protected function validateBreakPeriodsWithinWork(Validator $validator): void
    {
        $workStart = $this->toMinutes($this->input('work_start'));
        $workEnd = $this->toMinutes($this->input('work_end'));

        // 勤務時間が片方しか入力されていない場合は範囲を判定できない
        if ($workStart === null || $workEnd === null) {
            return;
        }

        // 勤務終了が開始以前なら日跨ぎとして扱う
        if ($workEnd <= $workStart) {
            $workEnd += self::MINUTES_PER_DAY;
        }

        $periods = $this->input('break_periods');

        if (! is_array($periods)) {
            return;
        }

        foreach ($periods as $index => $period) {
            $start = $this->toMinutes($period['start'] ?? null);
            $end = $this->toMinutes($period['end'] ?? null);

            if ($start === null || $end === null) {
                continue;
            }

            // 同じ時刻は日跨ぎの補正を掛ける前に判定する。
            // 補正してしまうと 24 時間の休憩として扱われ、範囲外と誤判定されるため。
            if ($start === $end) {
                $validator->errors()->add(
                    "break_periods.{$index}.end",
                    sprintf('%d つ目の休憩は開始時刻と終了時刻が同じです。', $index + 1),
                );

                continue;
            }

            // 勤務開始より前の時刻は、日跨ぎ勤務における翌日分とみなす
            if ($start < $workStart) {
                $start += self::MINUTES_PER_DAY;
            }

            if ($end <= $start) {
                $end += self::MINUTES_PER_DAY;
            }

            if ($start < $workStart || $end > $workEnd) {
                $validator->errors()->add(
                    "break_periods.{$index}.start",
                    sprintf('%d つ目の休憩時間が勤務時間の範囲外です。勤務時間内で入力してください。', $index + 1),
                );
            }
        }
    }

    /**
     * 1日の分数
     */
    private const MINUTES_PER_DAY = 24 * 60;

    /**
     * HH:MM 形式の時刻を、0時からの分数に変換する
     */
    private function toMinutes(mixed $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return null;
        }

        return ((int) $m[1] * 60) + (int) $m[2];
    }
}

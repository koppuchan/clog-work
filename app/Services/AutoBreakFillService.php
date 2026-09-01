<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ShiftPattern;
use Carbon\CarbonImmutable;

/**
 * 休憩打刻がない日にシフトの休憩時刻を補う
 *
 * 休憩の打刻を忘れても所定の休憩が控除されるようにするための機能で、
 * シフトパターンごとに有効・無効を切り替える。
 *
 * 実打刻を上書きしないため、休憩の打刻が1件でもある日は適用しない。
 * 片方だけの打刻は「打ち忘れ」ではなく修正すべき状態なので、
 * 画面側で欠けている側を示す（--:--）運用とする。
 *
 * 時間帯を持たない分数のみの休憩設定（break_mode=1）は、
 * 休憩の開始・終了を特定できないため対象外とする。
 */
class AutoBreakFillService
{
    /**
     * シフトの休憩時刻を補う対象かどうか
     *
     * @param  bool  $hasAnyBreakRecord  休憩の打刻が1件でもあるか
     */
    public function isApplicable(?ShiftPattern $pattern, bool $hasAnyBreakRecord): bool
    {
        if ($hasAnyBreakRecord) {
            return false;
        }

        if (! $pattern?->auto_fill_break) {
            return false;
        }

        return $pattern->break_start !== null && $pattern->break_end !== null;
    }

    /**
     * 補完する休憩時間帯を求める
     *
     * 早退などで休憩時刻が実労働時間の外に出る場合は、重なる範囲だけを控除する。
     * 重なりがなければ補完しない。
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    public function resolvePeriod(
        ShiftPattern $pattern,
        CarbonImmutable $workDate,
        ?CarbonImmutable $workStart,
        ?CarbonImmutable $workEnd,
    ): ?array {
        if ($workStart === null || $workEnd === null) {
            return null;
        }

        $breakStart = $workDate->setTimeFromTimeString((string) $pattern->break_start);
        $breakEnd = $workDate->setTimeFromTimeString((string) $pattern->break_end);

        // 休憩が日を跨ぐ場合（例: 23:30〜00:30）
        if ($breakEnd->lte($breakStart)) {
            $breakEnd = $breakEnd->addDay();
        }

        // 夜勤で休憩が翌日側にある場合（例: 01:00〜02:00）は勤務開始より後ろへ寄せる
        if ($breakEnd->lte($workStart)) {
            $breakStart = $breakStart->addDay();
            $breakEnd = $breakEnd->addDay();
        }

        // 実労働時間と重なる範囲のみを対象にする
        $start = $breakStart->greaterThan($workStart) ? $breakStart : $workStart;
        $end = $breakEnd->lessThan($workEnd) ? $breakEnd : $workEnd;

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * 補完する休憩時間（分）
     */
    public function fillMinutes(
        ShiftPattern $pattern,
        CarbonImmutable $workDate,
        ?CarbonImmutable $workStart,
        ?CarbonImmutable $workEnd,
    ): int {
        $period = $this->resolvePeriod($pattern, $workDate, $workStart, $workEnd);

        if ($period === null) {
            return 0;
        }

        return (int) $period['start']->diffInMinutes($period['end']);
    }
}

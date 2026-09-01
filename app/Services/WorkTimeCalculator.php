<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 労働時間・時間外・遅早の算出
 *
 * 櫻本さまに確定いただいた定義に基づく。
 *
 *   時間外   = max(0, 実労働時間 − 所定労働時間)
 *   遅早時間 = max(0, 所定労働時間 −（実労働時間 ＋ 有給みなし時間）)
 *
 *   実労働時間   = (退勤 − 出勤) − 実際の休憩
 *   所定労働時間 = (シフト終業 − シフト始業) − シフト上の休憩
 *
 * 半日有給・時間有給は当該時間を所定労働時間とみなす。ただしこれは
 * 遅早を打ち消すための扱いであり、時間外を発生させるものではない。
 * そのため時間外は有給みなし時間を含めずに算出する。
 */
class WorkTimeCalculator
{
    private const MINUTES_PER_DAY = 24 * 60;

    /**
     * 所定労働時間（分）を求める
     *
     * シフト終業が始業以前であれば夜勤とみなして翌日として扱う。
     *
     * @param  string|null  $scheduledStart  シフト始業（H:i）
     * @param  string|null  $scheduledEnd  シフト終業（H:i）
     * @param  int  $scheduledBreakMinutes  シフト上の休憩（分）
     */
    public function scheduledWorkMinutes(
        ?string $scheduledStart,
        ?string $scheduledEnd,
        int $scheduledBreakMinutes = 0,
    ): int {
        $start = $this->toMinutes($scheduledStart);
        $end = $this->toMinutes($scheduledEnd);

        if ($start === null || $end === null) {
            return 0;
        }

        if ($end <= $start) {
            $end += self::MINUTES_PER_DAY;
        }

        return max(0, ($end - $start) - max(0, $scheduledBreakMinutes));
    }

    /**
     * 時間外（分）を求める
     *
     * 所定労働時間を超えて実際に働いた分のみを対象とする。
     * シフトが設定されていない日は所定労働時間を持たないため、
     * 時間外ではなく休日勤務として扱う（呼び出し側の責務）。
     *
     * @param  int  $netWorkMinutes  実労働時間（分）
     * @param  int  $scheduledWorkMinutes  所定労働時間（分）
     */
    public function overtimeMinutes(int $netWorkMinutes, int $scheduledWorkMinutes): int
    {
        if ($scheduledWorkMinutes <= 0) {
            return 0;
        }

        return max(0, $netWorkMinutes - $scheduledWorkMinutes);
    }

    /**
     * 遅早（分）を求める
     *
     * 所定労働時間に届かなかった分。半日有給・時間有給を取得した場合は
     * その時間を働いたものとみなして差し引く。
     *
     * @param  int  $netWorkMinutes  実労働時間（分）
     * @param  int  $scheduledWorkMinutes  所定労働時間（分）
     * @param  int  $paidLeaveMinutes  有給みなし時間（分）
     */
    public function shortfallMinutes(
        int $netWorkMinutes,
        int $scheduledWorkMinutes,
        int $paidLeaveMinutes = 0,
    ): int {
        if ($scheduledWorkMinutes <= 0) {
            return 0;
        }

        return max(0, $scheduledWorkMinutes - ($netWorkMinutes + max(0, $paidLeaveMinutes)));
    }

    /**
     * H:i 形式の時刻を0時からの分数へ変換する
     */
    private function toMinutes(?string $time): ?int
    {
        if ($time === null || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return null;
        }

        return ((int) $m[1] * 60) + (int) $m[2];
    }
}

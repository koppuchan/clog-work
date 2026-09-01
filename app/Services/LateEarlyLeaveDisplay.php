<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 遅刻早退を表示するかどうかを判定する
 *
 * 承認済みの休暇がある日は遅刻早退として扱わない。
 * 半日有給で午後から出勤した場合、始業時刻との差をそのまま遅刻として
 * 出してしまうと、休暇を取ったこと自体が遅刻として集計されてしまう。
 *
 * 休暇の申請は承認時に勤務実績へ反映され、一日有給・半日有給・時間有給・
 * 特別休暇・欠勤のいずれも leave_type が入る。
 *
 * 集計値そのものは打刻に基づく事実として残し、表示のときだけ伏せる。
 */
class LateEarlyLeaveDisplay
{
    /**
     * その日の遅刻早退を表示するか
     *
     * @param  object|null  $summary  日次の勤務実績
     */
    public function shouldShow(?object $summary): bool
    {
        if ($summary === null) {
            return false;
        }

        return ($summary->leave_type ?? null) === null;
    }

    /**
     * 表示する遅刻時間（分）
     */
    public function lateMinutes(?object $summary): int
    {
        return $this->shouldShow($summary) ? (int) ($summary->late_minutes ?? 0) : 0;
    }

    /**
     * 表示する早退時間（分）
     */
    public function earlyLeaveMinutes(?object $summary): int
    {
        return $this->shouldShow($summary) ? (int) ($summary->early_leave_minutes ?? 0) : 0;
    }
}

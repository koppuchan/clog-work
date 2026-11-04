<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TimeRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * 同じ日に2回目の出勤をした日付越え勤務が、どの勤務日のものかを決める
 *
 * 打刻は実際の日時で保存し、勤務日は「出勤した日」として扱う。そのため、夜勤の
 * 出勤と退勤が日付をまたいでも、退勤は出勤した日の勤務に寄せて集計される。
 *
 * ところが、日付が変わった直後に始まる勤務（例: 0:00〜8:00のシフトに 0:10 出勤）の
 * あとに、翌日の同じ勤務へ日付が変わる前に早めに出勤する（例: 23:45 出勤 →
 * 翌日 7:45 退勤）ことがある。どちらも出勤が同じ日付になり、勤務実績は1日1件のため、
 * 2つの勤務が1つにつながって集計されていた（0:10 から翌 7:45 までの1勤務になる）。
 *
 * この場合は、同じ日付ですでに別の勤務をしたあとに出勤し、日付をまたいで退勤した
 * 勤務を、翌日の勤務として扱う。上の例では 11/3 の勤務は 0:10〜8:00、11/4 の勤務は
 * 前日 23:45〜7:45 になる。それ以外の打刻（1日に勤務が1回だけの日、同じ日の中で
 * 終わる2回目の勤務など）は、これまでどおり出勤した日の勤務として扱う。
 */
class WorkSessionAttribution
{
    public const EDIT_BLOCKED_MESSAGE = 'この日は、日付をまたいで同じ日に2回出勤した勤務を含むため、画面からは編集できません。修正が必要な場合は、システム管理者にご連絡ください。';

    /**
     * 翌日の勤務として扱う打刻の、勤務日を返す
     *
     * 出勤、その勤務中の休憩、退勤のすべてを、同じ勤務日にまとめて返す。
     * 対象にならない打刻（これまでどおり出勤した日の勤務）は含まれない。
     *
     * 出勤した日の打刻を漏れなく渡すこと。対象日の前日の打刻も含めておかないと、
     * 前日に出勤した勤務が翌日の勤務に寄せられるか判定できない。
     *
     * @param  Collection<int, TimeRecord>  $records  同じ従業員の打刻（順不同）
     * @return array<int, string> 打刻IDをキー、勤務日（Y-m-d）を値とした連想配列
     */
    public function movedWorkDates(Collection $records): array
    {
        $takenDates = [];
        $moved = [];

        foreach ($this->sessions($records) as $session) {
            $startDate = $session['start']->record_time->format('Y-m-d');
            $endDate = $session['end']?->record_time->format('Y-m-d');
            $endsOnLaterDate = $endDate !== null && $endDate !== $startDate;

            if ($endsOnLaterDate && isset($takenDates[$startDate])) {
                $workDate = CarbonImmutable::parse($startDate)->addDay()->format('Y-m-d');
                foreach ($session['records'] as $record) {
                    $moved[$record->id] = $workDate;
                }
                $takenDates[$workDate] = true;

                continue;
            }

            $takenDates[$startDate] = true;
        }

        return $moved;
    }

    /**
     * 指定した勤務日が、翌日の勤務として扱う勤務に関わっているか
     *
     * 勤務日の勤務そのものが翌日へ寄せられている場合と、前日から寄せられた勤務を含む場合の
     * どちらも含む。こうした日の打刻は、日付ごとに1回だけ出勤する前提の編集・修正の処理では
     * 正しく扱えないため、呼び出し側で画面からの編集を止めるために使う。
     *
     * @param  Collection<int, TimeRecord>  $records  対象日の前日から翌日までの打刻
     * @param  string  $workDate  勤務日（Y-m-d）
     */
    public function isInvolved(Collection $records, string $workDate): bool
    {
        foreach ($this->movedWorkDates($records) as $recordId => $movedDate) {
            $record = $records->firstWhere('id', $recordId);

            if ($movedDate === $workDate || $record?->record_time->format('Y-m-d') === $workDate) {
                return true;
            }
        }

        return false;
    }

    /**
     * 打刻を、出勤から退勤までの勤務ごとにまとめる
     *
     * 出勤のあとに次の出勤が来たら、前の勤務は退勤がないまま終わったものとして区切る。
     * 勤務の外にある休憩や退勤は、どの勤務にも含めない。
     *
     * @param  Collection<int, TimeRecord>  $records
     * @return array<int, array{start: TimeRecord, end: TimeRecord|null, records: array<int, TimeRecord>}>
     */
    private function sessions(Collection $records): array
    {
        $sorted = $records
            ->sortBy(fn (TimeRecord $record) => [$record->record_time->getTimestamp(), $record->id])
            ->values();

        $sessions = [];
        $current = null;

        foreach ($sorted as $record) {
            if ($record->record_type->isWorkStart()) {
                if ($current !== null) {
                    $sessions[] = $current;
                }
                $current = ['start' => $record, 'end' => null, 'records' => [$record]];

                continue;
            }

            if ($current === null) {
                continue;
            }

            $current['records'][] = $record;

            if ($record->record_type->isWorkEnd()) {
                $current['end'] = $record;
                $sessions[] = $current;
                $current = null;
            }
        }

        if ($current !== null) {
            $sessions[] = $current;
        }

        return $sessions;
    }
}

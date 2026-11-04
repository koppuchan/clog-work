<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TimeRecordTypeEnum;
use App\Models\TimeRecord;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * 実際に打刻された時刻を日付ごとに取り出す
 *
 * 労働時間の計算には丸め時刻（15分単位等）を使うが、帳票やCSVに
 * 表示するのは実際に打刻された時刻とする。
 *
 *   例: 8:58 に打刻 → 計算は 9:00、表示は 8:58
 *
 * 集計テーブル（daily_work_summaries）には丸め後の時刻が保存されるため、
 * 表示用の値は打刻レコードから引き直す必要がある。
 */
class RawStampTimeService
{
    public function __construct(
        private readonly TimeRecordRepositoryInterface $timeRecordRepository,
        private readonly WorkSessionAttribution $workSessionAttribution
    ) {}

    /**
     * 期間内の実打刻を日付ごとにまとめる
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d）
     * @param  string  $endDate  終了日（Y-m-d）
     * @return array<string, array{work_start: string|null, work_end: string|null, breaks: array<int, array{start: string, end: string|null}>}>
     */
    public function mapByDate(int $companyId, int $userId, string $startDate, string $endDate): array
    {
        $result = [];

        foreach ($this->recordsByWorkDate($companyId, $userId, $startDate, $endDate) as $date => $dayRecords) {
            $sorted = $dayRecords->sortBy(fn (TimeRecord $r) => $r->record_time->getTimestamp())->values();

            $breakStarts = $sorted->filter(
                fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::BREAK_START
            )->values();
            $breakEnds = $sorted->filter(
                fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::BREAK_END
            )->values();

            $breaks = [];
            foreach ($breakStarts as $index => $start) {
                $breaks[] = [
                    'start' => $start->record_time->format('H:i'),
                    'end' => $breakEnds->get($index)?->record_time->format('H:i'),
                ];
            }

            $result[$date] = [
                'work_start' => $sorted->first(
                    fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::WORK_START
                )?->record_time->format('H:i'),
                'work_end' => $sorted->last(
                    fn (TimeRecord $r) => $r->record_type->isWorkEnd()
                )?->record_time->format('H:i'),
                'breaks' => $breaks,
            ];
        }

        return $result;
    }

    /**
     * 期間内の出勤・退勤の打刻位置を、勤務日ごとにまとめる
     *
     * 出勤は、その日の最初の出勤の位置。退勤は、その日の最後の出勤に対する退勤
     * （日付をまたぐ勤務なら、翌日の日付の退勤）の位置を返す。
     * 出勤の無い日は、その日の日付の退勤（日付越えではないもの）の位置を返す。
     * 位置が記録されていない打刻は、nullにする。
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d）
     * @param  string  $endDate  終了日（Y-m-d）
     * @return array<string, array{start: array{latitude: float, longitude: float}|null, end: array{latitude: float, longitude: float}|null}>
     */
    public function locationsByDate(int $companyId, int $userId, string $startDate, string $endDate): array
    {
        $records = $this->loadRecords($companyId, $userId, $startDate, $endDate);
        $allSorted = $records->sortBy(fn (TimeRecord $r) => [$r->record_time->getTimestamp(), $r->id])->values();

        $locationOf = fn (?TimeRecord $record): ?array => $record?->latitude !== null && $record->longitude !== null
            ? ['latitude' => $record->latitude, 'longitude' => $record->longitude]
            : null;

        $result = [];

        foreach ($this->groupByWorkDate($records, $startDate, $endDate) as $date => $dayRecords) {
            $sorted = $dayRecords->sortBy(fn (TimeRecord $r) => $r->record_time->getTimestamp())->values();

            $firstStart = $sorted->first(fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::WORK_START);
            $lastStart = $sorted->last(fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::WORK_START);

            $end = $lastStart !== null
                ? $this->endOfSession($allSorted, $lastStart)
                : $sorted->last(fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::WORK_END);

            $result[$date] = ['start' => $locationOf($firstStart), 'end' => $locationOf($end)];
        }

        return $result;
    }

    /**
     * 出勤に対する退勤を探す（次の出勤より前に退勤があれば、その退勤）
     *
     * @param  \Illuminate\Support\Collection<int, TimeRecord>  $allSorted  時刻順に並べた打刻
     */
    private function endOfSession(\Illuminate\Support\Collection $allSorted, TimeRecord $workStart): ?TimeRecord
    {
        $following = $allSorted->filter(fn (TimeRecord $r) => $r->record_time->gt($workStart->record_time) || ($r->record_time->eq($workStart->record_time) && $r->id > $workStart->id));

        foreach ($following as $record) {
            if ($record->record_type->isWorkStart()) {
                return null;
            }
            if ($record->record_type->isWorkEnd()) {
                return $record;
            }
        }

        return null;
    }

    /**
     * 期間の前後1日を含めて、打刻を読む
     *
     * 前日に出勤した勤務を翌日の勤務として扱うかどうかの判定に、前後の日の打刻が必要。
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TimeRecord>
     */
    private function loadRecords(int $companyId, int $userId, string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return $this->timeRecordRepository->findByUserIdsAndDateRange(
            $companyId,
            [$userId],
            CarbonImmutable::parse($startDate)->subDay()->format('Y-m-d'),
            CarbonImmutable::parse($endDate)->addDay()->format('Y-m-d'),
        );
    }

    /**
     * 期間内の打刻を、勤務日ごとにまとめる
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, TimeRecord>>
     */
    private function recordsByWorkDate(int $companyId, int $userId, string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        return $this->groupByWorkDate($this->loadRecords($companyId, $userId, $startDate, $endDate), $startDate, $endDate);
    }

    /**
     * 打刻を勤務日ごとにまとめ、期間内の日だけを返す
     *
     * @param  \Illuminate\Support\Collection<int, TimeRecord>  $records  期間の前後1日を含む打刻
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, TimeRecord>>
     */
    private function groupByWorkDate(\Illuminate\Support\Collection $records, string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        // 同じ日に2回目の出勤をして日付をまたいだ勤務は、翌日の勤務として扱う
        $movedWorkDates = $this->workSessionAttribution->movedWorkDates($records);

        return $records
            ->groupBy(fn (TimeRecord $r) => $movedWorkDates[$r->id] ?? $r->record_time->format('Y-m-d'))
            ->filter(fn ($dayRecords, $date) => $date >= $startDate && $date <= $endDate);
    }
}

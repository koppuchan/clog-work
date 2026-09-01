<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TimeRecordTypeEnum;
use App\Models\TimeRecord;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;

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
        private readonly TimeRecordRepositoryInterface $timeRecordRepository
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
        $records = $this->timeRecordRepository->findByUserIdsAndDateRange(
            $companyId,
            [$userId],
            $startDate,
            $endDate,
        );

        $result = [];

        foreach ($records->groupBy(fn (TimeRecord $r) => $r->record_time->format('Y-m-d')) as $date => $dayRecords) {
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
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TimeRecordTypeEnum;
use App\Models\TimeRecord;
use Illuminate\Database\Eloquent\Collection;

/**
 * 当日の日付の打刻から、前夜の日付越え勤務に属するものを切り分ける
 *
 * 日付越え退勤(WORK_END_NEXT_DAY)とその時刻以前の休憩は、出勤日ではなく
 * 翌日の日付で保存される。そのため前夜に日付越えの勤務があった日は、当日の
 * 日付の打刻に「前夜の勤務の分」が混在する。これを当日の打刻として更新・削除・
 * 申請の対象にすると、当日の打刻は変わらないまま前日の退勤や休憩だけが
 * 書き換わったり消えたりしてしまう。
 *
 * 前夜分かどうかの判定は、翌日の分を前日へ寄せる際の判定
 * （日付越え退勤と、その時刻以前の休憩）と同じ基準にしている。
 */
class PreviousNightTimeRecords
{
    /**
     * 前夜の勤務に属する打刻を除いた打刻を返す
     *
     * @param  Collection<int, TimeRecord>  $dayRecords  当日の日付の打刻（翌日分をマージする前のもの）
     * @return Collection<int, TimeRecord>
     */
    public function excludeFrom(Collection $dayRecords): Collection
    {
        $previousNightEnd = $dayRecords->first(
            fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
        );

        if ($previousNightEnd === null) {
            return $dayRecords;
        }

        return $dayRecords->reject(function (TimeRecord $r) use ($previousNightEnd): bool {
            if ($r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY) {
                return true;
            }

            return ($r->record_type === TimeRecordTypeEnum::BREAK_START
                    || $r->record_type === TimeRecordTypeEnum::BREAK_END)
                && $r->record_time->lte($previousNightEnd->record_time);
        })->values();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TimeRecordTypeEnum;
use App\Models\TimeRecord;
use App\Services\WorkSessionAttribution;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * 同じ日に2回目の出勤をした日付越え勤務を、翌日の勤務として扱う判定のテスト
 */
class WorkSessionAttributionTest extends TestCase
{
    private int $nextId = 1;

    private function record(TimeRecordTypeEnum $type, string $time): TimeRecord
    {
        $record = new TimeRecord;
        $record->id = $this->nextId++;
        $record->record_type = $type;
        $record->record_time = $time;

        return $record;
    }

    /**
     * @param  array<int, TimeRecord>  $records
     * @return array<int, string>
     */
    private function moved(array $records): array
    {
        return (new WorkSessionAttribution)->movedWorkDates(new Collection($records));
    }

    /**
     * @test
     */
    public function 同じ日に2回目の出勤をして日付をまたいだ勤務は翌日の勤務になる(): void
    {
        // 11/3 0:10〜8:00（1回目）と 11/3 23:45〜11/4 7:45（2回目）。休憩は2回目の勤務の中にある
        $firstStart = $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00');
        $firstEnd = $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00');
        $secondStart = $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00');
        $breakStart = $this->record(TimeRecordTypeEnum::BREAK_START, '2026-11-04 03:00:00');
        $breakEnd = $this->record(TimeRecordTypeEnum::BREAK_END, '2026-11-04 04:00:00');
        $secondEnd = $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:45:00');

        $moved = $this->moved([$firstStart, $firstEnd, $secondStart, $breakStart, $breakEnd, $secondEnd]);

        $this->assertSame([
            $secondStart->id => '2026-11-04',
            $breakStart->id => '2026-11-04',
            $breakEnd->id => '2026-11-04',
            $secondEnd->id => '2026-11-04',
        ], $moved);
    }

    /**
     * @test
     */
    public function 順不同で渡しても同じ結果になる(): void
    {
        $firstStart = $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00');
        $firstEnd = $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00');
        $secondStart = $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00');
        $secondEnd = $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:45:00');

        $moved = $this->moved([$secondEnd, $firstEnd, $secondStart, $firstStart]);

        $this->assertSame([$secondStart->id => '2026-11-04', $secondEnd->id => '2026-11-04'], $moved);
    }

    /**
     * @test
     */
    public function 勤務が1日に1回だけなら日付をまたいでも出勤した日の勤務のまま(): void
    {
        $start = $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 22:00:00');
        $end = $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 06:00:00');

        $this->assertSame([], $this->moved([$start, $end]));
    }

    /**
     * @test
     */
    public function 毎日同じ時刻に夜勤をしていても勤務日は動かない(): void
    {
        $records = [
            $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-01 23:45:00'),
            $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-02 07:45:00'),
            $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-02 23:45:00'),
            $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-03 07:45:00'),
            $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:50:00'),
            $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:40:00'),
        ];

        $this->assertSame([], $this->moved($records));
    }

    /**
     * @test
     */
    public function 同じ日の中で終わる2回目の勤務は動かさない(): void
    {
        // 6:00〜10:00 と 18:00〜22:00 の分割勤務
        $records = [
            $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 06:00:00'),
            $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 10:00:00'),
            $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 18:00:00'),
            $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 22:00:00'),
        ];

        $this->assertSame([], $this->moved($records));
    }

    /**
     * @test
     */
    public function まだ退勤していない2回目の勤務は動かさない(): void
    {
        $records = [
            $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00'),
            $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00'),
            $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00'),
        ];

        $this->assertSame([], $this->moved($records));
    }

    /**
     * @test
     */
    public function 翌日の勤務に寄せた日にも日付越えの勤務があれば、さらに翌日に寄せる(): void
    {
        $firstStart = $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00');
        $firstEnd = $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00');
        $secondStart = $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00');
        $secondEnd = $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:45:00');
        $thirdStart = $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-04 23:45:00');
        $thirdEnd = $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-05 07:45:00');

        $moved = $this->moved([$firstStart, $firstEnd, $secondStart, $secondEnd, $thirdStart, $thirdEnd]);

        $this->assertSame('2026-11-04', $moved[$secondStart->id]);
        $this->assertSame('2026-11-05', $moved[$thirdStart->id]);
        $this->assertSame('2026-11-05', $moved[$thirdEnd->id]);
        $this->assertArrayNotHasKey($firstStart->id, $moved);
    }

    /**
     * @test
     */
    public function 勤務の外にある休憩や退勤は対象にしない(): void
    {
        $records = [
            $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-03 06:00:00'),
            $this->record(TimeRecordTypeEnum::BREAK_START, '2026-11-03 07:00:00'),
        ];

        $this->assertSame([], $this->moved($records));
    }

    /**
     * @test
     */
    public function 打刻が無ければ空を返す(): void
    {
        $this->assertSame([], $this->moved([]));
    }
}

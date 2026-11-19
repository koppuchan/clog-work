<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\DailyWorkSummaryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 勤務実績CSVで、夜勤の出勤・退勤・休憩が、出勤した日の行にそろうこと
 *
 * 夜勤の退勤（日付越え退勤）と、日付をまたいだ休憩は、打刻した日（翌日）の日付で
 * 保存される。CSVでは、勤務実績の画面と同じく、出勤した日の行に出す。
 * クライアント報告: 夜勤の方の出力がおかしく、修正が翌日の退勤に入ってしまっている。
 */
class NightShiftCsvTest extends TestCase
{
    use DatabaseTransactions;

    private const CLOCK_IN = 8;

    private const CLOCK_OUT = 9;

    private const BREAK_IN_1 = 10;

    private const BREAK_OUT_1 = 11;

    private Company $company;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['is_closed_on_holidays' => false]);
        $this->staff = User::factory()->forCompany($this->company->id)->employee()->create(['is_retired' => false, 'name' => '夜勤 太郎']);
    }

    private function record(TimeRecordTypeEnum $type, string $time, ?string $roundedTime = null): void
    {
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'record_type' => $type,
            'record_time' => $time,
            'rounded_time' => $roundedTime ?? $time,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    /**
     * 日付（n/j）をキーにした、CSVの行
     *
     * @return array<string, array<int, string>>
     */
    private function csvRowsByDate(string $startDate, string $endDate): array
    {
        $csv = app(DailyWorkSummaryService::class)->generateCsvAll(
            $this->company->id,
            $startDate,
            $endDate,
            new Collection([$this->staff])
        );

        $rows = [];
        foreach (array_slice(array_filter(explode("\n", $csv)), 1) as $line) {
            $fields = str_getcsv($line);
            $rows[preg_replace('/\(.+\)/', '', $fields[2])] = $fields;
        }

        return $rows;
    }

    /**
     * @test
     */
    public function 連続した夜勤の退勤は出勤した日の行に出る(): void
    {
        // Arrange: 10/21 に出勤して 10/22 に退勤、10/22 は休み、10/23・10/24 に連続して夜勤
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-10-21 16:49:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-22 05:18:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-10-23 16:55:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-24 04:59:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-10-24 16:53:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-25 04:44:00');

        // Act
        $rows = $this->csvRowsByDate('2026-10-21', '2026-10-25');

        // Assert
        $this->assertSame(['16:49', '05:18'], [$rows['10/21'][self::CLOCK_IN], $rows['10/21'][self::CLOCK_OUT]]);
        $this->assertSame(['', ''], [$rows['10/22'][self::CLOCK_IN], $rows['10/22'][self::CLOCK_OUT]]);
        $this->assertSame(['16:55', '04:59'], [$rows['10/23'][self::CLOCK_IN], $rows['10/23'][self::CLOCK_OUT]]);
        $this->assertSame(['16:53', '04:44'], [$rows['10/24'][self::CLOCK_IN], $rows['10/24'][self::CLOCK_OUT]]);
        $this->assertSame(['', ''], [$rows['10/25'][self::CLOCK_IN], $rows['10/25'][self::CLOCK_OUT]]);
    }

    /**
     * @test
     */
    public function 日付をまたいだ休憩は出勤した日の行に出る(): void
    {
        // Arrange: 11/10 の夜勤で 3:00〜4:30 に休憩。11/11 の夜勤は休憩の打刻なし
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-10 21:52:00');
        $this->record(TimeRecordTypeEnum::BREAK_START, '2026-11-11 03:00:00');
        $this->record(TimeRecordTypeEnum::BREAK_END, '2026-11-11 04:30:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-11 05:11:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-11 17:00:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-12 05:14:00');

        // Act
        $rows = $this->csvRowsByDate('2026-11-10', '2026-11-11');

        // Assert
        $this->assertSame(['03:00', '04:30', '05:11'], [$rows['11/10'][self::BREAK_IN_1], $rows['11/10'][self::BREAK_OUT_1], $rows['11/10'][self::CLOCK_OUT]]);
        $this->assertSame(['', '', '05:14'], [$rows['11/11'][self::BREAK_IN_1], $rows['11/11'][self::BREAK_OUT_1], $rows['11/11'][self::CLOCK_OUT]]);
    }

    /**
     * @test
     */
    public function 遅番で日付をまたぐ勤務の退勤は翌日が通常の勤務でも出勤した日の行に実打刻で出る(): void
    {
        // Arrange: クライアント報告の形。13:15〜22:00 のシフトで 0:13 まで勤務（丸めると 0:10）。
        // 翌日は同じ日の中で終わる勤務、その次の日は休み
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-10-28 12:23:00', '2026-10-28 13:15:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-29 00:13:00', '2026-10-29 00:10:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-10-29 13:03:00', '2026-10-29 13:15:00');
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-10-29 22:40:00', '2026-10-29 22:40:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-01 12:09:00', '2026-11-01 13:15:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-02 01:31:00', '2026-11-02 01:30:00');

        // Act
        $rows = $this->csvRowsByDate('2026-10-28', '2026-11-03');

        // Assert
        $this->assertSame(['12:23', '00:13'], [$rows['10/28'][self::CLOCK_IN], $rows['10/28'][self::CLOCK_OUT]]);
        $this->assertSame(['13:03', '22:40'], [$rows['10/29'][self::CLOCK_IN], $rows['10/29'][self::CLOCK_OUT]]);
        $this->assertSame(['12:09', '01:31'], [$rows['11/1'][self::CLOCK_IN], $rows['11/1'][self::CLOCK_OUT]]);
        $this->assertSame(['', ''], [$rows['11/2'][self::CLOCK_IN], $rows['11/2'][self::CLOCK_OUT]]);
    }
}

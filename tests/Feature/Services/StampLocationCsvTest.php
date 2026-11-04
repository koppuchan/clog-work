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
 * 勤務実績CSVに、出社・退社の位置（地図のURL）の列が付くこと
 *
 * 付くのは、スマホ打刻で位置情報を記録する設定の会社だけ。
 * 設定していない会社のCSVは、これまでと同じ列のまま。
 */
class StampLocationCsvTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['is_closed_on_holidays' => false]);
        $this->staff = User::factory()->forCompany($this->company->id)->employee()->create(['is_retired' => false, 'name' => '打刻 花子']);
    }

    private function record(TimeRecordTypeEnum $type, string $time, ?float $latitude = null, ?float $longitude = null): void
    {
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'record_type' => $type,
            'record_time' => $time,
            'rounded_time' => $time,
            'record_source' => RecordSourceEnum::AUTO,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvRows(): array
    {
        $csv = app(DailyWorkSummaryService::class)->generateCsvAll(
            $this->company->id,
            '2026-11-03',
            '2026-11-04',
            new Collection([$this->staff])
        );

        return array_map('str_getcsv', array_filter(explode("\n", $csv)));
    }

    /**
     * @test
     */
    public function 記録する設定の会社の_cs_vに出社と退社の地図_ur_lが付く(): void
    {
        $this->company->forceFill(['records_stamp_location' => true])->save();
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 09:00:00', 34.7340123, 135.5001234);
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 18:00:00', 34.7, 135.5);

        $rows = $this->csvRows();

        $this->assertSame(['出社位置', '退社位置'], array_slice($rows[0], -2));
        $this->assertSame(
            ['https://www.google.com/maps?q=34.7340123,135.5001234', 'https://www.google.com/maps?q=34.7,135.5'],
            array_slice($rows[1], -2)
        );
        // 打刻のない日は、位置も空
        $this->assertSame(['', ''], array_slice($rows[2], -2));
    }

    /**
     * @test
     */
    public function 位置が記録されていない打刻の日は空になる(): void
    {
        $this->company->forceFill(['records_stamp_location' => true])->save();
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 09:00:00', 34.7, 135.5);
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 18:00:00');

        $rows = $this->csvRows();

        $this->assertSame(['https://www.google.com/maps?q=34.7,135.5', ''], array_slice($rows[1], -2));
    }

    /**
     * @test
     */
    public function 日付をまたぐ勤務の退社位置は出勤した日の行に出る(): void
    {
        $this->company->forceFill(['records_stamp_location' => true])->save();
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 22:00:00', 34.7, 135.5);
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 06:00:00', 34.8, 135.6);

        $rows = $this->csvRows();

        $this->assertSame(['https://www.google.com/maps?q=34.7,135.5', 'https://www.google.com/maps?q=34.8,135.6'], array_slice($rows[1], -2));
        $this->assertSame(['', ''], array_slice($rows[2], -2));
    }

    /**
     * @test
     */
    public function 記録しない設定の会社の_cs_vはこれまでと同じ列のまま(): void
    {
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 09:00:00', 34.7, 135.5);

        $rows = $this->csvRows();

        $this->assertCount(20, $rows[0]);
        $this->assertSame('備考/申請', $rows[0][19]);
        $this->assertCount(20, $rows[1]);
    }

    /**
     * @test
     */
    public function 同じ日に2回出勤した日は、それぞれの勤務日の位置が出る(): void
    {
        $this->company->forceFill(['records_stamp_location' => true])->save();
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00', 34.1, 135.1);
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00', 34.2, 135.2);
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00', 34.3, 135.3);
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:45:00', 34.4, 135.4);

        $rows = $this->csvRows();

        $this->assertSame(['https://www.google.com/maps?q=34.1,135.1', 'https://www.google.com/maps?q=34.2,135.2'], array_slice($rows[1], -2));
        $this->assertSame(['https://www.google.com/maps?q=34.3,135.3', 'https://www.google.com/maps?q=34.4,135.4'], array_slice($rows[2], -2));
    }
}

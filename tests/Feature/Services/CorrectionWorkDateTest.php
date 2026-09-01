<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\TimeRecordCorrection;
use App\Models\User;
use App\Services\DailyWorkSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 打刻修正がどの日に表示されるかの検証。
 *
 * 夜勤の退勤は翌日の時刻で記録されるため、打刻時刻の日付でまとめると
 * 6/2 の勤務に対する修正が 6/3 の修正として表示されてしまう。
 */
class CorrectionWorkDateTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    private DailyWorkSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create();
        $this->service = app(DailyWorkSummaryService::class);
    }

    private function createCorrection(TimeRecordTypeEnum $type, string $beforeTime, string $afterTime): void
    {
        $record = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => $type,
            'record_time' => $beforeTime,
            'rounded_time' => $beforeTime,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecordCorrection::query()->create([
            'time_record_id' => $record->id,
            'record_type' => $type,
            'before_record_time' => $beforeTime,
            'before_rounded_time' => $beforeTime,
            'before_record_source' => RecordSourceEnum::AUTO,
            'after_record_time' => $afterTime,
            'after_rounded_time' => $afterTime,
            'after_record_source' => RecordSourceEnum::MANUAL,
            'corrected_by' => $this->user->id,
        ]);
    }

    /**
     * @test
     */
    public function 日付越えの退勤の修正は勤務開始日に表示される(): void
    {
        // Arrange: 6/2 22:00 出勤の夜勤に対する 6/3 6:00 の退勤の修正
        $this->createCorrection(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-06-03 06:00:00', '2026-06-03 06:30:00');

        // Act
        $result = $this->service->getCorrectionsByUserIdAndDateRange(
            $this->user->id,
            '2026-06-01',
            '2026-06-30',
        );

        // Assert: 翌日ではなく勤務開始日にまとまる
        $this->assertArrayHasKey('2026-06-02', $result);
        $this->assertArrayNotHasKey('2026-06-03', $result);
        $this->assertCount(1, $result['2026-06-02']);
    }

    /**
     * @test
     */
    public function 通常の退勤の修正は打刻当日に表示される(): void
    {
        // Arrange
        $this->createCorrection(TimeRecordTypeEnum::WORK_END, '2026-06-02 18:00:00', '2026-06-02 18:30:00');

        // Act
        $result = $this->service->getCorrectionsByUserIdAndDateRange(
            $this->user->id,
            '2026-06-01',
            '2026-06-30',
        );

        // Assert
        $this->assertArrayHasKey('2026-06-02', $result);
        $this->assertArrayNotHasKey('2026-06-01', $result);
    }

    /**
     * @test
     */
    public function 出勤の修正は打刻当日に表示される(): void
    {
        // Arrange
        $this->createCorrection(TimeRecordTypeEnum::WORK_START, '2026-06-02 09:00:00', '2026-06-02 08:30:00');

        // Act
        $result = $this->service->getCorrectionsByUserIdAndDateRange(
            $this->user->id,
            '2026-06-01',
            '2026-06-30',
        );

        // Assert
        $this->assertArrayHasKey('2026-06-02', $result);
    }
}

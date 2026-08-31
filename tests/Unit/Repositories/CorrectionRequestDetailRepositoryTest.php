<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\TimeRecordCorrectionRequest;
use App\Models\TimeRecordCorrectionRequestDetail;
use App\Models\User;
use App\Repositories\CorrectionRequestDetailRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/CorrectionRequestDetailRepositoryTest.php
 */
class CorrectionRequestDetailRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CorrectionRequestDetailRepository $repository;

    private Company $company;

    private User $user;

    private TimeRecordCorrectionRequest $correctionRequest;

    private TimeRecord $timeRecord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CorrectionRequestDetailRepository(new TimeRecordCorrectionRequestDetail);

        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create();

        $this->correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'target_date' => '2026-03-01',
            'reason' => 'テスト修正申請',
            'status' => RequestStatusEnum::PENDING->value,
        ]);

        $this->timeRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START->value,
            'record_time' => CarbonImmutable::parse('2026-03-01 09:00:00'),
            'rounded_time' => CarbonImmutable::parse('2026-03-01 09:00:00'),
            'record_source' => RecordSourceEnum::AUTO->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeDetailData(array $overrides = []): array
    {
        return array_merge([
            'correction_request_id' => $this->correctionRequest->id,
            'time_record_id' => $this->timeRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_START->value,
            'original_record_time' => CarbonImmutable::parse('2026-03-01 09:00:00'),
            'original_rounded_time' => CarbonImmutable::parse('2026-03-01 09:00:00'),
            'corrected_record_time' => CarbonImmutable::parse('2026-03-01 08:55:00'),
            'corrected_rounded_time' => CarbonImmutable::parse('2026-03-01 09:00:00'),
        ], $overrides);
    }

    public function test_createで明細を作成_正しいデータが保存される(): void
    {
        // Arrange
        $data = $this->makeDetailData();

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->correctionRequest->id, $result->correction_request_id);
        $this->assertEquals($this->timeRecord->id, $result->time_record_id);
        $this->assertEquals(TimeRecordTypeEnum::WORK_START, $result->record_type);
        $this->assertDatabaseHas('time_record_correction_request_details', [
            'id' => $result->id,
            'correction_request_id' => $this->correctionRequest->id,
        ]);
    }

    public function test_createで明細を作成_異なるrecord_typeで作成できる(): void
    {
        // Arrange
        $workEndRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END->value,
            'record_time' => CarbonImmutable::parse('2026-03-01 18:00:00'),
            'rounded_time' => CarbonImmutable::parse('2026-03-01 18:00:00'),
            'record_source' => RecordSourceEnum::AUTO->value,
        ]);

        $data = $this->makeDetailData([
            'time_record_id' => $workEndRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_END->value,
            'original_record_time' => CarbonImmutable::parse('2026-03-01 18:00:00'),
            'original_rounded_time' => CarbonImmutable::parse('2026-03-01 18:00:00'),
            'corrected_record_time' => CarbonImmutable::parse('2026-03-01 18:30:00'),
            'corrected_rounded_time' => CarbonImmutable::parse('2026-03-01 18:30:00'),
        ]);

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(TimeRecordTypeEnum::WORK_END, $result->record_type);
    }

    public function test_createで明細を作成_同一修正申請に複数明細を作成できる(): void
    {
        // Arrange
        $workEndRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END->value,
            'record_time' => CarbonImmutable::parse('2026-03-01 18:00:00'),
            'rounded_time' => CarbonImmutable::parse('2026-03-01 18:00:00'),
            'record_source' => RecordSourceEnum::AUTO->value,
        ]);

        // Act
        $detail1 = $this->repository->create($this->makeDetailData());
        $detail2 = $this->repository->create($this->makeDetailData([
            'time_record_id' => $workEndRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_END->value,
            'original_record_time' => CarbonImmutable::parse('2026-03-01 18:00:00'),
            'original_rounded_time' => CarbonImmutable::parse('2026-03-01 18:00:00'),
            'corrected_record_time' => CarbonImmutable::parse('2026-03-01 18:30:00'),
            'corrected_rounded_time' => CarbonImmutable::parse('2026-03-01 18:30:00'),
        ]));

        // Assert
        $this->assertNotEquals($detail1->id, $detail2->id);
        $this->assertEquals($this->correctionRequest->id, $detail1->correction_request_id);
        $this->assertEquals($this->correctionRequest->id, $detail2->correction_request_id);
    }

    public function test_updateで明細を更新_corrected_record_timeが更新される(): void
    {
        // Arrange
        $detail = TimeRecordCorrectionRequestDetail::query()->create($this->makeDetailData());
        $newCorrectedTime = CarbonImmutable::parse('2026-03-01 08:50:00');

        // Act
        $result = $this->repository->update($detail->id, [
            'corrected_record_time' => $newCorrectedTime,
        ]);

        // Assert
        $this->assertEquals(
            $newCorrectedTime->format('Y-m-d H:i:s'),
            $result->corrected_record_time->format('Y-m-d H:i:s')
        );
    }

    public function test_updateで明細を更新_corrected_rounded_timeが更新される(): void
    {
        // Arrange
        $detail = TimeRecordCorrectionRequestDetail::query()->create($this->makeDetailData());
        $newRoundedTime = CarbonImmutable::parse('2026-03-01 09:00:00');

        // Act
        $result = $this->repository->update($detail->id, [
            'corrected_rounded_time' => $newRoundedTime,
        ]);

        // Assert
        $this->assertEquals(
            $newRoundedTime->format('Y-m-d H:i:s'),
            $result->corrected_rounded_time->format('Y-m-d H:i:s')
        );
    }

    public function test_updateで明細を更新_存在しない_i_dの場合は例外を投げる(): void
    {
        // Assert
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // Act
        $this->repository->update(9999, [
            'corrected_record_time' => CarbonImmutable::parse('2026-03-01 08:50:00'),
        ]);
    }

    public function test_updateで明細を更新_record_typeを変更できる(): void
    {
        // Arrange
        $detail = TimeRecordCorrectionRequestDetail::query()->create($this->makeDetailData());

        // Act
        $result = $this->repository->update($detail->id, [
            'record_type' => TimeRecordTypeEnum::WORK_END->value,
        ]);

        // Assert
        $this->assertEquals(TimeRecordTypeEnum::WORK_END, $result->record_type);
    }
}

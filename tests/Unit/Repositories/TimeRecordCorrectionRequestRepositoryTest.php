<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\RequestStatusEnum;
use App\Models\Company;
use App\Models\TimeRecordCorrectionRequest;
use App\Models\User;
use App\Repositories\TimeRecordCorrectionRequestRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/TimeRecordCorrectionRequestRepositoryTest.php
 */
class TimeRecordCorrectionRequestRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TimeRecordCorrectionRequestRepository $repository;

    private Company $company;

    private User $user;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TimeRecordCorrectionRequestRepository(new TimeRecordCorrectionRequest);

        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create();
        $this->approver = User::factory()->forCompany($this->company->id)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeCorrectionRequestData(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'target_date' => '2026-03-01',
            'reason' => '打刻修正テスト',
            'status' => RequestStatusEnum::PENDING->value,
        ], $overrides);
    }

    public function test_find_by_idで修正申請を取得_存在する場合は申請を返す(): void
    {
        // Arrange
        $correctionRequest = TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());

        // Act
        $result = $this->repository->findById($correctionRequest->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($correctionRequest->id, $result->id);
        $this->assertEquals('打刻修正テスト', $result->reason);
    }

    public function test_find_by_idで修正申請を取得_存在しない場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findById(9999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_idで修正申請を取得_リレーションがロードされる(): void
    {
        // Arrange
        $correctionRequest = TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());

        // Act
        $result = $this->repository->findById($correctionRequest->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('company'));
        $this->assertTrue($result->relationLoaded('user'));
        $this->assertTrue($result->relationLoaded('details'));
    }

    public function test_find_by_company_id_and_statusで会社_i_dとステータスで取得_ステータス指定あり(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::PENDING->value,
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByCompanyIdAndStatus(
            $this->company->id,
            RequestStatusEnum::PENDING->value
        );

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals(RequestStatusEnum::PENDING, $result->first()->status);
    }

    public function test_find_by_company_id_and_statusで会社_i_dとステータスで取得_ステータスnullで全件返す(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::PENDING->value,
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByCompanyIdAndStatus($this->company->id, null);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_company_id_and_statusで会社_i_dとステータスで取得_別会社のデータは含まない(): void
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'company_id' => $otherCompany->id,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByCompanyIdAndStatus($this->company->id, null);

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_user_idでユーザー_i_dで取得_ステータス指定あり(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::PENDING->value,
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByUserId(
            $this->company->id,
            $this->user->id,
            RequestStatusEnum::PENDING->value
        );

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_user_idでユーザー_i_dで取得_ステータスnullで全件返す(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByUserId(
            $this->company->id,
            $this->user->id,
            null
        );

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_user_idでユーザー_i_dで取得_別ユーザーのデータは含まない(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'user_id' => $this->approver->id,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByUserId(
            $this->company->id,
            $this->user->id,
            null
        );

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_approver_idで承認者_i_dで取得_ステータス指定あり(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
            'approver_id' => $this->approver->id,
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::REJECTED->value,
            'approver_id' => $this->approver->id,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByApproverId(
            $this->company->id,
            $this->approver->id,
            RequestStatusEnum::APPROVED->value
        );

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_approver_idで承認者_i_dで取得_ステータスnullで全件返す(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
            'approver_id' => $this->approver->id,
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'status' => RequestStatusEnum::REJECTED->value,
            'approver_id' => $this->approver->id,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByApproverId(
            $this->company->id,
            $this->approver->id,
            null
        );

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_approver_idで承認者_i_dで取得_別承認者のデータは含まない(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'approver_id' => $this->approver->id,
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'approver_id' => $this->user->id,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByApproverId(
            $this->company->id,
            $this->approver->id,
            null
        );

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_target_dateで対象日で取得_該当日の申請を返す(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-03-01',
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByTargetDate($this->company->id, '2026-03-01');

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_target_dateで対象日で取得_該当なしの場合は空コレクション(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-03-01',
        ]));

        // Act
        $result = $this->repository->findByTargetDate($this->company->id, '2026-12-31');

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_by_date_rangeで日付範囲で取得_範囲内のデータを返す(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-03-01',
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-03-15',
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-04-01',
        ]));

        // Act
        $result = $this->repository->findByDateRange(
            $this->company->id,
            '2026-03-01',
            '2026-03-31',
            null
        );

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_date_rangeで日付範囲で取得_ステータス指定あり(): void
    {
        // Arrange
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-03-01',
            'status' => RequestStatusEnum::PENDING->value,
        ]));
        TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData([
            'target_date' => '2026-03-15',
            'status' => RequestStatusEnum::APPROVED->value,
        ]));

        // Act
        $result = $this->repository->findByDateRange(
            $this->company->id,
            '2026-03-01',
            '2026-03-31',
            RequestStatusEnum::PENDING->value
        );

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_createで修正申請を作成_正しいデータが保存される(): void
    {
        // Arrange
        $data = $this->makeCorrectionRequestData();

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->company->id, $result->company_id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals('打刻修正テスト', $result->reason);
        $this->assertDatabaseHas('time_record_correction_requests', [
            'id' => $result->id,
            'reason' => '打刻修正テスト',
        ]);
    }

    public function test_updateで修正申請を更新_正しくデータが更新される(): void
    {
        // Arrange
        $correctionRequest = TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());

        // Act
        $result = $this->repository->update($correctionRequest->id, ['reason' => '更新された理由']);

        // Assert
        $this->assertEquals('更新された理由', $result->reason);
        $this->assertDatabaseHas('time_record_correction_requests', [
            'id' => $correctionRequest->id,
            'reason' => '更新された理由',
        ]);
    }

    public function test_updateで修正申請を更新_存在しない_i_dの場合は例外を投げる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->update(9999, ['reason' => '更新']);
    }

    public function test_deleteで修正申請を削除_正常に削除される(): void
    {
        // Arrange
        $correctionRequest = TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());

        // Act
        $result = $this->repository->delete($correctionRequest->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('time_record_correction_requests', ['id' => $correctionRequest->id]);
    }

    public function test_deleteで修正申請を削除_存在しない場合はfalseを返す(): void
    {
        // Act
        $result = $this->repository->delete(9999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_approveで修正申請を承認_ステータスとapprover_idとapproved_atが設定される(): void
    {
        // Arrange
        $correctionRequest = TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());

        // Act
        $result = $this->repository->approve($correctionRequest->id, $this->approver->id);

        // Assert
        $this->assertEquals(RequestStatusEnum::APPROVED, $result->status);
        $this->assertEquals($this->approver->id, $result->approver_id);
        $this->assertNotNull($result->approved_at);
    }

    public function test_approveで修正申請を承認_存在しない_i_dの場合は例外を投げる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->approve(9999, $this->approver->id);
    }

    public function test_rejectで修正申請を却下_ステータスとapprover_idとrejection_reasonが設定される(): void
    {
        // Arrange
        $correctionRequest = TimeRecordCorrectionRequest::query()->create($this->makeCorrectionRequestData());

        // Act
        $result = $this->repository->reject($correctionRequest->id, $this->approver->id, '却下理由テスト');

        // Assert
        $this->assertEquals(RequestStatusEnum::REJECTED, $result->status);
        $this->assertEquals($this->approver->id, $result->approver_id);
        $this->assertEquals('却下理由テスト', $result->rejection_reason);
    }

    public function test_rejectで修正申請を却下_存在しない_i_dの場合は例外を投げる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->reject(9999, $this->approver->id, '却下理由');
    }
}

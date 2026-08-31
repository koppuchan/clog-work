<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\RequestStatusEnum;
use App\Models\ApplicationType;
use App\Models\Company;
use App\Models\Request;
use App\Models\User;
use App\Repositories\RequestRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/RequestRepositoryTest.php
 */
class RequestRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private RequestRepository $repository;

    private Company $company;

    private User $user;

    private User $approver;

    private ApplicationType $applicationType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RequestRepository(new Request);

        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create();
        $this->approver = User::factory()->forCompany($this->company->id)->create();
        $this->applicationType = ApplicationType::query()->create([
            'id' => 99,
            'code' => 'test',
            'name' => 'テスト',
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRequestData(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => $this->applicationType->id,
            'target_date' => '2026-03-01',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'reason' => 'テスト理由',
            'status' => RequestStatusEnum::PENDING->value,
        ], $overrides);
    }

    public function test_find_by_idで申請を取得_存在する場合は申請を返す(): void
    {
        // Arrange
        $request = Request::query()->create($this->makeRequestData());

        // Act
        $result = $this->repository->findById($request->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($request->id, $result->id);
        $this->assertEquals('テスト理由', $result->reason);
    }

    public function test_find_by_idで申請を取得_存在しない場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findById(9999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_idで申請を取得_リレーションがロードされる(): void
    {
        // Arrange
        $request = Request::query()->create($this->makeRequestData());

        // Act
        $result = $this->repository->findById($request->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('company'));
        $this->assertTrue($result->relationLoaded('requestedBy'));
        $this->assertTrue($result->relationLoaded('applicationType'));
    }

    public function test_find_by_company_id_and_statusで会社_i_dとステータスで取得_ステータス指定あり(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData(['status' => RequestStatusEnum::PENDING->value]));
        Request::query()->create($this->makeRequestData([
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
        Request::query()->create($this->makeRequestData(['status' => RequestStatusEnum::PENDING->value]));
        Request::query()->create($this->makeRequestData([
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
        Request::query()->create($this->makeRequestData());
        Request::query()->create($this->makeRequestData([
            'company_id' => $otherCompany->id,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByCompanyIdAndStatus($this->company->id, null);

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_requested_byで申請者_i_dで取得_ステータス指定あり(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData(['status' => RequestStatusEnum::PENDING->value]));
        Request::query()->create($this->makeRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByRequestedBy(
            $this->company->id,
            $this->user->id,
            RequestStatusEnum::PENDING->value
        );

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_requested_byで申請者_i_dで取得_ステータスnullで全件返す(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData());
        Request::query()->create($this->makeRequestData(['target_date' => '2026-03-02']));

        // Act
        $result = $this->repository->findByRequestedBy(
            $this->company->id,
            $this->user->id,
            null
        );

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_requested_byで申請者_i_dで取得_別ユーザーのデータは含まない(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData());
        Request::query()->create($this->makeRequestData([
            'requested_by' => $this->approver->id,
            'target_date' => '2026-03-02',
        ]));

        // Act
        $result = $this->repository->findByRequestedBy(
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
        Request::query()->create($this->makeRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
            'approver_id' => $this->approver->id,
        ]));
        Request::query()->create($this->makeRequestData([
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
        Request::query()->create($this->makeRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
            'approver_id' => $this->approver->id,
        ]));
        Request::query()->create($this->makeRequestData([
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

    public function test_find_by_target_dateで対象日で取得_該当日の申請を返す(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData(['target_date' => '2026-03-01']));
        Request::query()->create($this->makeRequestData(['target_date' => '2026-03-02']));

        // Act
        $result = $this->repository->findByTargetDate($this->company->id, '2026-03-01');

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_by_target_dateで対象日で取得_該当なしの場合は空コレクション(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData(['target_date' => '2026-03-01']));

        // Act
        $result = $this->repository->findByTargetDate($this->company->id, '2026-12-31');

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_by_date_rangeで日付範囲で取得_範囲内のデータを返す(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData(['target_date' => '2026-03-01']));
        Request::query()->create($this->makeRequestData(['target_date' => '2026-03-15']));
        Request::query()->create($this->makeRequestData(['target_date' => '2026-04-01']));

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
        Request::query()->create($this->makeRequestData([
            'target_date' => '2026-03-01',
            'status' => RequestStatusEnum::PENDING->value,
        ]));
        Request::query()->create($this->makeRequestData([
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

    public function test_find_by_user_id_and_date_rangeでユーザー_i_dと日付範囲で取得_該当ユーザーのみ返す(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData(['target_date' => '2026-03-01']));
        Request::query()->create($this->makeRequestData(['target_date' => '2026-03-15']));
        Request::query()->create($this->makeRequestData([
            'target_date' => '2026-03-10',
            'requested_by' => $this->approver->id,
        ]));

        // Act
        $result = $this->repository->findByUserIdAndDateRange(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            '2026-03-31',
            null
        );

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_user_id_and_date_rangeでユーザー_i_dと日付範囲で取得_ステータス指定あり(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData([
            'target_date' => '2026-03-01',
            'status' => RequestStatusEnum::PENDING->value,
        ]));
        Request::query()->create($this->makeRequestData([
            'target_date' => '2026-03-15',
            'status' => RequestStatusEnum::APPROVED->value,
        ]));

        // Act
        $result = $this->repository->findByUserIdAndDateRange(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            '2026-03-31',
            RequestStatusEnum::APPROVED->value
        );

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_exists_by_user_id_and_date_and_typeで申請存在確認_存在する場合はtrueを返す(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData([
            'status' => RequestStatusEnum::PENDING->value,
        ]));

        // Act
        $result = $this->repository->existsByUserIdAndDateAndType(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            $this->applicationType->id
        );

        // Assert
        $this->assertTrue($result);
    }

    public function test_exists_by_user_id_and_date_and_typeで申請存在確認_承認済みでもtrueを返す(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData([
            'status' => RequestStatusEnum::APPROVED->value,
        ]));

        // Act
        $result = $this->repository->existsByUserIdAndDateAndType(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            $this->applicationType->id
        );

        // Assert
        $this->assertTrue($result);
    }

    public function test_exists_by_user_id_and_date_and_typeで申請存在確認_却下済みの場合はfalseを返す(): void
    {
        // Arrange
        Request::query()->create($this->makeRequestData([
            'status' => RequestStatusEnum::REJECTED->value,
        ]));

        // Act
        $result = $this->repository->existsByUserIdAndDateAndType(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            $this->applicationType->id
        );

        // Assert
        $this->assertFalse($result);
    }

    public function test_exists_by_user_id_and_date_and_typeで申請存在確認_存在しない場合はfalseを返す(): void
    {
        // Act
        $result = $this->repository->existsByUserIdAndDateAndType(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            $this->applicationType->id
        );

        // Assert
        $this->assertFalse($result);
    }

    public function test_createで申請を作成_正しいデータが保存される(): void
    {
        // Arrange
        $data = $this->makeRequestData();

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->company->id, $result->company_id);
        $this->assertEquals($this->user->id, $result->requested_by);
        $this->assertEquals('テスト理由', $result->reason);
        $this->assertDatabaseHas('requests', [
            'id' => $result->id,
            'reason' => 'テスト理由',
        ]);
    }

    public function test_updateで申請を更新_正しくデータが更新される(): void
    {
        // Arrange
        $request = Request::query()->create($this->makeRequestData());

        // Act
        $result = $this->repository->update($request->id, ['reason' => '更新された理由']);

        // Assert
        $this->assertEquals('更新された理由', $result->reason);
        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'reason' => '更新された理由',
        ]);
    }

    public function test_updateで申請を更新_存在しない_i_dの場合は例外を投げる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->update(9999, ['reason' => '更新']);
    }

    public function test_deleteで申請を削除_正常に削除される(): void
    {
        // Arrange
        $request = Request::query()->create($this->makeRequestData());

        // Act
        $result = $this->repository->delete($request->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('requests', ['id' => $request->id]);
    }

    public function test_deleteで申請を削除_存在しない場合はfalseを返す(): void
    {
        // Act
        $result = $this->repository->delete(9999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_approveで申請を承認_ステータスとapprover_idとdecided_atが設定される(): void
    {
        // Arrange
        $request = Request::query()->create($this->makeRequestData());

        // Act
        $result = $this->repository->approve($request->id, $this->approver->id, '承認しました');

        // Assert
        $this->assertEquals(RequestStatusEnum::APPROVED, $result->status);
        $this->assertEquals($this->approver->id, $result->approver_id);
        $this->assertNotNull($result->decided_at);
        $this->assertEquals('承認しました', $result->decision_note);
    }

    public function test_approveで申請を承認_コメントなしでも承認できる(): void
    {
        // Arrange
        $request = Request::query()->create($this->makeRequestData());

        // Act
        $result = $this->repository->approve($request->id, $this->approver->id);

        // Assert
        $this->assertEquals(RequestStatusEnum::APPROVED, $result->status);
        $this->assertNull($result->decision_note);
    }

    public function test_approveで申請を承認_存在しない_i_dの場合は例外を投げる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->approve(9999, $this->approver->id);
    }

    public function test_rejectで申請を却下_ステータスとapprover_idとdecision_noteが設定される(): void
    {
        // Arrange
        $request = Request::query()->create($this->makeRequestData());

        // Act
        $result = $this->repository->reject($request->id, $this->approver->id, '却下理由');

        // Assert
        $this->assertEquals(RequestStatusEnum::REJECTED, $result->status);
        $this->assertEquals($this->approver->id, $result->approver_id);
        $this->assertNotNull($result->decided_at);
        $this->assertEquals('却下理由', $result->decision_note);
    }

    public function test_rejectで申請を却下_コメントなしでも却下できる(): void
    {
        // Arrange
        $request = Request::query()->create($this->makeRequestData());

        // Act
        $result = $this->repository->reject($request->id, $this->approver->id);

        // Assert
        $this->assertEquals(RequestStatusEnum::REJECTED, $result->status);
        $this->assertNull($result->decision_note);
    }

    public function test_rejectで申請を却下_存在しない_i_dの場合は例外を投げる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->reject(9999, $this->approver->id, '却下理由');
    }
}

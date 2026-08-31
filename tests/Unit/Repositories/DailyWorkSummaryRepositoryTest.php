<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\User;
use App\Repositories\DailyWorkSummaryRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/DailyWorkSummaryRepositoryTest.php
 */
class DailyWorkSummaryRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private DailyWorkSummaryRepository $repository;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DailyWorkSummaryRepository(new DailyWorkSummary);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create();
    }

    public function test_find_by_idで存在する勤務実績を取得できる(): void
    {
        // Arrange
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findById($summary->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals(480, $result->work_minutes);
        $this->assertTrue($result->relationLoaded('user'));
        $this->assertTrue($result->relationLoaded('company'));
    }

    public function test_find_by_idで存在しない_i_dの場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findById(9999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_user_id_and_date_rangeでユーザーの日付範囲内実績を取得できる(): void
    {
        // Arrange
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-15',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-04-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByUserIdAndDateRange(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_user_id_and_date_rangeで該当データがない場合は空コレクションを返す(): void
    {
        // Act
        $result = $this->repository->findByUserIdAndDateRange(
            $this->company->id,
            $this->user->id,
            '2026-01-01',
            '2026-01-31'
        );

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_by_company_id_and_date_rangeで会社の日付範囲内実績を取得できる(): void
    {
        // Arrange
        $user2 = User::factory()->forCompany($this->company->id)->create();
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user2->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByCompanyIdAndDateRange(
            $this->company->id,
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->first()->relationLoaded('user'));
    }

    public function test_find_by_company_id_and_date_rangeで他社データは含まれない(): void
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->forCompany($otherCompany->id)->create();
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByCompanyIdAndDateRange(
            $this->company->id,
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($this->company->id, $result->first()->company_id);
    }

    public function test_find_by_user_id_and_dateでユーザーの特定日の実績を取得できる(): void
    {
        // Arrange
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByUserIdAndDate(
            $this->company->id,
            $this->user->id,
            '2026-03-01'
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(480, $result->work_minutes);
    }

    public function test_find_by_user_id_and_dateで該当データがない場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findByUserIdAndDate(
            $this->company->id,
            $this->user->id,
            '2026-03-01'
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_user_ids_and_date_rangeで複数ユーザーの日付範囲内実績を取得できる(): void
    {
        // Arrange
        $user2 = User::factory()->forCompany($this->company->id)->create();
        $user3 = User::factory()->forCompany($this->company->id)->create();

        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user2->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user3->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByUserIdsAndDateRange(
            $this->company->id,
            [$this->user->id, $user2->id],
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->first()->relationLoaded('user'));
    }

    public function test_find_by_user_ids_and_date_rangeで空配列の場合は空コレクションを返す(): void
    {
        // Act
        $result = $this->repository->findByUserIdsAndDateRange(
            $this->company->id,
            [],
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_createで新しい勤務実績を作成できる(): void
    {
        // Arrange
        $data = [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-10',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(480, $result->work_minutes);
        $this->assertEquals(RecordSourceEnum::AUTO, $result->record_source);
        $this->assertDatabaseHas('daily_work_summaries', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-10',
        ]);
    }

    public function test_updateで既存勤務実績を更新できる(): void
    {
        // Arrange
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->update($summary->id, [
            'work_minutes' => 500,
            'net_work_minutes' => 440,
        ]);

        // Assert
        $this->assertEquals(500, $result->work_minutes);
        $this->assertEquals(440, $result->net_work_minutes);
        $this->assertDatabaseHas('daily_work_summaries', [
            'id' => $summary->id,
            'work_minutes' => 500,
        ]);
    }

    public function test_updateで存在しない_i_dの場合は例外がスローされる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->update(9999, ['work_minutes' => 480]);
    }

    public function test_deleteで勤務実績を削除できる(): void
    {
        // Arrange
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->delete($summary->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('daily_work_summaries', ['id' => $summary->id]);
    }

    public function test_deleteで存在しない_i_dの場合はfalseを返す(): void
    {
        // Act
        $result = $this->repository->delete(9999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_upsertで新規レコードを作成できる(): void
    {
        // Arrange
        $data = [
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ];

        // Act
        $result = $this->repository->upsert(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            $data
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(480, $result->work_minutes);
        $this->assertDatabaseHas('daily_work_summaries', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
        ]);
    }

    public function test_upsertで既存レコードを更新できる(): void
    {
        // Arrange
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-03-01',
            'work_minutes' => 480,
            'break_minutes' => 60,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $updateData = [
            'work_minutes' => 500,
            'break_minutes' => 60,
            'net_work_minutes' => 440,
            'record_source' => RecordSourceEnum::MANUAL,
        ];

        // Act
        $result = $this->repository->upsert(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            $updateData
        );

        // Assert
        $this->assertEquals(500, $result->work_minutes);
        $this->assertEquals(440, $result->net_work_minutes);
        $this->assertEquals(RecordSourceEnum::MANUAL, $result->record_source);

        // 重複レコードが作成されていないことを確認
        $count = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2026-03-01')
            ->count();
        $this->assertEquals(1, $count);
    }
}

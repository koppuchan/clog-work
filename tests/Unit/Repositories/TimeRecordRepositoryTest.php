<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\User;
use App\Repositories\TimeRecordRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/TimeRecordRepositoryTest.php
 */
class TimeRecordRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TimeRecordRepository $repository;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TimeRecordRepository(new TimeRecord);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create();
    }

    public function test_find_by_idで存在する打刻レコードを取得できる(): void
    {
        // Arrange
        $record = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findById($record->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(TimeRecordTypeEnum::WORK_START, $result->record_type);
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

    public function test_find_by_idsで複数_i_dの打刻レコードを取得できる(): void
    {
        // Arrange
        $record1 = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        $record2 = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2026-03-01 18:00:00',
            'rounded_time' => '2026-03-01 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByIds([$record1->id, $record2->id]);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_idsで空配列の場合は空コレクションを返す(): void
    {
        // Act
        $result = $this->repository->findByIds([]);

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_by_user_id_and_date_time_rangeでユーザーの日時範囲内レコードを取得できる(): void
    {
        // Arrange
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2026-03-01 18:00:00',
            'rounded_time' => '2026-03-01 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-02 09:00:00',
            'rounded_time' => '2026-03-02 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByUserIdAndDateTimeRange(
            $this->company->id,
            $this->user->id,
            '2026-03-01 00:00:00',
            '2026-03-01 23:59:59'
        );

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_user_id_and_date_time_rangeで該当データがない場合は空コレクションを返す(): void
    {
        // Act
        $result = $this->repository->findByUserIdAndDateTimeRange(
            $this->company->id,
            $this->user->id,
            '2026-01-01 00:00:00',
            '2026-01-31 23:59:59'
        );

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_by_company_id_and_date_time_rangeで会社の日時範囲内レコードを取得できる(): void
    {
        // Arrange
        $user2 = User::factory()->forCompany($this->company->id)->create();
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user2->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:30:00',
            'rounded_time' => '2026-03-01 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByCompanyIdAndDateTimeRange(
            $this->company->id,
            '2026-03-01 00:00:00',
            '2026-03-01 23:59:59'
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->first()->relationLoaded('user'));
    }

    public function test_find_by_company_id_and_date_time_rangeで他社データは含まれない(): void
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->forCompany($otherCompany->id)->create();
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByCompanyIdAndDateTimeRange(
            $this->company->id,
            '2026-03-01 00:00:00',
            '2026-03-01 23:59:59'
        );

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_find_latest_by_user_idでユーザーの最新打刻を取得できる(): void
    {
        // Arrange
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2026-03-01 18:00:00',
            'rounded_time' => '2026-03-01 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findLatestByUserId(
            $this->company->id,
            $this->user->id
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(TimeRecordTypeEnum::WORK_END, $result->record_type);
    }

    public function test_find_latest_by_user_idで打刻種別を指定して最新を取得できる(): void
    {
        // Arrange
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2026-03-01 18:00:00',
            'rounded_time' => '2026-03-01 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-02 09:00:00',
            'rounded_time' => '2026-03-02 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findLatestByUserId(
            $this->company->id,
            $this->user->id,
            TimeRecordTypeEnum::WORK_END
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(TimeRecordTypeEnum::WORK_END, $result->record_type);
        $this->assertEquals('2026-03-01 18:00:00', $result->record_time->format('Y-m-d H:i:s'));
    }

    public function test_find_latest_by_user_idで該当データがない場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findLatestByUserId(
            $this->company->id,
            $this->user->id
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_user_id_and_dateでユーザーの当日レコードを取得できる(): void
    {
        // Arrange
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => '2026-03-01 12:00:00',
            'rounded_time' => '2026-03-01 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => '2026-03-01 13:00:00',
            'rounded_time' => '2026-03-01 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2026-03-01 18:00:00',
            'rounded_time' => '2026-03-01 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        // 翌日のデータ（範囲外）
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-02 09:00:00',
            'rounded_time' => '2026-03-02 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByUserIdAndDate(
            $this->company->id,
            $this->user->id,
            '2026-03-01'
        );

        // Assert
        $this->assertCount(4, $result);
        $this->assertEquals(TimeRecordTypeEnum::WORK_START, $result->first()->record_type);
        $this->assertEquals(TimeRecordTypeEnum::WORK_END, $result->last()->record_type);
    }

    public function test_find_by_user_id_and_dateで該当日にデータがない場合は空コレクションを返す(): void
    {
        // Act
        $result = $this->repository->findByUserIdAndDate(
            $this->company->id,
            $this->user->id,
            '2026-03-01'
        );

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_createで新しい打刻レコードを作成できる(): void
    {
        // Arrange
        $data = [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
            'note' => '出勤',
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(TimeRecordTypeEnum::WORK_START, $result->record_type);
        $this->assertEquals(RecordSourceEnum::AUTO, $result->record_source);
        $this->assertEquals('出勤', $result->note);
        $this->assertDatabaseHas('time_records', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START->value,
        ]);
    }

    public function test_updateで既存打刻レコードを更新できる(): void
    {
        // Arrange
        $record = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->update($record->id, [
            'record_time' => '2026-03-01 09:15:00',
            'rounded_time' => '2026-03-01 09:15:00',
            'note' => '遅刻修正',
        ]);

        // Assert
        $this->assertEquals('2026-03-01 09:15:00', $result->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals('遅刻修正', $result->note);
        $this->assertDatabaseHas('time_records', [
            'id' => $record->id,
            'note' => '遅刻修正',
        ]);
    }

    public function test_updateで存在しない_i_dの場合は例外がスローされる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->update(9999, ['note' => 'テスト']);
    }

    public function test_deleteで打刻レコードを削除できる(): void
    {
        // Arrange
        $record = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->delete($record->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('time_records', ['id' => $record->id]);
    }

    public function test_deleteで存在しない_i_dの場合はfalseを返す(): void
    {
        // Act
        $result = $this->repository->delete(9999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_find_by_user_ids_and_date_rangeで複数ユーザーの日付範囲内レコードを取得できる(): void
    {
        // Arrange
        $user2 = User::factory()->forCompany($this->company->id)->create();
        $user3 = User::factory()->forCompany($this->company->id)->create();

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user2->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user3->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
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

    public function test_find_by_user_ids_and_date_rangeで日付範囲外のレコードは含まれない(): void
    {
        // Arrange
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-03-01 09:00:00',
            'rounded_time' => '2026-03-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-04-01 09:00:00',
            'rounded_time' => '2026-04-01 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findByUserIdsAndDateRange(
            $this->company->id,
            [$this->user->id],
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(1, $result);
    }
}

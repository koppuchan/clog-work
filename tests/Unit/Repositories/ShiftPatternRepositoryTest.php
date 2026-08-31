<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Company;
use App\Models\ShiftPattern;
use App\Repositories\ShiftPatternRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/ShiftPatternRepositoryTest.php
 */
class ShiftPatternRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ShiftPatternRepository $repository;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ShiftPatternRepository(new ShiftPattern);
        $this->company = Company::factory()->create();
    }

    public function test_find_by_company_idで会社_i_dに紐づくシフトパターン一覧を取得できる(): void
    {
        // Arrange
        ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '日勤',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);
        ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '22:00',
            'end_time' => '07:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '02:00',
            'break_end' => '03:00',
        ]);

        // Act
        $result = $this->repository->findByCompanyId($this->company->id);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('日勤', $result->first()->name);
    }

    public function test_find_by_company_idで該当データがない場合は空コレクションを返す(): void
    {
        // Act
        $result = $this->repository->findByCompanyId(9999);

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_by_company_idで他社のシフトパターンは含まれない(): void
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '自社パターン',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);
        ShiftPattern::query()->create([
            'company_id' => $otherCompany->id,
            'name' => '他社パターン',
            'start_time' => '10:00',
            'end_time' => '19:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '13:00',
            'break_end' => '14:00',
        ]);

        // Act
        $result = $this->repository->findByCompanyId($this->company->id);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('自社パターン', $result->first()->name);
    }

    public function test_find_by_idで存在するシフトパターンを取得できる(): void
    {
        // Arrange
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '日勤',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        // Act
        $result = $this->repository->findById($shiftPattern->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('日勤', $result->name);
        $this->assertEquals('09:00:00', $result->start_time);
    }

    public function test_find_by_idで存在しない_i_dの場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findById(9999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_id_with_relationsでリレーション付きシフトパターンを取得できる(): void
    {
        // Arrange
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '日勤',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        // Act
        $result = $this->repository->findByIdWithRelations($shiftPattern->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('colors'));
        $this->assertEquals('日勤', $result->name);
    }

    public function test_find_by_id_with_relationsで存在しない_i_dの場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findByIdWithRelations(9999);

        // Assert
        $this->assertNull($result);
    }

    public function test_createで新しいシフトパターンを作成できる(): void
    {
        // Arrange
        $data = [
            'company_id' => $this->company->id,
            'name' => '早番',
            'start_time' => '06:00',
            'end_time' => '15:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '11:00',
            'break_end' => '12:00',
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('早番', $result->name);
        $this->assertEquals('06:00', $result->start_time);
        $this->assertEquals(480, $result->work_minutes);
        $this->assertDatabaseHas('shift_patterns', ['name' => '早番', 'company_id' => $this->company->id]);
    }

    public function test_updateで既存シフトパターンを更新できる(): void
    {
        // Arrange
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '旧パターン',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        // Act
        $result = $this->repository->update($shiftPattern->id, [
            'name' => '新パターン',
            'start_time' => '10:00',
        ]);

        // Assert
        $this->assertEquals('新パターン', $result->name);
        $this->assertEquals('10:00:00', $result->start_time);
        $this->assertDatabaseHas('shift_patterns', ['id' => $shiftPattern->id, 'name' => '新パターン']);
    }

    public function test_updateで存在しない_i_dの場合は例外がスローされる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->update(9999, ['name' => 'テスト']);
    }

    public function test_deleteでシフトパターンを削除できる(): void
    {
        // Arrange
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '削除対象',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        // Act
        $result = $this->repository->delete($shiftPattern->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('shift_patterns', ['id' => $shiftPattern->id]);
    }

    public function test_deleteで存在しない_i_dの場合はfalseを返す(): void
    {
        // Act
        $result = $this->repository->delete(9999);

        // Assert
        $this->assertFalse($result);
    }
}

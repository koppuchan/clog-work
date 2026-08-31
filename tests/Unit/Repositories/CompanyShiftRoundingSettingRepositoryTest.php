<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Company;
use App\Models\CompanyShiftRoundingSetting;
use App\Models\ShiftRoundingUnit;
use App\Repositories\CompanyShiftRoundingSettingRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/CompanyShiftRoundingSettingRepositoryTest.php
 */
class CompanyShiftRoundingSettingRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CompanyShiftRoundingSettingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CompanyShiftRoundingSettingRepository(new CompanyShiftRoundingSetting);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createShiftRoundingUnit(array $attributes): ShiftRoundingUnit
    {
        $model = new ShiftRoundingUnit;
        $model->incrementing = false;
        $model->forceFill($attributes)->save();

        return $model;
    }

    // ========================================
    // findByCompanyId
    // ========================================

    public function test_find_by_company_idで設定が存在する場合_設定が返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $roundingUnit = $this->createShiftRoundingUnit([
            'id' => 201,
            'name' => '15分',
            'minutes' => 15,
        ]);
        CompanyShiftRoundingSetting::query()->create([
            'company_id' => $company->id,
            'rounding_unit_id' => $roundingUnit->id,
        ]);

        // Act
        $result = $this->repository->findByCompanyId($company->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($company->id, $result->company_id);
        $this->assertEquals($roundingUnit->id, $result->rounding_unit_id);
        $this->assertTrue($result->relationLoaded('roundingUnit'));
    }

    public function test_find_by_company_idで設定が存在しない場合_nullが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findByCompanyId($company->id);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_company_idでrounding_unitリレーションが正しくロードされる(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $roundingUnit = $this->createShiftRoundingUnit([
            'id' => 202,
            'name' => '30分',
            'minutes' => 30,
        ]);
        CompanyShiftRoundingSetting::query()->create([
            'company_id' => $company->id,
            'rounding_unit_id' => $roundingUnit->id,
        ]);

        // Act
        $result = $this->repository->findByCompanyId($company->id);

        // Assert
        $this->assertNotNull($result->roundingUnit);
        $this->assertEquals('30分', $result->roundingUnit->name);
        $this->assertEquals(30, $result->roundingUnit->minutes);
    }

    // ========================================
    // getRoundingMinutes
    // ========================================

    public function test_get_rounding_minutesで設定が存在する場合_分数が返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $roundingUnit = $this->createShiftRoundingUnit([
            'id' => 203,
            'name' => '15分',
            'minutes' => 15,
        ]);
        CompanyShiftRoundingSetting::query()->create([
            'company_id' => $company->id,
            'rounding_unit_id' => $roundingUnit->id,
        ]);

        // Act
        $result = $this->repository->getRoundingMinutes($company->id);

        // Assert
        $this->assertEquals(15, $result);
    }

    public function test_get_rounding_minutesで設定が存在しない場合_nullが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->getRoundingMinutes($company->id);

        // Assert
        $this->assertNull($result);
    }

    public function test_get_rounding_minutesで異なる丸め単位の場合_正しい分数が返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $roundingUnit = $this->createShiftRoundingUnit([
            'id' => 204,
            'name' => '5分',
            'minutes' => 5,
        ]);
        CompanyShiftRoundingSetting::query()->create([
            'company_id' => $company->id,
            'rounding_unit_id' => $roundingUnit->id,
        ]);

        // Act
        $result = $this->repository->getRoundingMinutes($company->id);

        // Assert
        $this->assertEquals(5, $result);
    }

    public function test_get_rounding_minutesで別会社の設定は影響しない(): void
    {
        // Arrange
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $roundingUnit = $this->createShiftRoundingUnit([
            'id' => 205,
            'name' => '10分',
            'minutes' => 10,
        ]);
        CompanyShiftRoundingSetting::query()->create([
            'company_id' => $company1->id,
            'rounding_unit_id' => $roundingUnit->id,
        ]);

        // Act
        $result = $this->repository->getRoundingMinutes($company2->id);

        // Assert
        $this->assertNull($result);
    }
}

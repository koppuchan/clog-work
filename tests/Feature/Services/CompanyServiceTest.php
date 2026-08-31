<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CompanyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CompanyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CompanyService::class);
    }

    /**
     * @test
     */
    public function find_by_id_returns_company_when_exists(): void
    {
        // Arrange
        $company = Company::factory()->create(['name' => 'Test Company']);

        // Act
        $result = $this->service->findById($company->id);

        // Assert
        $this->assertEquals('Test Company', $result->name);
    }

    /**
     * @test
     */
    public function find_by_id_throws_exception_when_not_found(): void
    {
        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('会社が見つかりません。');

        // Act
        $this->service->findById(9999);
    }

    /**
     * @test
     */
    public function find_by_id_with_relations_returns_company_with_relations(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->service->findByIdWithRelations($company->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('regularHolidays'));
        $this->assertTrue($result->relationLoaded('shiftRoundingSettings'));
        $this->assertTrue($result->relationLoaded('departments'));
    }

    /**
     * @test
     */
    public function create_creates_new_company(): void
    {
        // Arrange
        $data = ['name' => 'New Company', 'is_closed_on_holidays' => true];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertEquals('New Company', $result->name);
        $this->assertTrue($result->is_closed_on_holidays);
        $this->assertDatabaseHas('companies', ['name' => 'New Company']);
    }

    /**
     * @test
     */
    public function create_throws_exception_when_name_is_empty(): void
    {
        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('会社名は必須です。');

        // Act
        $this->service->create(['name' => '']);
    }

    /**
     * @test
     */
    public function update_settings_updates_company_successfully(): void
    {
        // Arrange
        $company = Company::factory()->create(['name' => 'Old Name']);
        $data = [
            'name' => 'Updated Name',
            'daily_working_hours' => '8.0',
            'payroll_closing_day' => '25',
        ];

        // Act
        $result = $this->service->updateSettings($company->id, $data);

        // Assert
        $this->assertEquals('Updated Name', $result->name);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * @test
     */
    public function update_settings_throws_exception_for_invalid_payroll_closing_day(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('給与締め日は1〜31または"end"を指定してください。');

        // Act
        $this->service->updateSettings($company->id, ['payroll_closing_day' => '50']);
    }

    /**
     * @test
     */
    public function update_settings_throws_exception_for_invalid_daily_working_hours(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('所定労働時間は0より大きく24時間以内で指定してください。');

        // Act
        $this->service->updateSettings($company->id, ['daily_working_hours' => '25.0']);
    }

    /**
     * @test
     */
    public function update_settings_accepts_end_as_payroll_closing_day(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $data = ['payroll_closing_day' => 'end'];

        // Act
        $result = $this->service->updateSettings($company->id, $data);

        // Assert
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'payroll_closing_day' => 'end',
        ]);
    }

    /**
     * @test
     */
    public function delete_removes_company_successfully(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $this->service->delete($company->id);

        // Assert
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    /**
     * @test
     */
    public function delete_throws_exception_when_company_not_found(): void
    {
        // Assert
        $this->expectException(NotFoundException::class);

        // Act
        $this->service->delete(9999);
    }
}

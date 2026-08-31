<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Company;
use App\Repositories\CompanyRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/CompanyRepositoryTest.php
 */
class CompanyRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CompanyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CompanyRepository(new Company);
    }

    public function find_by_id_returns_company_when_exists(): void
    {
        // Arrange
        $company = Company::factory()->create(['name' => 'Test Company']);

        // Act
        $result = $this->repository->findById($company->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Test Company', $result->name);
    }

    public function find_by_id_returns_null_when_not_exists(): void
    {
        // Act
        $result = $this->repository->findById(9999);

        // Assert
        $this->assertNull($result);
    }

    public function find_by_id_with_relations_returns_company_with_relations(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findByIdWithRelations($company->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('regularHolidays'));
        $this->assertTrue($result->relationLoaded('shiftRoundingSettings'));
        $this->assertTrue($result->relationLoaded('departments'));
    }

    public function create_creates_new_company(): void
    {
        // Arrange
        $data = [
            'company_code' => 'TEST01',
            'name' => 'New Company',
            'is_closed_on_holidays' => true,
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('New Company', $result->name);
        $this->assertTrue($result->is_closed_on_holidays);
        $this->assertDatabaseHas('companies', ['name' => 'New Company']);
    }

    public function update_updates_existing_company(): void
    {
        // Arrange
        $company = Company::factory()->create(['name' => 'Old Name']);
        $data = ['name' => 'Updated Name'];

        // Act
        $result = $this->repository->update($company->id, $data);

        // Assert
        $this->assertEquals('Updated Name', $result->name);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Updated Name']);
    }

    public function delete_removes_company(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->delete($company->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function delete_returns_false_when_company_not_found(): void
    {
        // Act
        $result = $this->repository->delete(9999);

        // Assert
        $this->assertFalse($result);
    }

    public function get_all_returns_all_companies(): void
    {
        // Arrange
        Company::factory()->count(3)->create();

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertCount(3, $result);
    }
}

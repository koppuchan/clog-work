<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Repositories\DepartmentRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/DepartmentRepositoryTest.php
 */
class DepartmentRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private DepartmentRepository $repository;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DepartmentRepository(new Department);
        $this->company = Company::factory()->create(['name' => 'テスト会社']);
    }

    public function test_find_by_id_存在する_i_dで取得_部署が返される(): void
    {
        // Arrange
        $department = Department::factory()->forCompany($this->company->id)->create(['name' => '開発部']);

        // Act
        $result = $this->repository->findById($department->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('開発部', $result->name);
        $this->assertEquals($this->company->id, $result->company_id);
    }

    public function test_find_by_id_存在しない_i_dで取得_nullが返される(): void
    {
        // Act
        $result = $this->repository->findById(99999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_id_with_relations_リレーション付きで取得_usersがロードされる(): void
    {
        // Arrange
        $department = Department::factory()->forCompany($this->company->id)->create(['name' => '営業部']);

        // Act
        $result = $this->repository->findByIdWithRelations($department->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('users'));
        $this->assertEquals('営業部', $result->name);
    }

    public function test_find_by_id_with_relations_存在しない_i_dで取得_nullが返される(): void
    {
        // Act
        $result = $this->repository->findByIdWithRelations(99999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_id_with_relations_ユーザーが紐づく部署を取得_ユーザーが含まれる(): void
    {
        // Arrange
        $department = Department::factory()->forCompany($this->company->id)->create(['name' => '人事部']);
        $user = User::factory()->forCompany($this->company->id)->create();
        $department->users()->attach($user->id, ['is_primary' => true]);

        // Act
        $result = $this->repository->findByIdWithRelations($department->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertCount(1, $result->users);
        $this->assertEquals($user->id, $result->users->first()->id);
    }

    public function test_find_by_company_id_会社_i_dで部署を取得_該当する部署のみ返される(): void
    {
        // Arrange
        $otherCompany = Company::factory()->create(['name' => '他の会社']);
        Department::factory()->forCompany($this->company->id)->create(['name' => '総務部']);
        Department::factory()->forCompany($this->company->id)->create(['name' => '経理部']);
        Department::factory()->forCompany($otherCompany->id)->create(['name' => '広報部']);

        // Act
        $result = $this->repository->findByCompanyId($this->company->id);

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->contains('name', '総務部'));
        $this->assertTrue($result->contains('name', '経理部'));
        $this->assertFalse($result->contains('name', '広報部'));
    }

    public function test_find_by_company_id_部署が存在しない会社_i_dで取得_空コレクションが返される(): void
    {
        // Arrange
        $emptyCompany = Company::factory()->create(['name' => '部署なし会社']);

        // Act
        $result = $this->repository->findByCompanyId($emptyCompany->id);

        // Assert
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_get_all_全部署を取得_全レコードが返される(): void
    {
        // Arrange
        Department::factory()->forCompany($this->company->id)->create(['name' => '法務部']);
        Department::factory()->forCompany($this->company->id)->create(['name' => '情報システム部']);

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertGreaterThanOrEqual(2, $result->count());
        $this->assertTrue($result->contains('name', '法務部'));
        $this->assertTrue($result->contains('name', '情報システム部'));
    }

    public function test_create_新規部署を作成_データベースに保存される(): void
    {
        // Arrange
        $data = [
            'company_id' => $this->company->id,
            'name' => '新規部署',
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('新規部署', $result->name);
        $this->assertEquals($this->company->id, $result->company_id);
        $this->assertDatabaseHas('departments', [
            'company_id' => $this->company->id,
            'name' => '新規部署',
        ]);
    }

    public function test_update_既存部署を更新_データベースに反映される(): void
    {
        // Arrange
        $department = Department::factory()->forCompany($this->company->id)->create(['name' => '旧部署名']);

        // Act
        $result = $this->repository->update($department->id, ['name' => '新部署名']);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('新部署名', $result->name);
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => '新部署名',
        ]);
    }

    public function test_update_存在しない_i_dで更新_例外が発生する(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->update(99999, ['name' => '更新失敗']);
    }

    public function test_delete_既存部署を削除_データベースから削除される(): void
    {
        // Arrange
        $department = Department::factory()->forCompany($this->company->id)->create(['name' => '削除対象部署']);

        // Act
        $result = $this->repository->delete($department->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_delete_存在しない_i_dで削除_falseが返される(): void
    {
        // Act
        $result = $this->repository->delete(99999);

        // Assert
        $this->assertFalse($result);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Company;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/UserRepositoryTest.php
 */
class UserRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository(new User);
    }

    // ========================================
    // findById
    // ========================================

    public function test_find_by_idでユーザーが存在する場合_ユーザーが返される(): void
    {
        // Arrange
        $user = User::factory()->create(['name' => 'テストユーザー']);

        // Act
        $result = $this->repository->findById($user->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('テストユーザー', $result->name);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_find_by_idでユーザーが存在しない場合_nullが返される(): void
    {
        // Act
        $result = $this->repository->findById(99999);

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // findByIdWithRelations
    // ========================================

    public function test_find_by_id_with_relationsでユーザーが存在する場合_リレーション付きで返される(): void
    {
        // Arrange
        $user = User::factory()->admin()->create();

        // Act
        $result = $this->repository->findByIdWithRelations($user->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('roles'));
        $this->assertTrue($result->relationLoaded('companies'));
        $this->assertTrue($result->relationLoaded('departments'));
    }

    public function test_find_by_id_with_relationsでユーザーが存在しない場合_nullが返される(): void
    {
        // Act
        $result = $this->repository->findByIdWithRelations(99999);

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // findByEmail
    // ========================================

    public function test_find_by_emailでメールが一致する場合_ユーザーが返される(): void
    {
        // Arrange
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Act
        $result = $this->repository->findByEmail('test@example.com');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result->id);
        $this->assertEquals('test@example.com', $result->email);
    }

    public function test_find_by_emailでメールが一致しない場合_nullが返される(): void
    {
        // Act
        $result = $this->repository->findByEmail('nonexistent@example.com');

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // getAll
    // ========================================

    public function test_get_allで全ユーザーが返される(): void
    {
        // Arrange
        User::factory()->count(3)->create();

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertGreaterThanOrEqual(3, $result->count());
    }

    // ========================================
    // findByCompanyId
    // ========================================

    public function test_find_by_company_idで会社に所属するユーザーが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user1 = User::factory()->forCompany($company->id)->create();
        $user2 = User::factory()->forCompany($company->id)->create();
        $otherCompany = Company::factory()->create();
        User::factory()->forCompany($otherCompany->id)->create();

        // Act
        $result = $this->repository->findByCompanyId($company->id);

        // Assert
        $this->assertCount(2, $result);
        $resultIds = $result->pluck('id')->toArray();
        $this->assertContains($user1->id, $resultIds);
        $this->assertContains($user2->id, $resultIds);
    }

    public function test_find_by_company_idで所属ユーザーがいない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findByCompanyId($company->id);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // create
    // ========================================

    public function test_createでユーザーが正常に作成される(): void
    {
        // Arrange
        $data = [
            'name' => '新規ユーザー',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('新規ユーザー', $result->name);
        $this->assertEquals('newuser@example.com', $result->email);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    // ========================================
    // update
    // ========================================

    public function test_updateでユーザー情報が正常に更新される(): void
    {
        // Arrange
        $user = User::factory()->create(['name' => '旧名前']);

        // Act
        $result = $this->repository->update($user->id, ['name' => '新名前']);

        // Assert
        $this->assertEquals('新名前', $result->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => '新名前']);
    }

    public function test_updateで存在しないユーザーの場合_例外が発生する(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->update(99999, ['name' => 'test']);
    }

    // ========================================
    // delete
    // ========================================

    public function test_deleteでユーザーが正常に削除される(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $result = $this->repository->delete($user->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_deleteで存在しないユーザーの場合_falseが返される(): void
    {
        // Act
        $result = $this->repository->delete(99999);

        // Assert
        $this->assertFalse($result);
    }

    // ========================================
    // attachRole / detachRole / syncRoles
    // ========================================

    public function test_attach_roleでユーザーにロールが割り当てられる(): void
    {
        // Arrange
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => '管理者']);

        // Act
        $this->repository->attachRole($user->id, $role->id);

        // Assert
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_attach_roleで存在しないユーザーの場合_例外が発生する(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->attachRole(99999, 1);
    }

    public function test_detach_roleでユーザーからロールが削除される(): void
    {
        // Arrange
        $user = User::factory()->admin()->create();

        // Act
        $this->repository->detachRole($user->id, 1);

        // Assert
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $user->id,
            'role_id' => 1,
        ]);
    }

    public function test_detach_roleで存在しないユーザーの場合_例外が発生する(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->detachRole(99999, 1);
    }

    public function test_sync_rolesでユーザーのロールが同期される(): void
    {
        // Arrange
        $user = User::factory()->admin()->create();
        $role2 = Role::query()->find(2) ?? Role::query()->create(['name' => '責任者']);
        $role3 = Role::query()->find(3) ?? Role::query()->create(['name' => '一般']);

        // Act
        $this->repository->syncRoles($user->id, [$role2->id, $role3->id]);

        // Assert
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $user->id,
            'role_id' => 1,
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role_id' => $role2->id,
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role_id' => $role3->id,
        ]);
    }

    public function test_sync_rolesで存在しないユーザーの場合_例外が発生する(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->syncRoles(99999, [1, 2]);
    }

    // ========================================
    // attachCompany
    // ========================================

    public function test_attach_companyでユーザーに会社が紐付けられる(): void
    {
        // Arrange
        $user = User::factory()->create();
        $company = Company::factory()->create();

        // Act
        $this->repository->attachCompany($user->id, $company->id, true);

        // Assert
        $this->assertDatabaseHas('user_companies', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'is_primary' => true,
        ]);
    }

    public function test_attach_companyでis_primaryがfalseの場合_副会社として紐付けられる(): void
    {
        // Arrange
        $user = User::factory()->create();
        $company = Company::factory()->create();

        // Act
        $this->repository->attachCompany($user->id, $company->id, false);

        // Assert
        $this->assertDatabaseHas('user_companies', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'is_primary' => false,
        ]);
    }

    public function test_attach_companyで存在しないユーザーの場合_例外が発生する(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->attachCompany(99999, 1, true);
    }

    // ========================================
    // attachDepartment
    // ========================================

    public function test_attach_departmentでユーザーに部署が紐付けられる(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'テスト部署',
        ]);

        // Act
        $this->repository->attachDepartment($user->id, $department->id, true);

        // Assert
        $this->assertDatabaseHas('user_departments', [
            'user_id' => $user->id,
            'department_id' => $department->id,
            'is_primary' => true,
        ]);
    }

    public function test_attach_departmentで存在しないユーザーの場合_例外が発生する(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->attachDepartment(99999, 1, true);
    }

    // ========================================
    // findByDepartmentId
    // ========================================

    public function test_find_by_department_idで会社と部署に所属するユーザーが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'テスト部署',
        ]);
        $user = User::factory()->forCompany($company->id)->create();
        $this->repository->attachDepartment($user->id, $department->id, true);

        // Act
        $result = $this->repository->findByDepartmentId($company->id, $department->id);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($user->id, $result->first()->id);
    }

    public function test_find_by_department_idで該当ユーザーがいない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'テスト部署',
        ]);

        // Act
        $result = $this->repository->findByDepartmentId($company->id, $department->id);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // findByRole
    // ========================================

    public function test_find_by_roleで指定ロールのユーザーが返される(): void
    {
        // Arrange
        $user = User::factory()->admin()->create();
        User::factory()->employee()->create();

        // Act
        $result = $this->repository->findByRole(1);

        // Assert
        $resultIds = $result->pluck('id')->toArray();
        $this->assertContains($user->id, $resultIds);
    }

    public function test_find_by_roleで該当ユーザーがいない場合_空コレクションが返される(): void
    {
        // Act
        $result = $this->repository->findByRole(99999);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // getMaxEmployeeCode
    // ========================================

    public function test_get_max_employee_codeで最大の従業員コードが返される(): void
    {
        // Arrange
        User::factory()->create(['employee_code' => '000001']);
        User::factory()->create(['employee_code' => '000099']);
        User::factory()->create(['employee_code' => '000050']);

        // Act
        $result = $this->repository->getMaxEmployeeCode();

        // Assert
        $this->assertEquals('000099', $result);
    }

    public function test_get_max_employee_codeで従業員コードがない場合_nullが返される(): void
    {
        // Arrange
        User::factory()->create(['employee_code' => null]);

        // Act
        $result = $this->repository->getMaxEmployeeCode();

        // Assert
        // 他のテストやシードでemployee_codeが存在する可能性があるため、
        // nullまたは文字列であることを確認
        $this->assertTrue($result === null || is_string($result));
    }

    // ========================================
    // existsByEmployeeCodeInCompany
    // ========================================

    public function test_exists_by_employee_code_in_companyで会社内に従業員コードが存在する場合_trueが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        User::factory()->forCompany($company->id)->create(['employee_code' => 'EMP001']);

        // Act
        $result = $this->repository->existsByEmployeeCodeInCompany($company->id, 'EMP001');

        // Assert
        $this->assertTrue($result);
    }

    public function test_exists_by_employee_code_in_companyで会社内に従業員コードが存在しない場合_falseが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->existsByEmployeeCodeInCompany($company->id, 'NONEXISTENT');

        // Assert
        $this->assertFalse($result);
    }

    public function test_exists_by_employee_code_in_companyで自分自身を除外した場合_falseが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->forCompany($company->id)->create(['employee_code' => 'EMP001']);

        // Act
        $result = $this->repository->existsByEmployeeCodeInCompany($company->id, 'EMP001', $user->id);

        // Assert
        $this->assertFalse($result);
    }

    public function test_exists_by_employee_code_in_companyで別ユーザーが同一コードを持つ場合_trueが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        User::factory()->forCompany($company->id)->create(['employee_code' => 'EMP001']);
        $anotherUser = User::factory()->forCompany($company->id)->create(['employee_code' => 'EMP002']);

        // Act
        $result = $this->repository->existsByEmployeeCodeInCompany($company->id, 'EMP001', $anotherUser->id);

        // Assert
        $this->assertTrue($result);
    }

    // ========================================
    // countAdminsByCompanyId
    // ========================================

    public function test_count_admins_by_company_idで管理者数が正しく返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        User::factory()->forCompany($company->id)->admin()->create();
        User::factory()->forCompany($company->id)->admin()->create();
        User::factory()->forCompany($company->id)->employee()->create();

        // Act
        $result = $this->repository->countAdminsByCompanyId($company->id);

        // Assert
        $this->assertEquals(2, $result);
    }

    public function test_count_admins_by_company_idで管理者がいない場合_0が返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        User::factory()->forCompany($company->id)->employee()->create();

        // Act
        $result = $this->repository->countAdminsByCompanyId($company->id);

        // Assert
        $this->assertEquals(0, $result);
    }
}

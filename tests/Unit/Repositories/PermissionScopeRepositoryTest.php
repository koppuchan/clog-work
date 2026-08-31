<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\PermissionScope;
use App\Repositories\PermissionScopeRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/PermissionScopeRepositoryTest.php
 */
class PermissionScopeRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private PermissionScopeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PermissionScopeRepository(new PermissionScope);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPermissionScope(array $attributes): PermissionScope
    {
        $model = new PermissionScope;
        $model->incrementing = false;
        $model->forceFill($attributes)->save();

        return $model;
    }

    public function test_get_all_全権限スコープを取得_i_d順で返される(): void
    {
        // Arrange
        $scope1 = $this->createPermissionScope([
            'id' => 201,
            'scope_code' => 'test-scope-a',
            'scope_name' => 'テストスコープA',
        ]);
        $scope2 = $this->createPermissionScope([
            'id' => 202,
            'scope_code' => 'test-scope-b',
            'scope_name' => 'テストスコープB',
        ]);

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertGreaterThanOrEqual(2, $result->count());
        $this->assertTrue($result->contains('scope_code', 'test-scope-a'));
        $this->assertTrue($result->contains('scope_code', 'test-scope-b'));

        // ID順でソートされていることを確認
        $ids = $result->pluck('id')->toArray();
        $sortedIds = $ids;
        sort($sortedIds);
        $this->assertEquals($sortedIds, $ids);
    }

    public function test_find_by_id_存在する_i_dで取得_権限スコープが返される(): void
    {
        // Arrange
        $scope = $this->createPermissionScope([
            'id' => 203,
            'scope_code' => 'find-by-id-test',
            'scope_name' => 'ID検索テスト',
        ]);

        // Act
        $result = $this->repository->findById($scope->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('find-by-id-test', $result->scope_code);
        $this->assertEquals('ID検索テスト', $result->scope_name);
    }

    public function test_find_by_id_存在しない_i_dで取得_nullが返される(): void
    {
        // Act
        $result = $this->repository->findById(99999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_code_存在するコードで取得_権限スコープが返される(): void
    {
        // Arrange
        $this->createPermissionScope([
            'id' => 204,
            'scope_code' => 'find-by-code-test',
            'scope_name' => 'コード検索テスト',
        ]);

        // Act
        $result = $this->repository->findByCode('find-by-code-test');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('find-by-code-test', $result->scope_code);
        $this->assertEquals('コード検索テスト', $result->scope_name);
    }

    public function test_find_by_code_存在しないコードで取得_nullが返される(): void
    {
        // Act
        $result = $this->repository->findByCode('non-existent-code');

        // Assert
        $this->assertNull($result);
    }
}

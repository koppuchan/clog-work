<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\PermissionResource;
use App\Repositories\PermissionResourceRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/PermissionResourceRepositoryTest.php
 */
class PermissionResourceRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private PermissionResourceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PermissionResourceRepository(new PermissionResource);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPermissionResource(array $attributes): PermissionResource
    {
        $model = new PermissionResource;
        $model->incrementing = false;
        $model->forceFill($attributes)->save();

        return $model;
    }

    public function test_get_all_全権限リソースを取得_i_d順で返される(): void
    {
        // Arrange
        $resource1 = $this->createPermissionResource([
            'id' => 201,
            'resource_code' => 'test-resource-a',
            'resource_name' => 'テストリソースA',
        ]);
        $resource2 = $this->createPermissionResource([
            'id' => 202,
            'resource_code' => 'test-resource-b',
            'resource_name' => 'テストリソースB',
        ]);

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertGreaterThanOrEqual(2, $result->count());
        $this->assertTrue($result->contains('resource_code', 'test-resource-a'));
        $this->assertTrue($result->contains('resource_code', 'test-resource-b'));

        // ID順でソートされていることを確認
        $ids = $result->pluck('id')->toArray();
        $sortedIds = $ids;
        sort($sortedIds);
        $this->assertEquals($sortedIds, $ids);
    }

    public function test_find_by_id_存在する_i_dで取得_権限リソースが返される(): void
    {
        // Arrange
        $resource = $this->createPermissionResource([
            'id' => 203,
            'resource_code' => 'find-by-id-test',
            'resource_name' => 'ID検索テスト',
        ]);

        // Act
        $result = $this->repository->findById($resource->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('find-by-id-test', $result->resource_code);
        $this->assertEquals('ID検索テスト', $result->resource_name);
    }

    public function test_find_by_id_存在しない_i_dで取得_nullが返される(): void
    {
        // Act
        $result = $this->repository->findById(99999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_code_存在するコードで取得_権限リソースが返される(): void
    {
        // Arrange
        $this->createPermissionResource([
            'id' => 204,
            'resource_code' => 'find-by-code-test',
            'resource_name' => 'コード検索テスト',
        ]);

        // Act
        $result = $this->repository->findByCode('find-by-code-test');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('find-by-code-test', $result->resource_code);
        $this->assertEquals('コード検索テスト', $result->resource_name);
    }

    public function test_find_by_code_存在しないコードで取得_nullが返される(): void
    {
        // Act
        $result = $this->repository->findByCode('non-existent-code');

        // Assert
        $this->assertNull($result);
    }
}

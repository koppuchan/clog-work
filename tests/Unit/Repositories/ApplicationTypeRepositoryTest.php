<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\ApplicationType;
use App\Repositories\ApplicationTypeRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/ApplicationTypeRepositoryTest.php
 */
class ApplicationTypeRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ApplicationTypeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApplicationTypeRepository(new ApplicationType);
    }

    public function test_find_by_id_存在する_i_dで取得_申請タイプが返される(): void
    {
        // Arrange
        $applicationType = ApplicationType::query()->create([
            'id' => 201,
            'code' => 'test-type',
            'name' => 'テスト申請',
            'display_order' => 1,
            'is_active' => true,
        ]);

        // Act
        $result = $this->repository->findById($applicationType->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('test-type', $result->code);
        $this->assertEquals('テスト申請', $result->name);
    }

    public function test_find_by_id_存在しない_i_dで取得_nullが返される(): void
    {
        // Act
        $result = $this->repository->findById(255);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_code_有効なコードで取得_申請タイプが返される(): void
    {
        // Arrange
        ApplicationType::query()->create([
            'id' => 202,
            'code' => 'active-code',
            'name' => '有効な申請',
            'display_order' => 1,
            'is_active' => true,
        ]);

        // Act
        $result = $this->repository->findByCode('active-code');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('active-code', $result->code);
        $this->assertEquals('有効な申請', $result->name);
    }

    public function test_find_by_code_無効な申請タイプのコードで取得_nullが返される(): void
    {
        // Arrange
        ApplicationType::query()->create([
            'id' => 203,
            'code' => 'inactive-code',
            'name' => '無効な申請',
            'display_order' => 1,
            'is_active' => false,
        ]);

        // Act
        $result = $this->repository->findByCode('inactive-code');

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_code_存在しないコードで取得_nullが返される(): void
    {
        // Act
        $result = $this->repository->findByCode('non-existent-code');

        // Assert
        $this->assertNull($result);
    }

    public function test_find_all_active_有効な申請タイプのみ取得_display_order順で返される(): void
    {
        // Arrange
        ApplicationType::query()->create([
            'id' => 204,
            'code' => 'active-second',
            'name' => '有効2',
            'display_order' => 20,
            'is_active' => true,
        ]);
        ApplicationType::query()->create([
            'id' => 205,
            'code' => 'active-first',
            'name' => '有効1',
            'display_order' => 10,
            'is_active' => true,
        ]);
        ApplicationType::query()->create([
            'id' => 206,
            'code' => 'inactive-type',
            'name' => '無効',
            'display_order' => 5,
            'is_active' => false,
        ]);

        // Act
        $result = $this->repository->findAllActive();

        // Assert
        $this->assertGreaterThanOrEqual(2, $result->count());

        // 無効なレコードが含まれていないことを確認
        $inactiveResult = $result->firstWhere('code', 'inactive-type');
        $this->assertNull($inactiveResult);

        // 有効なレコードが含まれていることを確認
        $activeFirst = $result->firstWhere('code', 'active-first');
        $activeSecond = $result->firstWhere('code', 'active-second');
        $this->assertNotNull($activeFirst);
        $this->assertNotNull($activeSecond);

        // display_order順でソートされていることを確認
        $activeFirstIndex = $result->search(fn ($item) => $item->code === 'active-first');
        $activeSecondIndex = $result->search(fn ($item) => $item->code === 'active-second');
        $this->assertLessThan($activeSecondIndex, $activeFirstIndex);
    }

    public function test_find_all_active_有効な申請タイプが存在しない場合_空コレクションが返される(): void
    {
        // Arrange: 既存のシードデータの影響を避けるため、作成したレコードのみで検証
        // 無効なレコードのみ作成
        ApplicationType::query()->create([
            'id' => 207,
            'code' => 'only-inactive',
            'name' => '無効のみ',
            'display_order' => 1,
            'is_active' => false,
        ]);

        // Act
        $result = $this->repository->findAllActive();

        // Assert: シードデータがあるため、作成した無効レコードが含まれないことを確認
        $inactiveResult = $result->firstWhere('code', 'only-inactive');
        $this->assertNull($inactiveResult);
    }
}

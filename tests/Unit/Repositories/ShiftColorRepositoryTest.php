<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\ShiftColor;
use App\Repositories\ShiftColorRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/ShiftColorRepositoryTest.php
 */
class ShiftColorRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ShiftColorRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ShiftColorRepository(new ShiftColor);
    }

    public function test_find_all_全シフトカラーを取得_i_d順で返される(): void
    {
        // Arrange
        $color1 = ShiftColor::query()->create([
            'background_color' => '#FF0000',
            'text_color' => '#FFFFFF',
        ]);
        $color2 = ShiftColor::query()->create([
            'background_color' => '#00FF00',
            'text_color' => '#000000',
        ]);

        // Act
        $result = $this->repository->findAll();

        // Assert
        $this->assertGreaterThanOrEqual(2, $result->count());
        $this->assertTrue($result->contains('background_color', '#FF0000'));
        $this->assertTrue($result->contains('background_color', '#00FF00'));

        // ID順でソートされていることを確認
        $ids = $result->pluck('id')->toArray();
        $sortedIds = $ids;
        sort($sortedIds);
        $this->assertEquals($sortedIds, $ids);
    }

    public function test_find_all_レコードが存在する場合_コレクションが返される(): void
    {
        // Arrange
        ShiftColor::query()->create([
            'background_color' => '#0000FF',
            'text_color' => '#FFFFFF',
        ]);

        // Act
        $result = $this->repository->findAll();

        // Assert
        $this->assertFalse($result->isEmpty());
        $firstColor = $result->firstWhere('background_color', '#0000FF');
        $this->assertNotNull($firstColor);
        $this->assertEquals('#FFFFFF', $firstColor->text_color);
    }

    public function test_find_all_複数レコードの属性確認_背景色とテキスト色が正しい(): void
    {
        // Arrange
        ShiftColor::query()->create([
            'background_color' => '#AABBCC',
            'text_color' => '#112233',
        ]);
        ShiftColor::query()->create([
            'background_color' => '#DDEEFF',
            'text_color' => '#445566',
        ]);

        // Act
        $result = $this->repository->findAll();

        // Assert
        $colorAA = $result->firstWhere('background_color', '#AABBCC');
        $this->assertNotNull($colorAA);
        $this->assertEquals('#112233', $colorAA->text_color);

        $colorDD = $result->firstWhere('background_color', '#DDEEFF');
        $this->assertNotNull($colorDD);
        $this->assertEquals('#445566', $colorDD->text_color);
    }
}

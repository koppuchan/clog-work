<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\LaborAlertService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * スタッフ本人向けの労務アラート抽出の検証。
 *
 * スタッフ画面には他のスタッフのアラートを出さない。
 */
class LaborAlertForUserTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 会社全体のアラートを差し替えたサービスを用意する
     *
     * @param  array<int, array<string, mixed>>  $alerts
     */
    private function serviceReturning(array $alerts): LaborAlertService
    {
        return new class($alerts) extends LaborAlertService
        {
            /**
             * @param  array<int, array<string, mixed>>  $alerts
             */
            public function __construct(private array $alerts)
            {
                // 依存は使用しないため親のコンストラクタは呼ばない
            }

            public function getAlerts(int $companyId, int $year, int $month): Collection
            {
                return collect($this->alerts);
            }
        };
    }

    /**
     * @test
     */
    public function 本人のアラートだけが抽出される(): void
    {
        // Arrange
        $service = $this->serviceReturning([
            ['userId' => 1, 'message' => '本人の警告'],
            ['userId' => 2, 'message' => '別のスタッフの警告'],
            ['userId' => 1, 'message' => '本人の注意喚起'],
        ]);

        // Act
        $alerts = $service->getAlertsForUser(companyId: 10, userId: 1, year: 2026, month: 9);

        // Assert
        $this->assertCount(2, $alerts);
        $this->assertSame(['本人の警告', '本人の注意喚起'], $alerts->pluck('message')->all());
    }

    /**
     * @test
     */
    public function 対象がなければ空になる(): void
    {
        // Arrange
        $service = $this->serviceReturning([
            ['userId' => 2, 'message' => '別のスタッフの警告'],
        ]);

        // Act
        $alerts = $service->getAlertsForUser(companyId: 10, userId: 1, year: 2026, month: 9);

        // Assert
        $this->assertTrue($alerts->isEmpty());
    }

    /**
     * @test
     */
    public function 添字は詰め直される(): void
    {
        // Arrange: 先頭が他人のアラートでも、結果は 0 始まりの配列になる
        $service = $this->serviceReturning([
            ['userId' => 2, 'message' => '別のスタッフ'],
            ['userId' => 1, 'message' => '本人'],
        ]);

        // Act
        $alerts = $service->getAlertsForUser(companyId: 10, userId: 1, year: 2026, month: 9);

        // Assert: JSON化した際にオブジェクトではなく配列になる
        $this->assertSame([0], $alerts->keys()->all());
    }
}

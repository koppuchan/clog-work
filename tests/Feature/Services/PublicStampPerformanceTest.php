<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\PublicStampService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 打刻まわりの発行クエリ数の検証。
 *
 * 打刻は端末から繰り返し呼ばれるため、同じ問い合わせを重ねないようにする。
 */
class PublicStampPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['company_code' => '960001']);
        $this->user = User::factory()->create(['name' => '打刻 太郎']);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    /**
     * 実行中に発行されたクエリ件数を数える
     */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @test
     */
    public function 打刻時の判定で同じユーザーを繰り返し問い合わせない(): void
    {
        // Arrange: 打刻処理は所属確認・退職確認・パスワード照合の3つを続けて行う
        $service = app(PublicStampService::class);

        // Act
        $queries = $this->countQueries(function () use ($service) {
            $service->isUserInCompany($this->user->id, $this->company->id);
            $service->isUserRetired($this->user->id);
            $service->verifyPassword($this->user->id, 'dummy');
        });

        // Assert: ユーザーの取得は1回。所属確認の1回とあわせて2クエリに収まる
        $this->assertLessThanOrEqual(2, $queries, "発行クエリ数が想定を超えています（{$queries}件）");
    }

    /**
     * @test
     */
    public function 打刻画面のユーザー一覧は人数に関わらずクエリ数が増えない(): void
    {
        // Arrange
        $service = app(PublicStampService::class);
        foreach (range(1, 20) as $i) {
            $extra = User::factory()->create(['name' => sprintf('スタッフ%02d', $i)]);
            $extra->companies()->attach($this->company->id, ['is_primary' => false]);
        }

        // Act
        $queries = $this->countQueries(fn () => $service->getActiveUsersByCompanyId($this->company->id));

        // Assert: 役割のイーガーロードをやめたため1クエリで済む
        $this->assertSame(1, $queries, "発行クエリ数が想定を超えています（{$queries}件）");
    }

    /**
     * @test
     */
    public function 打刻画面のユーザー一覧から退職者が除外される(): void
    {
        // Arrange
        $retired = User::factory()->create(['name' => '退職 花子', 'is_retired' => true]);
        $retired->companies()->attach($this->company->id, ['is_primary' => false]);

        // Act
        $names = app(PublicStampService::class)
            ->getActiveUsersByCompanyId($this->company->id)
            ->pluck('name')
            ->all();

        // Assert
        $this->assertContains('打刻 太郎', $names);
        $this->assertNotContains('退職 花子', $names);
    }

    /**
     * @test
     */
    public function 打刻画面のユーザー一覧は氏名順に並ぶ(): void
    {
        // Arrange
        foreach (['佐藤', '伊藤', '鈴木'] as $name) {
            $u = User::factory()->create(['name' => $name]);
            $u->companies()->attach($this->company->id, ['is_primary' => false]);
        }

        // Act
        $names = app(PublicStampService::class)
            ->getActiveUsersByCompanyId($this->company->id)
            ->pluck('name')
            ->all();

        // Assert
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
    }
}

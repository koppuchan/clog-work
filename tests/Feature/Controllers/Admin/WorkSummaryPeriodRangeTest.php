<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 勤務実績を過去どこまで遡って参照できるかの検証。
 *
 * 画面の期間選択は過去66ヶ月（5年6ヶ月）分を提示する。
 * サーバー側がその範囲の日付を受け付けることを確認する。
 */
class WorkSummaryPeriodRangeTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['company_code' => '950001']);

        $this->admin = User::factory()->create([
            'name' => '管理 太郎',
            'email_verified_at' => now(),
        ]);
        $this->admin->companies()->attach($company->id, ['is_primary' => true]);
        $this->admin->roles()->attach(1);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function 遡る月数(): array
    {
        return [
            '1ヶ月前' => [1],
            '12ヶ月前' => [12],
            '36ヶ月前' => [36],
            '66ヶ月前（5年6ヶ月）' => [66],
        ];
    }

    /**
     * @test
     *
     * @dataProvider 遡る月数
     */
    public function 過去の期間を指定しても勤務実績を表示できる(int $monthsAgo): void
    {
        // Arrange
        $target = Carbon::now()->subMonths($monthsAgo);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get(sprintf(
            '/admin/reports?start_date=%s&end_date=%s',
            $target->copy()->startOfMonth()->toDateString(),
            $target->copy()->endOfMonth()->toDateString(),
        ));

        // Assert
        $response->assertOk();
    }

    /**
     * @test
     */
    public function 先の期間を指定しても表示できる(): void
    {
        // Arrange: シフトの事前登録のため、先の月も選択できる
        $target = Carbon::now()->addMonths(11);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get(sprintf(
            '/admin/reports?start_date=%s&end_date=%s',
            $target->copy()->startOfMonth()->toDateString(),
            $target->copy()->endOfMonth()->toDateString(),
        ));

        // Assert
        $response->assertOk();
    }
}

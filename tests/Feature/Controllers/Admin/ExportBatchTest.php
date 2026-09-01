<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 全従業員出力のバッチ分割。
 *
 * 人数が多いと一度の出力でサーバが応答しなくなるため、内部では
 * バッチサイズごとに処理し、画面からは範囲を指定して取得できる。
 */
class ExportBatchTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.export_batch_size' => 3]);

        $this->company = Company::factory()->create();

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create(['name' => '管理者', 'is_retired' => false]);

        foreach (['一郎', '二郎', '三郎', '四郎', '五郎'] as $name) {
            User::factory()
                ->forCompany($this->company->id)
                ->employee()
                ->create(['name' => $name, 'is_retired' => false]);
        }
    }

    private function exportCsv(array $query): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'admin')->get(
            '/admin/reports/export/csv?'.http_build_query($query)
        );
    }

    /**
     * @test
     */
    public function バッチ指定なしなら全員が1つのファイルに入る(): void
    {
        $response = $this->exportCsv([
            'scope' => 'all',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('全従業員', $this->filenameOf($response));
    }

    /**
     * @test
     */
    public function 一つ目のバッチはバッチサイズ分の人数になる(): void
    {
        $response = $this->exportCsv([
            'scope' => 'all',
            'batch' => 1,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);

        $response->assertStatus(200);
        // 管理者を含めて6名、バッチサイズ3なので 1〜3名
        $this->assertStringContainsString('1〜3名', $this->filenameOf($response));
    }

    /**
     * @test
     */
    public function 最後のバッチは残りの人数までになる(): void
    {
        $response = $this->exportCsv([
            'scope' => 'all',
            'batch' => 2,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('4〜6名', $this->filenameOf($response));
    }

    /**
     * @test
     */
    public function 該当者がいないバッチは404になる(): void
    {
        $response = $this->exportCsv([
            'scope' => 'all',
            'batch' => 9,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);

        $response->assertStatus(404);
    }

    private function filenameOf(\Illuminate\Testing\TestResponse $response): string
    {
        return urldecode($response->headers->get('content-disposition') ?? '');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 全従業員のExcel出力は、1つのファイルにスタッフごとのシートで出力される。
 *
 * クライアント要望: 一人一ファイルではなく、一つのファイルにシートを全員分で
 * 出力できないか（印刷するのが非常に手間）。以前は1人1ファイルをZIPにまとめていた。
 */
class ExportExcelAllTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.export_batch_size' => 3]);

        $company = Company::factory()->create();

        $this->admin = User::factory()
            ->forCompany($company->id)
            ->admin()
            ->create(['name' => '管理者', 'is_retired' => false]);

        foreach (['一郎', '二郎', '三郎', '四郎', '五郎'] as $name) {
            User::factory()
                ->forCompany($company->id)
                ->employee()
                ->create(['name' => $name, 'is_retired' => false]);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function exportExcel(array $query): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')->get(
            '/admin/reports/export/excel?'.http_build_query($query + [
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-31',
            ])
        );
    }

    /**
     * @return array<int, string>
     */
    private function sheetNamesOf(TestResponse $response): array
    {
        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getSheetNames();
    }

    private function filenameOf(TestResponse $response): string
    {
        return urldecode($response->headers->get('content-disposition') ?? '');
    }

    /**
     * @test
     */
    public function 全従業員は1つの_excelファイルにスタッフごとのシートで出力される(): void
    {
        $response = $this->exportExcel(['scope' => 'all']);

        $response->assertStatus(200);
        $this->assertStringContainsString('全従業員', $this->filenameOf($response));
        $this->assertStringContainsString('.xlsx', $this->filenameOf($response));
        $this->assertStringNotContainsString('.zip', $this->filenameOf($response));
        $this->assertCount(6, $this->sheetNamesOf($response)); // 管理者 + 5名
    }

    /**
     * @test
     */
    public function 範囲を指定するとその人数分のシートだけになる(): void
    {
        $response = $this->exportExcel(['scope' => 'all', 'batch' => 2]);

        $response->assertStatus(200);
        $this->assertStringContainsString('4〜6名', $this->filenameOf($response));
        $this->assertCount(3, $this->sheetNamesOf($response));
    }

    /**
     * @test
     */
    public function 個別出力は従来どおり選んだ1人のシートだけになる(): void
    {
        $response = $this->exportExcel(['scope' => 'single']);

        $response->assertStatus(200);
        $this->assertCount(1, $this->sheetNamesOf($response));
    }
}

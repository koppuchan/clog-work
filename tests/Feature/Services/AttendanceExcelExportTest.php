<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\AttendanceExcelExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 勤務実績Excelの出力検証。
 *
 * 集計欄と合計行はシート側の数式で算出される。出力処理がそこへ値を書くと
 * 数式が失われ、給与計算に使えなくなる。出力後も数式が残ることを固定する。
 */
class AttendanceExcelExportTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Company $company;

    private string $generatedPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['company_code' => '970001']);
        $this->user = User::factory()->create([
            'name' => '出力 太郎',
            'employee_code' => '000123',
        ]);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->generatedPath !== '' && file_exists($this->generatedPath)) {
            unlink($this->generatedPath);
        }

        parent::tearDown();
    }

    private function generatedSheet(): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $this->generatedPath = app(AttendanceExcelExportService::class)->generate(
            $this->company->id,
            $this->user,
            '2026-06-21',
            '2026-07-20',
        );

        return IOFactory::load($this->generatedPath)->getActiveSheet();
    }

    /**
     * @test
     */
    public function 出力後も集計欄の数式が残っている(): void
    {
        $sheet = $this->generatedSheet();

        foreach (['G4', 'H4', 'I4', 'J4', 'K4', 'L4', 'M4', 'N4', 'O4', 'P4', 'R4', 'S4', 'T4', 'U4'] as $cell) {
            $this->assertStringStartsWith(
                '=',
                (string) $sheet->getCell($cell)->getValue(),
                "{$cell} の数式が出力時に失われています",
            );
        }
    }

    /**
     * @test
     */
    public function 出力後も合計行の数式が残っている(): void
    {
        $sheet = $this->generatedSheet();

        foreach (['M38', 'N38', 'O38', 'P38', 'Q38'] as $cell) {
            $this->assertStringStartsWith(
                '=',
                (string) $sheet->getCell($cell)->getValue(),
                "{$cell} の数式が出力時に失われています",
            );
        }
    }

    /**
     * @test
     */
    public function スタッフの識別情報が出力される(): void
    {
        $sheet = $this->generatedSheet();

        $this->assertSame('000123', (string) $sheet->getCell('A4')->getValue());
        $this->assertSame('出力 太郎', (string) $sheet->getCell('B4')->getValue());
    }

    /**
     * @test
     */
    public function 日付欄が締め期間どおりに並ぶ(): void
    {
        $sheet = $this->generatedSheet();

        // 6/21〜7/20 は30日間。7行目から36行目までが埋まる
        $this->assertStringStartsWith('6/21', (string) $sheet->getCell('A7')->getValue());
        $this->assertStringStartsWith('7/20', (string) $sheet->getCell('A36')->getValue());

        // 31日ある月に備えて37行目まで用意されているが、今回は空のまま
        $this->assertSame('', (string) $sheet->getCell('A37')->getValue());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\User;
use App\Services\AttendanceExcelExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * 勤務実績Excelを、全員分を1つのファイルにスタッフごとのシートで出力する検証と、
 * 開いたときの表示位置・ウィンドウ枠の固定の検証。
 *
 * クライアント要望:
 * - 現在は1人1ファイルのため、一つのファイルにシートを全員分で出力できないか
 *   （印刷するのが非常に手間）。
 * - デフォルトで表示されるのがH4のセルで数式が長く表示されるため、B7を
 *   デフォルトの位置にして、B7でウィンドウ枠も固定できないか。
 */
class AttendanceExcelExportMultiSheetTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    /** @var array<int, string> */
    private array $generatedPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['company_code' => '970077']);
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedPaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function makeUser(string $name, string $code): User
    {
        return User::factory()
            ->forCompany($this->company->id)
            ->create(['name' => $name, 'employee_code' => $code]);
    }

    /**
     * @param  array<int, User>  $users
     */
    private function generateFor(array $users): Spreadsheet
    {
        $path = app(AttendanceExcelExportService::class)->generateForUsers(
            $this->company->id,
            collect($users),
            '2026-06-21',
            '2026-07-20',
        );
        $this->generatedPaths[] = $path;

        return IOFactory::load($path);
    }

    /**
     * @test
     */
    public function 複数のスタッフが1つのファイルにスタッフごとのシートで出力される(): void
    {
        $a = $this->makeUser('出力 一郎', '000101');
        $b = $this->makeUser('出力 二郎', '000102');
        $c = $this->makeUser('出力 三郎', '000103');

        $spreadsheet = $this->generateFor([$a, $b, $c]);

        $this->assertSame(['出力 一郎', '出力 二郎', '出力 三郎'], $spreadsheet->getSheetNames());
        $this->assertSame('000101', (string) $spreadsheet->getSheet(0)->getCell('A4')->getValue());
        $this->assertSame('出力 二郎', (string) $spreadsheet->getSheet(1)->getCell('B4')->getValue());
        $this->assertSame('000103', (string) $spreadsheet->getSheet(2)->getCell('A4')->getValue());
    }

    /**
     * @test
     */
    public function 各シートの明細は本人のデータだけになる(): void
    {
        $a = $this->makeUser('出力 一郎', '000101');
        $b = $this->makeUser('出力 二郎', '000102');
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $a->id,
            'work_date' => '2026-06-24',
            'work_start' => '2026-06-24 09:00:00',
            'work_end' => '2026-06-24 18:00:00',
            'net_work_minutes' => 480,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $spreadsheet = $this->generateFor([$a, $b]);

        // 6/24 は 6/21 開始の期間の4日目（10行目）
        $this->assertSame('09:00', (string) $spreadsheet->getSheet(0)->getCell('G10')->getValue());
        $this->assertSame('', (string) $spreadsheet->getSheet(1)->getCell('G10')->getValue());
    }

    /**
     * @test
     */
    public function 全シートで集計欄と合計行の数式が残っている(): void
    {
        $spreadsheet = $this->generateFor([
            $this->makeUser('出力 一郎', '000101'),
            $this->makeUser('出力 二郎', '000102'),
        ]);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $this->assertStringStartsWith('=', (string) $sheet->getCell('H4')->getValue(), $sheet->getTitle());
            $this->assertStringStartsWith('=', (string) $sheet->getCell('M38')->getValue(), $sheet->getTitle());
        }
    }

    /**
     * @test
     */
    public function 全シートで_b7が選択され_ウィンドウ枠が_b7で固定される(): void
    {
        $spreadsheet = $this->generateFor([
            $this->makeUser('出力 一郎', '000101'),
            $this->makeUser('出力 二郎', '000102'),
        ]);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $this->assertSame('B7', $sheet->getActiveCell(), $sheet->getTitle().' の選択セル');
            $this->assertSame('B7', $sheet->getFreezePane(), $sheet->getTitle().' のウィンドウ枠');
        }
        $this->assertSame(0, $spreadsheet->getActiveSheetIndex());
    }

    /**
     * @test
     */
    public function 一人分の出力でも_b7が選択されウィンドウ枠が固定される(): void
    {
        $user = $this->makeUser('出力 一郎', '000101');

        $path = app(AttendanceExcelExportService::class)->generate(
            $this->company->id,
            $user,
            '2026-06-21',
            '2026-07-20',
        );
        $this->generatedPaths[] = $path;
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('B7', $sheet->getActiveCell());
        $this->assertSame('B7', $sheet->getFreezePane());
    }

    /**
     * @test
     *
     * シート名は31文字まで・一部の記号は使えず・重複できない。
     */
    public function シート名は_excelの制約に合わせ_同名のスタッフでも重複しない(): void
    {
        $spreadsheet = $this->generateFor([
            $this->makeUser('同姓同名', '000201'),
            $this->makeUser('同姓同名', '000202'),
            $this->makeUser('記号/入り:名前[1]', '000203'),
            $this->makeUser(str_repeat('長', 40), '000204'),
        ]);

        $names = $spreadsheet->getSheetNames();

        $this->assertCount(4, array_unique($names));
        $this->assertSame('同姓同名', $names[0]);
        $this->assertSame('同姓同名_000202', $names[1]);
        $this->assertSame('記号入り名前1', $names[2]);
        $this->assertSame(31, mb_strlen($names[3]));
    }
}

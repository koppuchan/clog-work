<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 勤務実績Excelのテンプレート検証。
 *
 * 集計欄はテンプレート側の数式で算出される。アプリが値を書き込むと
 * 数式が失われ、シート側で組まれた集計が動かなくなるため、
 * 数式が残っていることと列の並びを固定する。
 */
class AttendanceExcelTemplateTest extends TestCase
{
    private const TEMPLATE = 'app/public/templates/attendance_template.xlsx';

    private \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sheet = IOFactory::load(storage_path(self::TEMPLATE))->getActiveSheet();
    }

    /**
     * @test
     */
    public function 集計欄に数式が残っている(): void
    {
        // 有給日数から残業申請まで、シート側で組まれた集計
        foreach (['G4', 'H4', 'I4', 'J4', 'K4', 'L4', 'M4', 'N4', 'O4', 'P4', 'R4', 'S4', 'T4', 'U4'] as $cell) {
            $this->assertStringStartsWith(
                '=',
                (string) $this->sheet->getCell($cell)->getValue(),
                "{$cell} の数式が失われています",
            );
        }
    }

    /**
     * @test
     */
    public function 合計行に数式が残っている(): void
    {
        foreach (['M38', 'N38', 'O38', 'P38', 'Q38'] as $cell) {
            $this->assertStringStartsWith(
                '=',
                (string) $this->sheet->getCell($cell)->getValue(),
                "{$cell} の数式が失われています",
            );
        }
    }

    /**
     * @test
     */
    public function 見出しの列構成が想定どおり(): void
    {
        $expected = [
            'B6' => '勤務区分',
            'G6' => '出勤時刻',
            'H6' => '退勤時刻',
            'I6' => '休憩入①',
            'J6' => '休憩出①',
            'K6' => '休憩入②',
            'L6' => '休憩出②',
            'M6' => '労働時間',
            'N6' => '時間外',
            'O6' => '休日',
            'P6' => '深夜',
            'Q6' => '遅刻早退',
            'R6' => '備考/申請',
        ];

        foreach ($expected as $cell => $label) {
            $this->assertSame(
                $label,
                str_replace("\n", '', (string) $this->sheet->getCell($cell)->getValue()),
                "{$cell} の見出しが想定と異なります",
            );
        }
    }

    /**
     * @test
     */
    public function 集計の数式は備考のラベル列と数値列を参照している(): void
    {
        // R列でラベル位置を特定し、S列の同じ位置から数値を取り出す作り。
        // アプリが1セルにまとめて書くと集計が動かなくなる。
        $formula = (string) $this->sheet->getCell('H4')->getValue();

        $this->assertStringContainsString('$R$7:$R$37', $formula);
        $this->assertStringContainsString('$S$7:$S$37', $formula);
    }

    /**
     * @test
     */
    public function 休憩が2枠ある(): void
    {
        // 休憩2枠対応が失われていないことの確認
        $this->assertSame('休憩入①', (string) $this->sheet->getCell('I6')->getValue());
        $this->assertSame('休憩入②', (string) $this->sheet->getCell('K6')->getValue());
    }

    /**
     * @test
     */
    public function データ行は空である(): void
    {
        // テンプレートにサンプルデータが残っていると出力へ混入する
        foreach ([7, 20, 37] as $row) {
            foreach (['A', 'B', 'G', 'H', 'R'] as $col) {
                $this->assertSame(
                    '',
                    (string) $this->sheet->getCell($col.$row)->getValue(),
                    "{$col}{$row} にデータが残っています",
                );
            }
        }
    }
}

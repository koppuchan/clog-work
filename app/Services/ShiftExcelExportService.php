<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * シフト表をExcelファイルに書き出すサービス
 *
 * シフト管理画面と同じ見え方（部署ごとのまとまり、シフトパターンの色、日曜・祝日は赤、
 * 土曜は青、部署ごと・全体の出勤人数）で、1枚のシートに出力する。
 * そのまま印刷できるよう、A4横・横幅1ページに収め、見出し行は各ページの先頭に繰り返す。
 */
class ShiftExcelExportService
{
    private const TITLE_ROW = 1;

    private const HEADER_ROW = 3;

    private const FIRST_DATA_ROW = 5;

    private const NAME_COLUMN = 1;

    private const BORDER_COLOR = 'D1D5DB';

    /** 日付見出しの色（トーン → 背景・文字） */
    private const TONES = [
        'red' => ['background' => 'FEE2E2', 'text' => 'B91C1C'],
        'blue' => ['background' => 'DBEAFE', 'text' => '1D4ED8'],
        'normal' => ['background' => 'F3F4F6', 'text' => '111827'],
    ];

    /**
     * シフト表をExcelファイルとして生成する
     *
     * @param  array<string, mixed>  $table  ShiftTableService::build() の結果
     * @return string 一時ファイルのパス（ダウンロード後に削除すること）
     */
    public function generate(array $table): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('シフト表');

        $dayCount = count($table['days']);
        $lastDayColumn = self::NAME_COLUMN + $dayCount;
        $workColumn = $lastDayColumn + 1;
        $restColumn = $lastDayColumn + 2;

        $this->writeTitle($sheet, $table, $restColumn);
        $this->writeHeader($sheet, $table['days'], $workColumn, $restColumn);

        $row = self::FIRST_DATA_ROW;
        foreach ($table['groups'] as $group) {
            $row = $this->writeGroup($sheet, $table, $group, $row, $workColumn, $restColumn);
        }

        if ($table['groups'] === []) {
            $sheet->setCellValue([self::NAME_COLUMN, $row], '対象のスタッフがいません。');
            $row++;
        } elseif ($table['department_name'] === null) {
            $row = $this->writeTotals($sheet, $table, $row, $workColumn, $restColumn);
        }

        $this->writeLegend($sheet, $table['patterns'], $row + 1);
        $this->setUpSheet($sheet, $dayCount, $workColumn, $restColumn);

        return $this->saveToTempFile($spreadsheet);
    }

    /**
     * @param  array<string, mixed>  $table
     */
    private function writeTitle(Worksheet $sheet, array $table, int $lastColumn): void
    {
        $start = \Carbon\CarbonImmutable::parse($table['start_date']);
        $end = \Carbon\CarbonImmutable::parse($table['end_date']);
        $period = $start->format('Y年n月j日').'〜'.($start->year === $end->year ? $end->format('n月j日') : $end->format('Y年n月j日'));

        $title = 'シフト表　'.$period.'　'.($table['department_name'] ?? '全部署');
        $sheet->setCellValue([self::NAME_COLUMN, self::TITLE_ROW], $title);
        $sheet->getStyle([self::NAME_COLUMN, self::TITLE_ROW])->getFont()->setBold(true)->setSize(13);
        $sheet->getRowDimension(self::TITLE_ROW)->setRowHeight(24);
    }

    /**
     * 日付と曜日の見出し（2行）と、出勤・休日の見出しを書く
     *
     * @param  array<int, array<string, mixed>>  $days
     */
    private function writeHeader(Worksheet $sheet, array $days, int $workColumn, int $restColumn): void
    {
        $dayRow = self::HEADER_ROW;
        $weekdayRow = self::HEADER_ROW + 1;

        $sheet->setCellValue([self::NAME_COLUMN, $dayRow], 'スタッフ');
        $sheet->mergeCells([self::NAME_COLUMN, $dayRow, self::NAME_COLUMN, $weekdayRow]);
        $this->styleBox($sheet, [self::NAME_COLUMN, $dayRow, self::NAME_COLUMN, $weekdayRow], 'F3F4F6', '111827', true);

        foreach ($days as $index => $day) {
            $column = self::NAME_COLUMN + 1 + $index;
            $tone = self::TONES[$day['tone']];

            // 期間の最初の日と月の1日は、月も添えて月をまたぐことが分かるようにする
            $showMonth = $index === 0 || $day['day'] === 1;
            $sheet->setCellValueExplicit([$column, $dayRow], $showMonth ? $day['month'].'/'.$day['day'] : (string) $day['day'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([$column, $weekdayRow], $day['weekday']);
            $this->styleBox($sheet, [$column, $dayRow, $column, $weekdayRow], $tone['background'], $tone['text'], true);
        }

        foreach ([[$workColumn, '出勤', 'DCFCE7', '166534'], [$workColumn + 1, '休日', 'FFEDD5', '9A3412']] as [$column, $label, $background, $text]) {
            $sheet->setCellValue([$column, $dayRow], $label);
            $sheet->mergeCells([$column, $dayRow, $column, $weekdayRow]);
            $this->styleBox($sheet, [$column, $dayRow, $column, $weekdayRow], $background, $text, true);
        }
    }

    /**
     * 部署1つ分（部署名・スタッフ・部署の出勤人数）を書く
     *
     * @param  array<string, mixed>  $table
     * @param  array<string, mixed>  $group
     * @return int 次に書く行
     */
    private function writeGroup(Worksheet $sheet, array $table, array $group, int $row, int $workColumn, int $restColumn): int
    {
        // 部署名の行
        $sheet->setCellValue([self::NAME_COLUMN, $row], $group['name']);
        $sheet->mergeCells([self::NAME_COLUMN, $row, $restColumn, $row]);
        $this->styleBox($sheet, [self::NAME_COLUMN, $row, $restColumn, $row], 'E5E7EB', '374151', true, Alignment::HORIZONTAL_LEFT);
        $row++;

        foreach ($group['users'] as $user) {
            $sheet->setCellValue([self::NAME_COLUMN, $row], $user['name']);
            $this->styleBox($sheet, [self::NAME_COLUMN, $row, self::NAME_COLUMN, $row], 'EFF6FF', '111827', false, Alignment::HORIZONTAL_LEFT);

            foreach ($table['days'] as $index => $day) {
                $column = self::NAME_COLUMN + 1 + $index;
                $this->writeShiftCell($sheet, $column, $row, $user['cells'][$day['date']]);
            }

            $this->writeNumberCell($sheet, $workColumn, $row, $user['work_days'], '0"日"', 'DCFCE7', '166534');
            $this->writeNumberCell($sheet, $restColumn, $row, $user['rest_days'], '0"日"', 'FFEDD5', '9A3412');
            $sheet->getRowDimension($row)->setRowHeight(26);
            $row++;
        }

        // 部署の出勤人数
        $sheet->setCellValue([self::NAME_COLUMN, $row], $group['name'].' 合計');
        $this->styleBox($sheet, [self::NAME_COLUMN, $row, self::NAME_COLUMN, $row], 'DCFCE7', '166534', true, Alignment::HORIZONTAL_LEFT);
        foreach ($table['days'] as $index => $day) {
            $this->writeNumberCell($sheet, self::NAME_COLUMN + 1 + $index, $row, $group['counts'][$day['date']], 'General"人"', 'F0FDF4', '166534');
        }
        $this->styleBox($sheet, [$workColumn, $row, $restColumn, $row], 'FFFFFF', '111827', false);

        return $row + 1;
    }

    /**
     * 全部署の出勤人数と、出勤・休日の合計を書く
     *
     * @param  array<string, mixed>  $table
     * @return int 次に書く行
     */
    private function writeTotals(Worksheet $sheet, array $table, int $row, int $workColumn, int $restColumn): int
    {
        $sheet->setCellValue([self::NAME_COLUMN, $row], '合計');
        $this->styleBox($sheet, [self::NAME_COLUMN, $row, self::NAME_COLUMN, $row], 'BBF7D0', '166534', true);

        foreach ($table['days'] as $index => $day) {
            $this->writeNumberCell($sheet, self::NAME_COLUMN + 1 + $index, $row, $table['totals']['counts'][$day['date']], 'General"人"', 'DCFCE7', '166534', true);
        }
        $this->writeNumberCell($sheet, $workColumn, $row, $table['totals']['work_days'], '0"日"', 'BBF7D0', '166534', true);
        $this->writeNumberCell($sheet, $restColumn, $row, $table['totals']['rest_days'], '0"日"', 'FED7AA', '9A3412', true);

        return $row + 1;
    }

    /**
     * 凡例（シフトパターンの色と時間、休暇の略称）を書く
     *
     * @param  array<int, array<string, mixed>>  $patterns
     */
    private function writeLegend(Worksheet $sheet, array $patterns, int $row): void
    {
        $sheet->setCellValue([self::NAME_COLUMN, $row], '凡例');
        $sheet->getStyle([self::NAME_COLUMN, $row])->getFont()->setBold(true);
        $row++;

        foreach ($patterns as $pattern) {
            $sheet->setCellValue([self::NAME_COLUMN, $row], $pattern['name']);
            $this->styleBox($sheet, [self::NAME_COLUMN, $row, self::NAME_COLUMN, $row], ltrim($pattern['background'], '#'), ltrim($pattern['text'], '#'), false);

            $time = ($pattern['start_time'] !== null && $pattern['end_time'] !== null)
                ? $pattern['start_time'].'〜'.$pattern['end_time']
                : '';
            $sheet->setCellValue([self::NAME_COLUMN + 1, $row], $time);
            $sheet->mergeCells([self::NAME_COLUMN + 1, $row, self::NAME_COLUMN + 4, $row]);
            $row++;
        }

        $sheet->setCellValue([self::NAME_COLUMN, $row], '有：有給休暇　特：特別休暇　半：半日有給　時：時間有給（承認済みの休暇）');
        $sheet->getStyle([self::NAME_COLUMN, $row])->getFont()->getColor()->setRGB('4B5563');
    }

    /**
     * シフト1日分のセルを書く
     *
     * @param  array<string, mixed>  $cell
     */
    private function writeShiftCell(Worksheet $sheet, int $column, int $row, array $cell): void
    {
        $leave = $cell['leave'];

        if ($leave !== null && $leave['is_full_day']) {
            // 全日の休暇は、セル全体を休暇の表示にする
            $sheet->setCellValue([$column, $row], $leave['label']);
            $this->styleBox($sheet, [$column, $row, $column, $row], ltrim($leave['background'], '#'), ltrim($leave['text'], '#'), true);

            return;
        }

        // 半日有給・時間有給は、シフト名の下に休暇の略称を添える
        $sheet->setCellValue([$column, $row], $leave === null ? $cell['label'] : $cell['label']."\n".$leave['label']);
        $this->styleBox($sheet, [$column, $row, $column, $row], ltrim($cell['background'], '#'), ltrim($cell['text'], '#'), false);
    }

    private function writeNumberCell(Worksheet $sheet, int $column, int $row, float|int $value, string $format, string $background, string $text, bool $bold = false): void
    {
        $sheet->setCellValue([$column, $row], $value);
        $sheet->getStyle([$column, $row])->getNumberFormat()->setFormatCode($format);
        $this->styleBox($sheet, [$column, $row, $column, $row], $background, $text, $bold);
    }

    /**
     * 範囲に、背景色・文字色・罫線・中央寄せ（折り返し）をまとめて設定する
     *
     * @param  array{0: int, 1: int, 2: int, 3: int}  $range  開始列, 開始行, 終了列, 終了行
     */
    private function styleBox(Worksheet $sheet, array $range, string $background, string $text, bool $bold, string $horizontal = Alignment::HORIZONTAL_CENTER): void
    {
        $style = $sheet->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($background);
        $style->getFont()->setBold($bold)->setSize(9)->getColor()->setRGB($text);
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDER_COLOR);
        $style->getAlignment()->setHorizontal($horizontal)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    /**
     * 列幅・見出しの固定・印刷設定
     */
    private function setUpSheet(Worksheet $sheet, int $dayCount, int $workColumn, int $restColumn): void
    {
        $sheet->getColumnDimensionByColumn(self::NAME_COLUMN)->setWidth(18);
        for ($i = 0; $i < $dayCount; $i++) {
            $sheet->getColumnDimensionByColumn(self::NAME_COLUMN + 1 + $i)->setWidth(6.5);
        }
        $sheet->getColumnDimensionByColumn($workColumn)->setWidth(7);
        $sheet->getColumnDimensionByColumn($restColumn)->setWidth(7);

        // スクロールしても、スタッフ名と日付が見えるようにする
        $sheet->freezePane(Coordinate::stringFromColumnIndex(self::NAME_COLUMN + 1).self::FIRST_DATA_ROW);

        // そのまま印刷できるようにする（A4横・横幅は1ページ、見出し行は各ページに繰り返す）
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW + 1);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);
        $sheet->getPageSetup()->setHorizontalCentered(true);
        $sheet->getHeaderFooter()->setOddFooter('&C&P / &N');
    }

    private function saveToTempFile(Spreadsheet $spreadsheet): string
    {
        // 同時に複数の出力が走っても上書きし合わないよう、ファイル名は毎回変える
        $tempPath = storage_path('app/temp/shift_table_'.bin2hex(random_bytes(8)).'.xlsx');

        $tempDir = dirname($tempPath);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempPath);

        $spreadsheet->disconnectWorksheets();

        return $tempPath;
    }
}

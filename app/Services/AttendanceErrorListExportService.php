<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 勤務実績のエラーリストをExcelファイルに書き出すサービス
 *
 * 選んだリストごとにシートを分けて、1つのファイルにまとめる。
 */
class AttendanceErrorListExportService
{
    private const TITLE_ROW = 1;

    private const NOTE_ROW = 2;

    private const HEADER_ROW = 4;

    private const FIRST_DATA_ROW = 5;

    /**
     * リストごとのシート名・見出し・列
     *
     * @var array<string, array{sheet: string, title: string, note: string, columns: array<string, array{label: string, width: int}>}>
     */
    private const DEFINITIONS = [
        AttendanceErrorListService::LIST_NO_BREAK => [
            'sheet' => '休憩入力無しリスト',
            'title' => '休憩入力無しリスト',
            'note' => '出勤・退勤が入っていて、休憩が0分の日です。休憩はシフトに合わせて自動入力されるため、シフトの無い日に出勤した場合などは入りません。必要に応じて、勤務実績の画面から休憩を入力してください。',
            'columns' => [
                'employee_code' => ['label' => '従業員コード', 'width' => 14],
                'name' => ['label' => '氏名', 'width' => 18],
                'date' => ['label' => '日付', 'width' => 13],
                'weekday' => ['label' => '曜日', 'width' => 6],
                'shift' => ['label' => 'シフト', 'width' => 20],
                'work_start' => ['label' => '出勤', 'width' => 9],
                'work_end' => ['label' => '退勤', 'width' => 9],
                'work_minutes' => ['label' => '労働時間', 'width' => 11],
                'source' => ['label' => '登録方法', 'width' => 12],
            ],
        ],
        AttendanceErrorListService::LIST_MISSING_STAMP => [
            'sheet' => '打刻漏れリスト',
            'title' => '打刻漏れリスト',
            'note' => '退勤・休憩終了の打刻がない日と、シフトがあるのに出勤の打刻も休暇などの勤務実績もない日です。当日分は含みません。勤務実績の画面から、打刻の追加・修正をしてください。',
            'columns' => [
                'employee_code' => ['label' => '従業員コード', 'width' => 14],
                'name' => ['label' => '氏名', 'width' => 18],
                'date' => ['label' => '日付', 'width' => 13],
                'weekday' => ['label' => '曜日', 'width' => 6],
                'issue' => ['label' => '内容', 'width' => 34],
                'stamps' => ['label' => 'その日の打刻', 'width' => 60],
            ],
        ],
    ];

    /**
     * エラーリストをExcelファイルとして生成する
     *
     * @param  array<string, array<int, array<string, mixed>>>  $lists  AttendanceErrorListService::build() の結果
     * @return string 一時ファイルのパス（ダウンロード後に削除すること）
     */
    public function generate(array $lists, string $startDate, string $endDate): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $period = $startDate.' 〜 '.$endDate;

        foreach (self::DEFINITIONS as $key => $definition) {
            if (! array_key_exists($key, $lists)) {
                continue;
            }

            $sheet = $spreadsheet->createSheet();
            $this->fillSheet($sheet, $definition, $lists[$key], $period);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $this->saveToTempFile($spreadsheet);
    }

    /**
     * @param  array{sheet: string, title: string, note: string, columns: array<string, array{label: string, width: int}>}  $definition
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function fillSheet(Worksheet $sheet, array $definition, array $rows, string $period): void
    {
        $sheet->setTitle($definition['sheet']);
        $columnKeys = array_keys($definition['columns']);
        $lastColumn = chr(ord('A') + count($columnKeys) - 1);

        $sheet->setCellValue('A'.self::TITLE_ROW, sprintf('%s（%s）／%d件', $definition['title'], $period, count($rows)));
        $sheet->getStyle('A'.self::TITLE_ROW)->getFont()->setBold(true)->setSize(13);
        $sheet->setCellValue('A'.self::NOTE_ROW, $definition['note']);
        $sheet->mergeCells('A'.self::NOTE_ROW.':'.$lastColumn.self::NOTE_ROW);
        $sheet->getStyle('A'.self::NOTE_ROW)->getFont()->getColor()->setRGB('555555');
        $sheet->getStyle('A'.self::NOTE_ROW)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getRowDimension(self::NOTE_ROW)->setRowHeight(34);

        // 印刷しても列が途中で切れないよう、横向き・幅1ページに収める
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getSheetView()->setZoomScale(100);

        foreach ($columnKeys as $index => $columnKey) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column.self::HEADER_ROW, $definition['columns'][$columnKey]['label']);
            $sheet->getColumnDimension($column)->setWidth($definition['columns'][$columnKey]['width']);
        }

        $header = 'A'.self::HEADER_ROW.':'.$lastColumn.self::HEADER_ROW;
        $sheet->getStyle($header)->getFont()->setBold(true);
        $sheet->getStyle($header)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F0E8');
        $sheet->getStyle($header)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($rows === []) {
            $sheet->setCellValue('A'.self::FIRST_DATA_ROW, '該当はありません。');

            return;
        }

        $rowNumber = self::FIRST_DATA_ROW;
        foreach ($rows as $row) {
            foreach ($columnKeys as $index => $columnKey) {
                // 従業員コードなどが数字だけでも、先頭の0が消えないよう文字列として書く
                $sheet->setCellValueExplicit(
                    chr(ord('A') + $index).$rowNumber,
                    $this->formatValue($columnKey, $row),
                    DataType::TYPE_STRING
                );
            }
            $rowNumber++;
        }

        // 見出し行を固定し、並べ替え・絞り込みができるようにする
        $sheet->freezePane('A'.self::FIRST_DATA_ROW);
        $sheet->setAutoFilter('A'.self::HEADER_ROW.':'.$lastColumn.($rowNumber - 1));
    }

    /**
     * セルに書く文字列を作る
     *
     * @param  array<string, mixed>  $row
     */
    private function formatValue(string $columnKey, array $row): string
    {
        $value = $row[$columnKey] ?? '';

        return match ($columnKey) {
            'work_end' => ($row['is_cross_day'] ?? false) ? '翌'.$value : (string) $value,
            'work_minutes' => $this->formatMinutes((int) $value),
            default => (string) $value,
        };
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function saveToTempFile(Spreadsheet $spreadsheet): string
    {
        // 同時に複数の出力が走っても上書きし合わないよう、ファイル名は毎回変える
        $tempPath = storage_path('app/temp/error_list_'.bin2hex(random_bytes(8)).'.xlsx');

        $tempDir = dirname($tempPath);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempPath);

        $spreadsheet->disconnectWorksheets();

        return $tempPath;
    }
}

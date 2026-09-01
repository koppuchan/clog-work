<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecordSourceEnum;
use App\Models\User;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * 勤務実績CSVを取り込む
 *
 * 旧環境はSSHで接続できずDBダンプを取得できないため、
 * 管理画面から出力できる勤務実績CSVだけが移行の手がかりになる。
 *
 * 旧環境の出力には勤務区分の列がないため、列の並びではなく
 * ヘッダーの名前で対応付ける。
 */
class WorkSummaryCsvImportService
{
    /** 氏名と日付以外は欠けていても取り込める */
    private const REQUIRED_HEADERS = ['氏名', '日付'];

    /** ヘッダー名と保存先の対応 */
    private const MINUTE_COLUMNS = [
        '勤務時間' => 'work_minutes',
        '休憩' => 'break_minutes',
        '実働時間' => 'net_work_minutes',
        '時間外' => 'overtime_minutes',
        '休日' => 'holiday_minutes',
        '深夜' => 'night_minutes',
        '遅刻' => 'late_minutes',
        '早退' => 'early_leave_minutes',
    ];

    public function __construct(
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * CSVの内容を取り込む
     *
     * @param  string  $csv  CSVの中身
     * @param  int  $companyId  取り込み先の会社ID
     * @param  bool  $dryRun  trueなら保存せず結果だけを返す
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function import(string $csv, int $companyId, bool $dryRun = false): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        $rows = $this->parse($csv);

        if ($rows === []) {
            $result['errors'][] = 'CSVが空です。';

            return $result;
        }

        $headers = array_shift($rows);

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                $result['errors'][] = "必要な列がありません: {$required}";

                return $result;
            }
        }

        $usersByName = $this->usersByName($companyId);

        foreach ($rows as $index => $row) {
            // 見出しの次の行を1行目として数える
            $lineNumber = $index + 2;

            if (array_filter($row, fn ($value) => $value !== '') === []) {
                continue;
            }

            $values = $this->combine($headers, $row);
            $name = trim($values['氏名'] ?? '');
            $date = $this->parseDate($values['日付'] ?? '');

            if ($name === '' || $date === null) {
                $result['skipped']++;

                continue;
            }

            $candidates = $usersByName[$name] ?? [];

            if ($candidates === []) {
                $result['errors'][] = "{$lineNumber}行目: 「{$name}」に一致するスタッフがいません。";
                $result['skipped']++;

                continue;
            }

            // 同姓同名は取り違えると別人の実績になるため取り込まない
            if (count($candidates) > 1) {
                $result['errors'][] = "{$lineNumber}行目: 「{$name}」が複数登録されているため特定できません。";
                $result['skipped']++;

                continue;
            }

            $data = $this->buildSummaryData($values, $date);

            // 勤務も休憩も記録がない日は、出力に含まれるだけの空行なので取り込まない
            if ($this->isEmptyRow($data)) {
                $result['skipped']++;

                continue;
            }

            if (! $dryRun) {
                $this->dailyWorkSummaryRepository->upsert(
                    $companyId,
                    $candidates[0],
                    $date->format('Y-m-d'),
                    $data,
                );
            }

            $result['imported']++;
        }

        return $result;
    }

    /**
     * 会社のスタッフを氏名で引けるようにする
     *
     * 同姓同名を検出するため、氏名ごとにIDの配列で持つ。
     *
     * @return array<string, array<int, int>>
     */
    private function usersByName(int $companyId): array
    {
        $map = [];

        foreach ($this->userRepository->findByCompanyId($companyId) as $user) {
            /** @var User $user */
            $map[trim($user->name)][] = $user->id;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function combine(array $headers, array $row): array
    {
        $values = [];

        foreach ($headers as $position => $header) {
            $values[$header] = $row[$position] ?? '';
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function buildSummaryData(array $values, CarbonImmutable $date): array
    {
        $data = [
            'work_start' => $this->parseDateTime($date, $values['出勤時刻'] ?? ''),
            'work_end' => $this->parseDateTime($date, $values['退勤時刻'] ?? ''),
            'note' => ($values['備考'] ?? '') !== '' ? $values['備考'] : null,
            'record_source' => RecordSourceEnum::MANUAL->value,
        ];

        foreach (self::MINUTE_COLUMNS as $header => $column) {
            $data[$column] = $this->parseMinutes($values[$header] ?? '');
        }

        // 退勤が出勤より前なら日を跨いだ勤務として扱う
        if ($data['work_start'] !== null && $data['work_end'] !== null
            && $data['work_end'] < $data['work_start']) {
            $data['work_end'] = CarbonImmutable::parse($data['work_end'])->addDay()->format('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        if ($data['work_start'] !== null || $data['work_end'] !== null) {
            return false;
        }

        foreach (self::MINUTE_COLUMNS as $column) {
            if (($data[$column] ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * 「H:MM」を分に直す
     */
    private function parseMinutes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || ! str_contains($value, ':')) {
            return 0;
        }

        [$hours, $minutes] = array_pad(explode(':', $value, 2), 2, '0');

        return max(0, ((int) $hours * 60) + (int) $minutes);
    }

    /**
     * 「2026/06/24」形式の日付を読む
     */
    private function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 「H:MM」の時刻を、その日の日時に直す
     */
    private function parseDateTime(CarbonImmutable $date, string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || ! str_contains($value, ':')) {
            return null;
        }

        try {
            return $date->setTimeFromTimeString($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parse(string $csv): array
    {
        // Excelで開いた際に付くBOMを取り除く
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;

        $rows = [];

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }

            $rows[] = array_map(fn ($value) => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }
}

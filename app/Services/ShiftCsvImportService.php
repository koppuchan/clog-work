<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * シフトCSVを取り込む
 *
 * 旧環境の管理画面から出力したシフトCSVを新環境へ移す。
 * 列は 個人コード, 氏名, 日付, 曜日, シフト名, 始業, 終業, 休憩時間, 休憩開始, 休憩終了, 備考。
 *
 * シフト名は新環境のシフトパターン名と突き合わせる。名前が一致しない場合は
 * 取り違えを避けるため取り込まず、理由を返す。
 */
class ShiftCsvImportService
{
    private const REQUIRED_HEADERS = ['日付', 'シフト名'];

    /** 出勤しない日を表すシフト名 */
    private const REST_NAMES = ['休み', '休日', ''];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * CSVの内容を取り込む
     *
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

        $usersByCode = $this->usersByEmployeeCode($companyId);
        $patternsByName = $this->patternsByName($companyId);
        $missingPatterns = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;

            if (array_filter($row, fn ($value) => $value !== '') === []) {
                continue;
            }

            $values = $this->combine($headers, $row);
            $code = trim($values['個人コード'] ?? '');
            $date = $this->parseDate($values['日付'] ?? '');
            $patternName = trim($values['シフト名'] ?? '');

            if ($code === '' || $date === null) {
                $result['skipped']++;

                continue;
            }

            // 休みの行はシフトを作らない
            if (in_array($patternName, self::REST_NAMES, true)) {
                $result['skipped']++;

                continue;
            }

            $userId = $usersByCode[$code] ?? null;

            if ($userId === null) {
                $result['errors'][] = "{$lineNumber}行目: 個人コード「{$code}」のスタッフがいません。";
                $result['skipped']++;

                continue;
            }

            $patternId = $patternsByName[$patternName] ?? null;

            if ($patternId === null) {
                // 同じ指摘を人数分繰り返さないよう、パターン名ごとに1回だけ出す
                if (! isset($missingPatterns[$patternName])) {
                    $result['errors'][] = "シフトパターン「{$patternName}」が新環境にありません。先に登録してください。";
                    $missingPatterns[$patternName] = true;
                }

                $result['skipped']++;

                continue;
            }

            if (! $dryRun) {
                Shift::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'user_id' => $userId,
                        'shift_date' => $date->format('Y-m-d'),
                    ],
                    [
                        'shift_pattern_id' => $patternId,
                        'note' => ($values['備考'] ?? '') !== '' ? $values['備考'] : null,
                    ],
                );
            }

            $result['imported']++;
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function usersByEmployeeCode(int $companyId): array
    {
        $map = [];

        foreach ($this->userRepository->findByCompanyId($companyId) as $user) {
            /** @var User $user */
            if ($user->employee_code !== null) {
                $map[trim((string) $user->employee_code)] = $user->id;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function patternsByName(int $companyId): array
    {
        return ShiftPattern::query()
            ->where('company_id', $companyId)
            ->get()
            ->mapWithKeys(fn (ShiftPattern $p) => [trim($p->name) => $p->id])
            ->all();
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
     * @return array<int, array<int, string>>
     */
    private function parse(string $csv): array
    {
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

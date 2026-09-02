<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\TimeRecord;
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
    /** 日付は必須。人物は個人コードか氏名のどちらかで特定する */
    private const REQUIRED_HEADERS = ['日付'];

    /**
     * ヘッダー名と保存先の対応
     *
     * 旧環境の出力は列構成が異なるため、どちらの名前でも受け取れるようにする。
     * 「労働時間」は実働時間にあたる。
     */
    private const MINUTE_COLUMNS = [
        '勤務時間' => 'work_minutes',
        '休憩' => 'break_minutes',
        '実働時間' => 'net_work_minutes',
        '労働時間' => 'net_work_minutes',
        '時間外' => 'overtime_minutes',
        '休日' => 'holiday_minutes',
        '深夜' => 'night_minutes',
        '遅刻' => 'late_minutes',
        '早退' => 'early_leave_minutes',
        '遅刻早退' => 'late_minutes',
    ];

    /**
     * 休憩の時刻列（旧環境の出力に含まれる）
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const BREAK_COLUMNS = [
        ['休憩入①', '休憩出①'],
        ['休憩入②', '休憩出②'],
        ['休憩入1', '休憩出1'],
        ['休憩入2', '休憩出2'],
    ];

    /** 勤務区分と休暇種別の対応 */
    private const LEAVE_TYPES = [
        '有給休暇' => 1,
        '特別休暇' => 2,
        '欠勤' => 3,
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
        $usersByCode = $this->usersByEmployeeCode($companyId);

        foreach ($rows as $index => $row) {
            // 見出しの次の行を1行目として数える
            $lineNumber = $index + 2;

            if (array_filter($row, fn ($value) => $value !== '') === []) {
                continue;
            }

            $values = $this->combine($headers, $row);
            $name = trim($values['氏名'] ?? '');
            $code = trim($values['個人コード'] ?? '');
            $date = $this->parseDate($values['日付'] ?? '');

            if ($date === null || ($name === '' && $code === '')) {
                $result['skipped']++;

                continue;
            }

            // 個人コードがあれば確実に特定できる。無ければ氏名で照合する
            if ($code !== '') {
                $userId = $usersByCode[$code] ?? null;

                if ($userId === null) {
                    $result['errors'][] = "{$lineNumber}行目: 個人コード「{$code}」のスタッフがいません。";
                    $result['skipped']++;

                    continue;
                }

                $candidates = [$userId];
            } else {
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

                // 画面の休憩表示や打刻修正の履歴は打刻レコードを見るため、
                // 出力に含まれる時刻から復元する
                $this->restoreTimeRecords($companyId, $candidates[0], $date, $values);
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
     * 会社のスタッフを個人コードで引けるようにする
     *
     * 個人コードは会社内で一意のため、氏名より確実に特定できる。
     *
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

        // 勤務区分から休暇種別を引き継ぐ。休出・出勤・休日は休暇ではない
        $workType = trim($values['勤務区分'] ?? '');
        $data['leave_type'] = self::LEAVE_TYPES[$workType] ?? null;

        // シフトの予定時刻も引き継ぐ（欠勤の判定などに使う）
        $data['scheduled_start_time'] = $this->parseTime($values['シフト開始'] ?? '');
        $data['scheduled_end_time'] = $this->parseTime($values['シフト終了'] ?? '');

        // 列名は旧新で異なるため、CSVに存在する列だけを反映する
        foreach (self::MINUTE_COLUMNS as $header => $column) {
            if (! array_key_exists($header, $values)) {
                continue;
            }

            $data[$column] = $this->parseMinutes($values[$header]);
        }

        // 退勤が出勤より前なら日を跨いだ勤務として扱う
        if ($data['work_start'] !== null && $data['work_end'] !== null
            && $data['work_end'] < $data['work_start']) {
            $data['work_end'] = CarbonImmutable::parse($data['work_end'])->addDay()->format('Y-m-d H:i:s');
        }

        // 休憩は時刻の組で出力されるため、そこから分数を求める
        $breaks = $this->breakPeriods($values, $date);

        if ($breaks !== []) {
            $data['break_minutes'] = array_sum(array_map(
                fn (array $b) => (int) $b['start']->diffInMinutes($b['end']),
                $breaks,
            ));
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

        // 打刻がなくても休暇の記録は取り込む
        if (($data['leave_type'] ?? null) !== null) {
            return false;
        }

        foreach (array_unique(array_values(self::MINUTE_COLUMNS)) as $column) {
            if (($data[$column] ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * 出力に含まれる時刻から打刻レコードを復元する
     *
     * 同じ日を再取り込みしても重ならないよう、いったん消してから作り直す。
     * 打刻としての事実は出力の時刻がすべてなので、丸め時刻も同じ値を入れる。
     *
     * @param  array<string, string>  $values
     */
    private function restoreTimeRecords(int $companyId, int $userId, CarbonImmutable $date, array $values): void
    {
        $punches = [];

        $workStart = $this->parseDateTime($date, $values['出勤時刻'] ?? '');
        if ($workStart !== null) {
            $punches[] = [TimeRecordTypeEnum::WORK_START, CarbonImmutable::parse($workStart)];
        }

        $workEnd = $this->parseDateTime($date, $values['退勤時刻'] ?? '');
        if ($workEnd !== null) {
            $endAt = CarbonImmutable::parse($workEnd);
            $isNextDay = $workStart !== null && $endAt->lessThan(CarbonImmutable::parse($workStart));

            $punches[] = [
                $isNextDay ? TimeRecordTypeEnum::WORK_END_NEXT_DAY : TimeRecordTypeEnum::WORK_END,
                $isNextDay ? $endAt->addDay() : $endAt,
            ];
        }

        foreach ($this->breakPeriods($values, $date) as $period) {
            $punches[] = [TimeRecordTypeEnum::BREAK_START, $period['start']];
            $punches[] = [TimeRecordTypeEnum::BREAK_END, $period['end']];
        }

        if ($punches === []) {
            return;
        }

        TimeRecord::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereBetween('record_time', [
                $date->startOfDay()->format('Y-m-d H:i:s'),
                $date->addDay()->endOfDay()->format('Y-m-d H:i:s'),
            ])
            ->delete();

        foreach ($punches as [$type, $at]) {
            TimeRecord::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'record_type' => $type,
                'record_time' => $at->format('Y-m-d H:i:s'),
                'rounded_time' => $at->format('Y-m-d H:i:s'),
                'record_source' => RecordSourceEnum::MANUAL,
            ]);
        }
    }

    /**
     * 休憩の時刻の組を取り出す
     *
     * 退勤と同じく、終了が開始より前なら日を跨いだものとして扱う。
     *
     * @param  array<string, string>  $values
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function breakPeriods(array $values, CarbonImmutable $date): array
    {
        $periods = [];

        foreach (self::BREAK_COLUMNS as [$startHeader, $endHeader]) {
            $start = $this->parseDateTime($date, $values[$startHeader] ?? '');
            $end = $this->parseDateTime($date, $values[$endHeader] ?? '');

            if ($start === null || $end === null) {
                continue;
            }

            $startAt = CarbonImmutable::parse($start);
            $endAt = CarbonImmutable::parse($end);

            if ($endAt->lessThan($startAt)) {
                $endAt = $endAt->addDay();
            }

            $periods[] = ['start' => $startAt, 'end' => $endAt];
        }

        return $periods;
    }

    /**
     * 「H:MM」形式の時刻をそのまま取り出す
     */
    private function parseTime(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' && str_contains($value, ':') ? $value : null;
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

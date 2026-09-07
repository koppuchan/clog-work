<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * 勤務実績の要対応状態を検出するサービス
 *
 * 打刻の抜けや集計漏れは、放置すると給与計算の直前に見つかって
 * 手戻りになる。一覧上で気づけるよう、日付ごとの状態を返す。
 */
class AttendanceIssueService
{
    /**
     * 退勤打刻がない
     */
    public const MISSING_CLOCK_OUT = 'missing_clock_out';

    /**
     * 打刻はあるが労働時間が集計されていない
     */
    public const NOT_CALCULATED = 'not_calculated';

    /**
     * 休憩終了打刻がない
     */
    public const MISSING_BREAK_END = 'missing_break_end';

    /**
     * 日付ごとの要対応状態を返す
     *
     * 当日は勤務の途中である可能性が高いため対象外とする。
     *
     * @param  Collection<int, array<string, mixed>>|Collection<int, object>  $summaries  勤務実績
     * @param  string|null  $today  当日（Y-m-d）。省略時は現在日
     * @return array<string, array<int, string>> 日付をキーとした状態の一覧
     */
    public function detect(Collection $summaries, ?string $today = null): array
    {
        $today ??= CarbonImmutable::now()->format('Y-m-d');
        $issues = [];

        foreach ($summaries as $summary) {
            $date = $this->value($summary, 'work_date');

            if (! is_string($date) || $date === '') {
                continue;
            }

            // 日付に時刻が含まれている場合に備えて先頭10文字で揃える
            $date = substr($date, 0, 10);

            if ($date >= $today) {
                continue;
            }

            $workStart = $this->value($summary, 'work_start');
            $workEnd = $this->value($summary, 'work_end');
            $netMinutes = $this->value($summary, 'net_work_minutes');

            $found = [];

            if ($this->filled($workStart) && ! $this->filled($workEnd)) {
                $found[] = self::MISSING_CLOCK_OUT;
            }

            // 出退勤が揃っているのに労働時間が算出されていない
            if ($this->filled($workStart) && $this->filled($workEnd) && ! $this->filled($netMinutes)) {
                $found[] = self::NOT_CALCULATED;
            }

            if ($found !== []) {
                $issues[$date] = $found;
            }
        }

        return $issues;
    }

    /**
     * 勤務実績と打刻データから、日付ごとの要対応状態をまとめて検出する
     *
     * @param  Collection<int, array<string, mixed>>|Collection<int, object>  $summaries  勤務実績
     * @param  Collection<int, \App\Models\TimeRecord>  $timeRecords  打刻データ
     * @param  string|null  $today  当日（Y-m-d）。省略時は現在日
     * @return array<string, array<int, string>> 日付をキーとした状態の一覧
     */
    public function detectAll(Collection $summaries, Collection $timeRecords, ?string $today = null): array
    {
        $issues = $this->detect($summaries, $today);

        foreach ($this->detectMissingClockOut($timeRecords, $today) as $date => $codes) {
            $issues[$date] = array_values(array_unique([...($issues[$date] ?? []), ...$codes]));
        }

        foreach ($this->detectMissingBreakEnd($timeRecords, $today) as $date => $codes) {
            $issues[$date] = array_values(array_unique([...($issues[$date] ?? []), ...$codes]));
        }

        return $issues;
    }

    /**
     * 打刻データから、退勤打刻がない日を検出する
     *
     * detect()は勤務実績（バッチ集計済みのDailyWorkSummary）を見て判定するため、
     * 集計がまだ走っていない日（打刻直後〜翌日0時のバッチ実行まで）は
     * 判定対象に含められない。ここでは打刻データから直接判定することで、
     * 集計を待たずに退勤忘れを検出する。
     *
     * 出勤・退勤打刻を時系列順に走査し、「開いたまま（対応する退勤がない）
     * 出勤」を検出する。単純に「この出勤より後に退勤打刻があるか」だけで
     * 判定すると、後日の別の出勤に対応する退勤を誤って「この出勤の退勤」
     * とみなしてしまい、実際には退勤忘れの日を見逃す（例:
     * 9/5 10:44出勤・退勤なし → 9/6 09:00出勤 → 9/6 18:00退勤、という
     * データでは9/5が退勤忘れなのに、9/6の退勤で満たされたと誤判定する）。
     * そのため、出勤の後に次の出勤が来たら、前の出勤は退勤忘れとして
     * 確定させる。
     *
     * @param  Collection<int, \App\Models\TimeRecord>  $timeRecords  打刻データ
     * @param  string|null  $today  当日（Y-m-d）。省略時は現在日
     * @return array<string, array<int, string>> 日付をキーとした状態の一覧
     */
    public function detectMissingClockOut(Collection $timeRecords, ?string $today = null): array
    {
        $today ??= CarbonImmutable::now()->format('Y-m-d');
        $issues = [];

        $workRecords = $timeRecords
            ->filter(fn ($record) => $record->record_type->isWorkStart() || $record->record_type->isWorkEnd())
            ->sortBy(fn ($record) => $record->record_time->getTimestamp())
            ->values();

        $openWorkStart = null;

        foreach ($workRecords as $record) {
            if ($record->record_type->isWorkStart()) {
                if ($openWorkStart !== null) {
                    $this->flagMissingClockOutIfPast($issues, $openWorkStart, $today);
                }
                $openWorkStart = $record;

                continue;
            }

            // 退勤打刻。直前に開いていた出勤に対応するとみなして閉じる
            $openWorkStart = null;
        }

        if ($openWorkStart !== null) {
            $this->flagMissingClockOutIfPast($issues, $openWorkStart, $today);
        }

        return $issues;
    }

    /**
     * 出勤打刻の日付が過去であれば、退勤忘れとして記録する
     *
     * @param  array<string, array<int, string>>  $issues
     * @param  \App\Models\TimeRecord  $workStart
     */
    private function flagMissingClockOutIfPast(array &$issues, $workStart, string $today): void
    {
        $date = $workStart->record_time->format('Y-m-d');

        if ($date >= $today) {
            return;
        }

        $issues[$date] = [self::MISSING_CLOCK_OUT];
    }

    /**
     * 日付ごとに休憩打刻漏れ（休憩開始のみで終了がない）を検出する
     *
     * 当日は休憩中である可能性が高いため対象外とする。
     *
     * @param  Collection<int, \App\Models\TimeRecord>  $timeRecords  打刻データ
     * @param  string|null  $today  当日（Y-m-d）。省略時は現在日
     * @return array<string, array<int, string>> 日付をキーとした状態の一覧
     */
    public function detectMissingBreakEnd(Collection $timeRecords, ?string $today = null): array
    {
        $today ??= CarbonImmutable::now()->format('Y-m-d');
        $issues = [];

        $byDate = $timeRecords
            ->filter(fn ($record) => $record->record_type->isBreak())
            ->groupBy(fn ($record) => $record->record_time->format('Y-m-d'));

        foreach ($byDate as $date => $records) {
            if ($date >= $today) {
                continue;
            }

            $starts = $records->filter(fn ($record) => $record->record_type->isBreakStart())->count();
            $ends = $records->filter(fn ($record) => $record->record_type->isBreakEnd())->count();

            if ($starts > $ends) {
                $issues[$date] = [self::MISSING_BREAK_END];
            }
        }

        return $issues;
    }

    /**
     * 配列・オブジェクトのどちらでも値を取り出す
     */
    private function value(mixed $summary, string $key): mixed
    {
        if (is_array($summary)) {
            return $summary[$key] ?? null;
        }

        return $summary->{$key} ?? null;
    }

    /**
     * 値が入っているか（0 は入力済みとして扱う）
     */
    private function filled(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}

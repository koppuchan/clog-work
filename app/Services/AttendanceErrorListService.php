<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyWorkSummary;
use App\Models\Shift;
use App\Models\User;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * 勤務実績の出力に付けるエラーリストを作成するサービス
 *
 * シフトに合わせて休憩を自動入力する設定のため、シフトの無い日に出勤した場合などは
 * 休憩が入らず、管理者が手で入力・修正する必要がある。その対象を探しやすいよう、
 * 次の2つのリストを返す。
 * - 休憩入力無しリスト: 出勤・退勤があるのに休憩が0分の日（労働時間の下限を条件に指定できる）
 * - 打刻漏れリスト: 出勤・退勤・休憩のどちらか片方の打刻しかない日
 *
 * 出勤も退勤も打刻がない日は、打刻漏れではなく欠勤とみなすため載せない
 * （クライアント回答: 両方無いのは欠勤とみなしてよい）。
 */
class AttendanceErrorListService
{
    public const LIST_NO_BREAK = 'no_break';

    public const LIST_MISSING_STAMP = 'missing_stamp';

    /**
     * 要対応の種別ごとの表示名
     */
    private const ISSUE_LABELS = [
        AttendanceIssueService::MISSING_CLOCK_IN => '出勤の打刻なし',
        AttendanceIssueService::MISSING_CLOCK_OUT => '退勤の打刻なし',
        AttendanceIssueService::MISSING_BREAK_START => '休憩開始の打刻なし',
        AttendanceIssueService::MISSING_BREAK_END => '休憩終了の打刻なし',
    ];

    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    public function __construct(
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly TimeRecordRepositoryInterface $timeRecordRepository,
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly AttendanceIssueService $attendanceIssueService,
    ) {}

    /**
     * 指定した従業員・期間のエラーリストを作成する
     *
     * @param  EloquentCollection<int, User>  $users  対象の従業員（この順に並べる）
     * @param  array<int, string>  $lists  作成するリスト（LIST_NO_BREAK / LIST_MISSING_STAMP）
     * @param  CarbonImmutable|null  $now  現在日時。省略時は現在日時
     * @param  int|null  $minWorkMinutes  休憩入力無しリストに載せる労働時間の下限（分、以上）。省略時は全ての日
     * @return array<string, array<int, array<string, mixed>>> リスト名をキーとした行の一覧
     */
    public function build(
        int $companyId,
        EloquentCollection $users,
        string $startDate,
        string $endDate,
        array $lists,
        ?CarbonImmutable $now = null,
        ?int $minWorkMinutes = null,
    ): array {
        $now ??= CarbonImmutable::now();
        $result = array_fill_keys($lists, []);
        $userIds = $users->pluck('id')->all();

        if ($userIds === [] || $lists === []) {
            return $result;
        }

        $summariesByUser = $this->dailyWorkSummaryRepository
            ->findByUserIdsAndDateRange($companyId, $userIds, $startDate, $endDate)
            ->groupBy('user_id');
        $shiftsByUser = $this->shiftRepository
            ->findByCompanyIdAndDateRange($companyId, $startDate, $endDate)
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id');

        $wantsNoBreak = in_array(self::LIST_NO_BREAK, $lists, true);
        $wantsMissingStamp = in_array(self::LIST_MISSING_STAMP, $lists, true);

        // 日付をまたぐ夜勤の出勤・退勤・休憩を正しく対応させるため、打刻は期間の前日と翌日まで取得する。
        // 期間の最初の日に退勤だけが見えて「出勤なし」と誤検出したり、最後の日に始めた夜勤を
        // 「退勤なし」と誤検出したりしない（出力する日付は、後で期間内に絞る）
        $recordsByUser = $wantsMissingStamp
            ? $this->timeRecordRepository->findByUserIdsAndDateRange(
                $companyId,
                $userIds,
                CarbonImmutable::parse($startDate)->subDay()->format('Y-m-d'),
                CarbonImmutable::parse($endDate)->addDay()->format('Y-m-d'),
            )->groupBy('user_id')
            : collect();

        foreach ($users as $user) {
            $summaries = $summariesByUser->get($user->id, collect());

            if ($wantsNoBreak) {
                array_push($result[self::LIST_NO_BREAK], ...$this->noBreakRows(
                    $user,
                    $summaries,
                    $shiftsByUser->get($user->id, collect()),
                    $minWorkMinutes,
                ));
            }

            if ($wantsMissingStamp) {
                array_push($result[self::LIST_MISSING_STAMP], ...$this->missingStampRows(
                    $user,
                    $summaries,
                    $recordsByUser->get($user->id, collect()),
                    $startDate,
                    $endDate,
                    $now,
                ));
            }
        }

        return $result;
    }

    /**
     * 休憩入力無しリストの行を作る
     *
     * @param  Collection<int, DailyWorkSummary>  $summaries
     * @param  Collection<int, Shift>  $shifts
     * @return array<int, array<string, mixed>>
     */
    private function noBreakRows(User $user, Collection $summaries, Collection $shifts, ?int $minWorkMinutes): array
    {
        $shiftsByDate = $shifts->keyBy(fn (Shift $shift) => $shift->shift_date->format('Y-m-d'));
        $rows = [];

        foreach ($summaries->sortBy(fn (DailyWorkSummary $summary) => $summary->work_date->format('Y-m-d')) as $summary) {
            // 出勤・退勤が揃っている日のうち、休憩が入っていない日
            if ($summary->work_start === null || $summary->work_end === null || (int) $summary->break_minutes > 0) {
                continue;
            }

            $workMinutes = (int) ($summary->net_work_minutes ?? $summary->work_minutes ?? 0);
            if ($minWorkMinutes !== null && $workMinutes < $minWorkMinutes) {
                continue;
            }

            $date = $summary->work_date->format('Y-m-d');

            $rows[] = [
                'employee_code' => (string) ($user->employee_code ?? ''),
                'name' => (string) $user->name,
                'date' => $date,
                'weekday' => $this->weekday($date),
                'shift' => $shiftsByDate->get($date)?->shiftPattern?->name ?? 'シフトなし',
                'work_start' => $summary->work_start->format('H:i'),
                'work_end' => $summary->work_end->format('H:i'),
                'is_cross_day' => (bool) $summary->is_cross_day,
                'work_minutes' => $workMinutes,
                'source' => $summary->record_source->label(),
            ];
        }

        return $rows;
    }

    /**
     * 打刻漏れリストの行を作る
     *
     * @param  Collection<int, DailyWorkSummary>  $summaries
     * @param  Collection<int, \App\Models\TimeRecord>  $records
     * @return array<int, array<string, mixed>>
     */
    private function missingStampRows(
        User $user,
        Collection $summaries,
        Collection $records,
        string $startDate,
        string $endDate,
        CarbonImmutable $now,
    ): array {
        // 画面の要対応表示と同じ判定を使うため、勤務実績は画面と同じ配列の形に揃える
        $summaryRows = $summaries->map(fn (DailyWorkSummary $summary) => [
            'work_date' => $summary->work_date->format('Y-m-d'),
            'work_start' => $summary->work_start?->format('H:i'),
            'work_end' => $summary->work_end?->format('H:i'),
            'net_work_minutes' => $summary->net_work_minutes,
        ]);

        $today = $now->format('Y-m-d');

        /** @var array<string, array<int, string>> $issues */
        $issues = [];
        $detected = [
            $this->attendanceIssueService->detectMissingClockOut($records, $summaryRows, $now->format('Y-m-d H:i:s')),
            $this->attendanceIssueService->detectMissingClockIn($records),
            $this->attendanceIssueService->detectMissingBreakEnd($records, $today),
            $this->attendanceIssueService->detectMissingBreakStart($records),
        ];
        foreach ($detected as $found) {
            foreach ($found as $date => $codes) {
                $issues[$date] = array_values(array_unique([...($issues[$date] ?? []), ...$codes]));
            }
        }
        ksort($issues);

        $rows = [];
        foreach ($issues as $date => $codes) {
            // 前後1日ぶん多く取得しているため、出力する期間の外は除く。当日分は途中の可能性があるため載せない
            if ($date < $startDate || $date > $endDate || $date >= $today) {
                continue;
            }

            // 画面の表示順に合わせ、出勤→退勤→休憩の順に並べる
            usort($codes, fn (string $a, string $b) => array_search($a, array_keys(self::ISSUE_LABELS), true) <=> array_search($b, array_keys(self::ISSUE_LABELS), true));
            [$clockIn, $clockOut] = $this->clockTimes($date, $records);

            $rows[] = [
                'employee_code' => (string) ($user->employee_code ?? ''),
                'name' => (string) $user->name,
                'date' => $date,
                'weekday' => $this->weekday($date),
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'issue' => implode('、', array_map(fn (string $code) => self::ISSUE_LABELS[$code], $codes)),
                'stamps' => $this->describeStamps($records->filter(fn ($record) => $record->record_time->format('Y-m-d') === $date)),
            ];
        }

        return $rows;
    }

    /**
     * その日の勤務の出勤時刻と退勤時刻を返す（打刻がなければ空）
     *
     * 出勤・退勤の打刻を時系列に並べ、出勤から次の退勤までを1回の勤務として対応させる。
     * その日に出勤した勤務を優先し、なければその日に退勤した勤務（日付をまたぐ夜勤の
     * 退勤側の日や、出勤の打刻がない日）を使う。日付をまたぐ側の時刻には、
     * 前日の出勤は「前」、翌日の退勤は「翌」を付ける。
     *
     * @param  Collection<int, \App\Models\TimeRecord>  $records
     * @return array{0: string, 1: string}
     */
    private function clockTimes(string $date, Collection $records): array
    {
        $sessions = [];
        $open = null;
        $workRecords = $records
            ->filter(fn ($record) => $record->record_type->isWorkStart() || $record->record_type->isWorkEnd())
            ->sortBy(fn ($record) => $record->record_time->getTimestamp());

        foreach ($workRecords as $record) {
            if ($record->record_type->isWorkStart()) {
                if ($open !== null) {
                    $sessions[] = ['start' => $open, 'end' => null];
                }
                $open = $record;

                continue;
            }

            $sessions[] = ['start' => $open, 'end' => $record];
            $open = null;
        }
        if ($open !== null) {
            $sessions[] = ['start' => $open, 'end' => null];
        }

        $sessions = collect($sessions);
        $session = $sessions->first(fn (array $s) => $s['start']?->record_time->format('Y-m-d') === $date)
            ?? $sessions->first(fn (array $s) => $s['end']?->record_time->format('Y-m-d') === $date);

        if ($session === null) {
            return ['', ''];
        }

        $in = $session['start'];
        $out = $session['end'];

        return [
            $in === null ? '' : ($in->record_time->format('Y-m-d') < $date ? '前' : '').$in->record_time->format('H:i'),
            $out === null ? '' : ($out->record_time->format('Y-m-d') > $date ? '翌' : '').$out->record_time->format('H:i'),
        ];
    }

    /**
     * その日の打刻を「勤務開始 09:00、休憩開始 12:00」の形で並べる
     *
     * @param  Collection<int, \App\Models\TimeRecord>  $records
     */
    private function describeStamps(Collection $records): string
    {
        if ($records->isEmpty()) {
            return '打刻なし';
        }

        return $records
            ->sortBy(fn ($record) => $record->record_time->getTimestamp())
            ->map(fn ($record) => $record->record_type->label().' '.$record->record_time->format('H:i'))
            ->implode('、');
    }

    private function weekday(string $date): string
    {
        return self::WEEKDAYS[CarbonImmutable::parse($date)->dayOfWeek];
    }
}

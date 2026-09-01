<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Enums\TimeRecordTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use App\Services\AttendanceIssueService;
use App\Services\CompanySettingService;
use App\Services\DailyWorkSummaryService;
use App\Services\LaborAlertService;
use App\Services\RequestService;
use App\Services\ShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * スタッフ向け勤務実績コントローラー
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly DailyWorkSummaryService $dailyWorkSummaryService,
        private readonly RequestService $requestService,
        private readonly TimeRecordRepositoryInterface $timeRecordRepository,
        private readonly ShiftService $shiftService,
        private readonly CompanySettingService $companySettingService,
        private readonly LaborAlertService $laborAlertService,
        private readonly AttendanceIssueService $attendanceIssueService
    ) {}

    /**
     * 勤務実績画面を表示
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $user->company_id;
        $userId = $user->id;
        $now = CarbonImmutable::now();

        // シフト表示設定を取得
        $settings = $this->companySettingService->getSettings($companyId);
        $shiftDisplayPeriod = match ($settings['shiftDisplay']['periodId']) {
            2 => 'closing_day_based',
            default => 'monthly',
        };
        $payrollClosingDay = $settings['payrollClosingDay'] ?? 25;

        if ($shiftDisplayPeriod === 'closing_day_based' && $request->query('start_date')) {
            // 締め日翌日スタート: start_date/end_date パラメータで期間指定
            $startDate = CarbonImmutable::parse($request->query('start_date'));
            $endDate = CarbonImmutable::parse($request->query('end_date'));
            $targetMonth = $endDate->startOfMonth();
        } else {
            // クエリパラメータから対象月を取得（デフォルトは当月）
            $requestedMonth = $request->query('month', $now->format('Y-m'));
            $targetMonth = $this->resolveTargetMonth($requestedMonth, $now);

            if ($shiftDisplayPeriod === 'closing_day_based') {
                $defaultDates = $this->getClosingDayBasedDateRange($now, $payrollClosingDay);
                $startDate = CarbonImmutable::parse($defaultDates['start']);
                $endDate = CarbonImmutable::parse($defaultDates['end']);
                $targetMonth = $endDate->startOfMonth();
            } else {
                $startDate = $targetMonth->startOfMonth();
                $endDate = $targetMonth->endOfMonth();
            }
        }

        // 勤務実績（日次サマリー）を取得
        $workSummaries = $this->dailyWorkSummaryService->getByUserIdAndDateRange(
            $companyId,
            $userId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        // 月間サマリーを計算
        $monthlySummary = $this->dailyWorkSummaryService->calculateMonthlySummary(
            $companyId,
            $userId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        // ユーザーの申請を取得
        $requests = $this->requestService->getRequestsByUserIdAndDateRange(
            $companyId,
            $userId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        // 勤務実績データを整形
        $workSummariesData = $workSummaries->map(function ($summary) {
            return [
                'id' => $summary->id,
                'user_id' => $summary->user_id,
                'work_date' => $summary->work_date->format('Y-m-d'),
                'scheduled_start_time' => $summary->scheduled_start_time,
                'scheduled_end_time' => $summary->scheduled_end_time,
                'work_start' => $summary->work_start?->format('H:i'),
                'work_end' => $summary->work_end?->format('H:i'),
                'work_minutes' => $summary->work_minutes,
                'break_minutes' => $summary->break_minutes,
                'net_work_minutes' => $summary->net_work_minutes,
                'night_minutes' => $summary->night_minutes,
                'holiday_minutes' => $summary->holiday_minutes,
                'overtime_minutes' => $summary->overtime_minutes,
                'late_minutes' => $summary->late_minutes,
                'early_leave_minutes' => $summary->early_leave_minutes,
                // 承認済みの休暇がある日は遅刻早退として扱わないため画面へ渡す
                'leave_type' => $summary->leave_type?->value,
                'is_cross_day' => $summary->is_cross_day,
                'record_source' => [
                    'value' => $summary->record_source->value,
                    'label' => $summary->record_source->label(),
                ],
                'note' => $summary->note,
            ];
        });

        // 申請を日付でグループ化（1日に複数申請対応）
        $requestsData = $requests->groupBy(fn ($req) => $req->target_date->format('Y-m-d'))
            ->map(fn ($group) => $group->map(fn ($req) => [
                'id' => $req->id,
                'type' => [
                    'value' => $req->type,
                    'code' => $req->applicationType?->code ?? '',
                    'label' => $req->applicationType?->name ?? 'その他',
                ],
                'start_time' => $req->start_time ? substr($req->start_time, 0, 5) : null,
                'end_time' => $req->end_time ? substr($req->end_time, 0, 5) : null,
                'reason' => $req->reason,
                'status' => [
                    'value' => $req->status->value,
                    'label' => $req->status->label(),
                    'css_class' => $req->status->cssClass(),
                ],
                'created_at' => $req->created_at->format('Y-m-d H:i'),
            ])->values());

        // 本日の打刻レコードから出勤時刻を取得（表示期間内の場合のみ）
        $todayWorkStart = null;
        $todayStr = $now->format('Y-m-d');
        if ($todayStr >= $startDate->format('Y-m-d') && $todayStr <= $endDate->format('Y-m-d')) {
            $todayRecords = $this->timeRecordRepository->findByUserIdAndDate(
                $companyId,
                $userId,
                $now->format('Y-m-d')
            );
            $clockInRecord = $todayRecords->first(
                fn ($record) => $record->record_type === TimeRecordTypeEnum::WORK_START
            );
            $todayWorkStart = $clockInRecord?->record_time?->format('H:i');
        }

        // シフトデータを取得（パターン名のマップ）
        $shifts = $this->shiftService->getShiftsByUserIdAndDateRange(
            $companyId,
            $userId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
        $shiftsMap = $shifts->mapWithKeys(function ($shift) {
            $pattern = $shift->shiftPattern;
            if (! $pattern) {
                return [];
            }

            $startTime = $pattern->start_time ? substr((string) $pattern->start_time, 0, 5) : null;
            $endTime = $pattern->end_time ? substr((string) $pattern->end_time, 0, 5) : null;
            $breakStart = $pattern->break_start ? substr((string) $pattern->break_start, 0, 5) : null;
            $breakEnd = $pattern->break_end ? substr((string) $pattern->break_end, 0, 5) : null;

            return [
                $shift->shift_date->format('Y-m-d') => [
                    'label' => $startTime && $endTime
                        ? "{$startTime}~{$endTime}"
                        : $pattern->name,
                    'break_start' => $breakStart,
                    'break_end' => $breakEnd,
                    // break_mode=1（分数のみ指定）の場合に表示できるよう、
                    // 休憩分数とモードもフロントへ渡す
                    'break_minutes' => $pattern->break_minutes,
                    'break_mode' => $pattern->break_mode,
                ],
            ];
        })->filter();

        // 打刻データを取得（休憩詳細表示用）
        $timeRecords = $this->timeRecordRepository->findByUserIdAndDateTimeRange(
            $companyId,
            $userId,
            $startDate->startOfDay()->format('Y-m-d H:i:s'),
            $endDate->endOfDay()->format('Y-m-d H:i:s')
        );

        $timeRecordsData = $timeRecords->map(function ($record) {
            return [
                'id' => $record->id,
                'user_id' => $record->user_id,
                'record_date' => $record->record_time->format('Y-m-d'),
                'record_time' => $record->record_time->format('H:i'),
                'record_type' => [
                    'value' => $record->record_type->value,
                    'label' => $record->record_type->label(),
                    'is_work_start' => $record->record_type->isWorkStart(),
                    'is_work_end' => $record->record_type->isWorkEnd(),
                    'is_break' => $record->record_type->isBreak(),
                ],
                'record_source' => [
                    'value' => $record->record_source->value,
                    'label' => $record->record_source->label(),
                    'badge_color' => $record->record_source->badgeColor(),
                    'can_edit' => $record->record_source->canEdit(),
                ],
                'note' => $record->note,
                'rounded_time' => $record->rounded_time?->format('H:i'),
            ];
        });

        // 打刻修正履歴を取得
        $correctionsData = $this->dailyWorkSummaryService->getCorrectionsByUserIdAndDateRange(
            $userId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        // 会社の有給設定を取得
        $company = $user->primaryCompany()->first();

        return Inertia::render('Staff/Reports', [
            'workSummaries' => $workSummariesData,
            'attendanceIssues' => $this->attendanceIssueService->detect(collect($workSummariesData)),
            'monthlySummary' => $monthlySummary,
            'timeRecords' => $timeRecordsData,
            'timeRecordCorrections' => $correctionsData,
            'requests' => $requestsData,
            'shifts' => $shiftsMap,
            'todayWorkStart' => $todayWorkStart,
            'filters' => [
                'month' => $targetMonth->format('Y-m'),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'shiftDisplayPeriod' => $shiftDisplayPeriod,
                'payrollClosingDay' => $payrollClosingDay,
            ],
            'leaveSettings' => [
                'half_day' => $company?->paid_leave_half_day ?? false,
                'hourly' => $company?->paid_leave_hourly ?? false,
            ],
            // 本人に関する労務アラートのみを渡す
            'laborAlerts' => $this->laborAlertService
                ->getAlertsForUser($companyId, $user->id, $targetMonth->year, $targetMonth->month)
                ->toArray(),
        ]);
    }

    /**
     * 締め日翌日スタートの日付範囲を計算
     *
     * @param  CarbonImmutable  $now  現在日時
     * @param  int  $closingDay  給与締め日（1-31）
     * @return array{start: string, end: string}
     */
    private function getClosingDayBasedDateRange(CarbonImmutable $now, int $closingDay): array
    {
        $safeDay = fn (CarbonImmutable $date, int $day): int => min($day, $date->daysInMonth);

        if ($now->day <= $safeDay($now, $closingDay)) {
            // 締め日以前: 前月(締め日+1)〜今月締め日
            $endDate = $now->setDay($safeDay($now, $closingDay));
            $startMonth = $now->subMonth();
            $startDay = $closingDay + 1;

            if ($closingDay >= $startMonth->daysInMonth) {
                $startDate = $now->startOfMonth();
            } else {
                $startDate = $startMonth->setDay(min($startDay, $startMonth->daysInMonth));
            }
        } else {
            // 締め日以降: 今月(締め日+1)〜来月締め日
            $startDay = $closingDay + 1;

            if ($closingDay >= $now->daysInMonth) {
                $startDate = $now->addMonth()->startOfMonth();
            } else {
                $startDate = $now->setDay(min($startDay, $now->daysInMonth));
            }

            $endMonth = $now->addMonth();
            $endDate = $endMonth->setDay($safeDay($endMonth, $closingDay));
        }

        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * 対象月の解析とフォールバック処理
     */
    private function resolveTargetMonth(string $requestedMonth, CarbonImmutable $default): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m', $requestedMonth)->startOfMonth();
        } catch (\Throwable) {
            return $default->startOfMonth();
        }
    }
}

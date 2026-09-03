<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkSummaryRequest;
use App\Http\Requests\UpdateWorkSummaryRequest;
use App\Services\AttendanceExcelExportService;
use App\Services\AttendanceIssueService;
use App\Services\CompanySettingService;
use App\Services\DailyWorkSummaryService;
use App\Services\PermissionService;
use App\Services\RequestService;
use App\Services\ShiftService;
use App\Services\TimeRecordService;
use App\Services\UserService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 管理者向け勤務実績コントローラー
 */
class WorkSummaryController extends Controller
{
    public function __construct(
        private readonly DailyWorkSummaryService $dailyWorkSummaryService,
        private readonly TimeRecordService $timeRecordService,
        private readonly UserService $userService,
        private readonly RequestService $requestService,
        private readonly AttendanceExcelExportService $attendanceExcelExportService,
        private readonly PermissionService $permissionService,
        private readonly ShiftService $shiftService,
        private readonly CompanySettingService $companySettingService,
        private readonly AttendanceIssueService $attendanceIssueService
    ) {}

    /**
     * 勤務実績レポート画面を表示
     */
    public function index(Request $request): Response
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id;
        $now = CarbonImmutable::now();

        $selectedUserId = $request->query('user_id');

        // シフト表示設定を取得（シフト管理と同じ期間設定を使用）
        $settings = $this->companySettingService->getSettings($companyId);
        $shiftDisplayPeriod = match ($settings['shiftDisplay']['periodId']) {
            2 => 'closing_day_based',
            default => 'monthly',
        };
        $payrollClosingDay = $settings['payrollClosingDay'] ?? 25;

        $defaultDates = $this->getDefaultDateRange($shiftDisplayPeriod, $payrollClosingDay);
        $startDate = CarbonImmutable::parse($request->query('start_date', $defaultDates['start']));
        $endDate = CarbonImmutable::parse($request->query('end_date', $defaultDates['end']));

        // スコープに基づいてアクセス可能なユーザーIDを取得
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_ATTENDANCE_VIEW
        );

        // ユーザー一覧を取得（スコープでフィルタリング）
        $users = $this->userService->findByCompanyId($companyId)
            ->filter(fn ($user) => in_array($user->id, $accessibleUserIds, true))
            ->values();
        $users->loadMissing('departments');
        $usersData = $users->map(function ($user) {
            $primaryDepartment = $user->departments->where('pivot.is_primary', true)->first();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $user->employee_code,
                'department' => $primaryDepartment ? [
                    'id' => $primaryDepartment->id,
                    'name' => $primaryDepartment->name,
                ] : null,
            ];
        });

        // 指定されたユーザーが対象リストに存在しない場合は先頭のユーザーを採用
        $availableUserIds = $usersData->pluck('id');
        $effectiveUserId = $availableUserIds->contains((int) $selectedUserId)
            ? (int) $selectedUserId
            : $availableUserIds->first();

        $workSummaries = collect();
        $timeRecords = collect();
        $approvedRequests = collect();
        $shiftsMap = collect();
        $correctionsData = [];
        $monthlySummary = null;

        if ($effectiveUserId) {
            // 勤務実績（日次サマリー）を取得
            $workSummaries = $this->dailyWorkSummaryService->getByUserIdAndDateRange(
                $companyId,
                $effectiveUserId,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            // 打刻データを取得
            $timeRecords = $this->timeRecordService->getTimeRecordsByUserIdAndDateTimeRange(
                $companyId,
                $effectiveUserId,
                $startDate->startOfDay()->format('Y-m-d H:i:s'),
                $endDate->endOfDay()->format('Y-m-d H:i:s')
            );

            // 承認済み申請を取得
            $approvedRequests = $this->requestService->getRequestsByUserIdAndDateRange(
                $companyId,
                $effectiveUserId,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
                RequestStatusEnum::APPROVED->value
            );

            // 月間サマリーを計算
            $monthlySummary = $this->dailyWorkSummaryService->calculateMonthlySummary(
                $companyId,
                $effectiveUserId,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            // シフトデータを取得（パターン名のマップ）
            $shifts = $this->shiftService->getShiftsByUserIdAndDateRange(
                $companyId,
                $effectiveUserId,
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
                        // 打刻がない日にシフトの休憩を当てた場合、
                        // 打刻された休憩と見分けられるよう画面へ渡す
                        'auto_fill_break' => (bool) $pattern->auto_fill_break,
                    ],
                ];
            })->filter();

            // 打刻修正履歴を取得
            $correctionsData = $this->dailyWorkSummaryService->getCorrectionsByUserIdAndDateRange(
                $effectiveUserId,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );
        }

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

        // 打刻データを整形
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

        // 承認済み申請データを整形
        $approvedRequestsData = $approvedRequests->map(function ($request) {
            return [
                'id' => $request->id,
                'target_date' => $request->target_date->format('Y-m-d'),
                'type' => [
                    'value' => $request->type,
                    'code' => $request->applicationType?->code ?? '',
                    'label' => $request->applicationType?->name ?? 'その他',
                ],
                'start_time' => $request->start_time ? substr($request->start_time, 0, 5) : null,
                'end_time' => $request->end_time ? substr($request->end_time, 0, 5) : null,
                'reason' => $request->reason,
                'decided_at' => $request->decided_at?->format('Y-m-d H:i'),
                'approver' => $request->approver ? [
                    'id' => $request->approver->id,
                    'name' => $request->approver->name,
                ] : null,
            ];
        });

        return Inertia::render('Admin/Reports', [
            'users' => $usersData,
            'workSummaries' => $workSummariesData,
            'attendanceIssues' => $this->attendanceIssueService->detectAll(collect($workSummariesData), $timeRecords),
            'timeRecords' => $timeRecordsData,
            'approvedRequests' => $approvedRequestsData,
            'timeRecordCorrections' => $correctionsData,
            'monthlySummary' => $monthlySummary,
            'canExport' => $authUser->isAdmin(),
            'exportBatchSize' => $this->exportBatchSize(),
            'shifts' => $shiftsMap,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'user_id' => $effectiveUserId,
                'shiftDisplayPeriod' => $shiftDisplayPeriod,
                'payrollClosingDay' => $payrollClosingDay,
            ],
        ]);
    }

    /**
     * 勤務実績をCSVでエクスポート
     *
     * scope=all の場合は全従業員を1つのCSVで出力（集計なし）
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'この操作は管理者のみ実行できます。');
        }

        $companyId = auth()->user()->company_id;
        $now = CarbonImmutable::now();
        $scope = $request->query('scope', 'single');

        // 日付範囲を決定（start_date/end_date優先、なければmonthからカレンダー月）
        [$startDate, $endDate] = $this->resolveExportDateRange($request, $now);

        $periodLabel = $startDate->format('Y年m月d日').'〜'.$endDate->format('m月d日');
        $users = $this->userService->findByCompanyId($companyId);

        if ($scope === 'all') {
            // 全従業員: 1つのCSVにまとめて出力
            $batch = $this->resolveExportBatch($request, $users->count());
            $targetUsers = $batch === null
                ? $users
                : $users->slice($batch['offset'], $batch['size'])->values();

            $csvContent = $this->dailyWorkSummaryService->generateCsvAll(
                $companyId,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
                $targetUsers
            );

            $filename = sprintf(
                '勤務実績_%s_%s.csv',
                $batch === null ? '全従業員' : $batch['label'],
                $periodLabel
            );

            return response()->streamDownload(function () use ($csvContent) {
                echo "\xEF\xBB\xBF".$csvContent;
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        // 個別ユーザーのCSVを生成
        $selectedUserId = $request->query('user_id');
        $availableUserIds = $users->pluck('id');
        $effectiveUserId = $availableUserIds->contains((int) $selectedUserId)
            ? (int) $selectedUserId
            : $availableUserIds->first();

        if (! $effectiveUserId) {
            abort(404, 'ユーザーが見つかりません');
        }

        $user = $users->firstWhere('id', $effectiveUserId);
        $csvContent = $this->dailyWorkSummaryService->generateCsv(
            $companyId,
            $effectiveUserId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $user
        );

        $filename = sprintf(
            '勤務実績_%s_%s.csv',
            $user->name,
            $periodLabel
        );

        return response()->streamDownload(function () use ($csvContent) {
            echo "\xEF\xBB\xBF".$csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 勤務実績をExcelでエクスポート
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'この操作は管理者のみ実行できます。');
        }

        $companyId = auth()->user()->company_id;
        $now = CarbonImmutable::now();
        $scope = $request->query('scope', 'single');

        // 日付範囲を決定（start_date/end_date優先、なければmonthからカレンダー月）
        [$startDate, $endDate] = $this->resolveExportDateRange($request, $now);

        $periodLabel = $startDate->format('Y年m月d日').'〜'.$endDate->format('m月d日');
        $users = $this->userService->findByCompanyId($companyId);

        if ($scope === 'all') {
            // 全従業員: 1人1ファイルのExcelをZIPにまとめる
            $batch = $this->resolveExportBatch($request, $users->count());
            $targetUsers = $batch === null
                ? $users
                : $users->slice($batch['offset'], $batch['size'])->values();

            $zipPath = tempnam(sys_get_temp_dir(), 'excel_export_');
            $tempFiles = [];

            try {
                $zip = new \ZipArchive;
                $zip->open($zipPath, \ZipArchive::OVERWRITE);

                // 人数分のブックを一度に抱えるとメモリが尽きるため、
                // バッチサイズごとに区切って解放しながら生成する
                foreach ($targetUsers->chunk($this->exportBatchSize()) as $chunk) {
                    foreach ($chunk as $user) {
                        $filePath = $this->attendanceExcelExportService->generate(
                            $companyId,
                            $user,
                            $startDate->format('Y-m-d'),
                            $endDate->format('Y-m-d')
                        );

                        $excelFilename = sprintf(
                            '勤務実績_%s_%s.xlsx',
                            $user->name,
                            $periodLabel
                        );

                        $zip->addFile($filePath, $excelFilename);
                        $tempFiles[] = $filePath;
                    }

                    gc_collect_cycles();
                }

                $zip->close();
            } finally {
                // 例外発生時も一時ファイルを確実に削除
                foreach ($tempFiles as $tempFile) {
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            }

            $zipFilename = sprintf(
                '勤務実績_%s_%s.zip',
                $batch === null ? '全従業員' : $batch['label'],
                $periodLabel
            );

            return response()->download($zipPath, $zipFilename, [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);
        } else {
            $selectedUserId = $request->query('user_id');
            $availableUserIds = $users->pluck('id');
            $effectiveUserId = $availableUserIds->contains((int) $selectedUserId)
                ? (int) $selectedUserId
                : $availableUserIds->first();

            if (! $effectiveUserId) {
                abort(404, 'ユーザーが見つかりません');
            }

            $user = $users->firstWhere('id', $effectiveUserId);

            $filePath = $this->attendanceExcelExportService->generate(
                $companyId,
                $user,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            $filename = sprintf(
                '勤務実績_%s_%s.xlsx',
                $user->name,
                $periodLabel
            );
        }

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * 勤務実績を新規作成（打刻がない日）
     */
    public function store(StoreWorkSummaryRequest $request): RedirectResponse
    {
        $companyId = auth()->user()->company_id;
        $userId = (int) $request->validated('user_id');

        // ユーザーの会社IDが一致するか検証
        $user = $this->userService->findById($userId);
        if (! $user || $user->company_id !== $companyId) {
            abort(403, 'この従業員の勤務実績を編集する権限がありません。');
        }

        $this->dailyWorkSummaryService->createOrUpdateWorkTimes(
            $companyId,
            $userId,
            $request->validated('work_date'),
            $request->validated('work_start'),
            $request->validated('work_end'),
            $request->validated('break_periods') ?? [],
            auth()->user()->id
        );

        return back()->with('success', '勤務実績を登録しました。');
    }

    /**
     * 勤務実績を更新
     */
    public function update(UpdateWorkSummaryRequest $request, int $id): RedirectResponse
    {
        $summary = $this->dailyWorkSummaryService->findById($id);

        // 会社IDの一致チェック
        if ($summary->company_id !== auth()->user()->company_id) {
            abort(403, 'この勤務実績を編集する権限がありません。');
        }

        $this->dailyWorkSummaryService->updateWorkTimes(
            $id,
            $request->validated('work_start'),
            $request->validated('work_end'),
            $request->validated('break_periods') ?? [],
            auth()->user()->id
        );

        return back()->with('success', '勤務実績を更新しました。');
    }

    /**
     * 勤務実績を削除（打刻データを含む）
     */
    public function destroy(int $id): RedirectResponse
    {
        $summary = $this->dailyWorkSummaryService->findById($id);

        // 会社IDの一致チェック
        if ($summary->company_id !== auth()->user()->company_id) {
            abort(403, 'この勤務実績を削除する権限がありません。');
        }

        $this->dailyWorkSummaryService->deleteWorkTimes($id);

        return back()->with('success', '打刻を削除しました。');
    }

    /**
     * シフト表示期間設定に応じたデフォルト日付範囲を取得
     *
     * @return array{start: string, end: string}
     */
    private function getDefaultDateRange(string $displayPeriod, int $closingDay = 25): array
    {
        $now = CarbonImmutable::now();

        if ($displayPeriod === 'closing_day_based') {
            return $this->getClosingDayBasedDateRange($now, $closingDay);
        }

        return [
            'start' => $now->startOfMonth()->format('Y-m-d'),
            'end' => $now->endOfMonth()->format('Y-m-d'),
        ];
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
     * 全従業員出力のバッチサイズ（人数）
     */
    private function exportBatchSize(): int
    {
        return max(1, (int) config('attendance.export_batch_size', 10));
    }

    /**
     * 出力対象のバッチを決定
     *
     * batch は 1 始まりで、1 なら 1〜10名、2 なら 11〜20名を指す。
     * 指定がなければ全員を1つにまとめるため null を返す。
     *
     * @return array{offset: int, size: int, label: string}|null
     */
    private function resolveExportBatch(Request $request, int $userCount): ?array
    {
        $batch = $request->query('batch');

        if ($batch === null || $batch === '') {
            return null;
        }

        $size = $this->exportBatchSize();
        $batchNumber = max(1, (int) $batch);
        $offset = ($batchNumber - 1) * $size;

        if ($offset >= $userCount) {
            abort(404, '該当する従業員がいません。');
        }

        return [
            'offset' => $offset,
            'size' => $size,
            'label' => sprintf('%d〜%d名', $offset + 1, min($offset + $size, $userCount)),
        ];
    }

    /**
     * 対象月の解析とフォールバック処理
     */
    /**
     * エクスポート用の日付範囲を決定
     *
     * start_date/end_dateがあればそちらを使用、なければmonthからカレンダー月
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveExportDateRange(Request $request, CarbonImmutable $default): array
    {
        $startDateParam = $request->query('start_date');
        $endDateParam = $request->query('end_date');

        if ($startDateParam && $endDateParam) {
            return [
                CarbonImmutable::parse($startDateParam),
                CarbonImmutable::parse($endDateParam),
            ];
        }

        // 後方互換: monthパラメータからカレンダー月
        $requestedMonth = $request->query('month', $default->format('Y-m'));
        $targetMonth = $this->resolveTargetMonth($requestedMonth, $default);

        return [
            $targetMonth->startOfMonth(),
            $targetMonth->endOfMonth(),
        ];
    }

    private function resolveTargetMonth(string $requestedMonth, CarbonImmutable $default): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m', $requestedMonth)->startOfMonth();
        } catch (\Throwable $e) {
            return $default->startOfMonth();
        }
    }
}

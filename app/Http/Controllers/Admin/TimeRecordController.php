<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TimeRecordService;
use App\Services\UserService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者向け勤務実績コントローラー
 */
class TimeRecordController extends Controller
{
    public function __construct(
        private readonly TimeRecordService $timeRecordService,
        private readonly UserService $userService
    ) {}

    /**
     * 勤務実績レポート画面を表示
     */
    public function index(Request $request): Response
    {
        $companyId = auth()->user()->company_id;
        $now = CarbonImmutable::now();

        $requestedMonth = $request->query('month', $now->format('Y-m'));
        $selectedUserId = $request->query('user_id');
        $targetMonth = $this->resolveTargetMonth($requestedMonth, $now);

        $startDate = $targetMonth->startOfMonth();
        $endDate = $targetMonth->endOfMonth();

        $users = $this->userService->findByCompanyId($companyId);
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

        $timeRecordsData = collect();

        if ($effectiveUserId) {
            $timeRecords = $this->timeRecordService->getTimeRecordsByUserIdAndDateTimeRange(
                $companyId,
                $effectiveUserId,
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
        }

        return Inertia::render('Admin/Reports', [
            'users' => $usersData,
            'timeRecords' => $timeRecordsData,
            'filters' => [
                'month' => $targetMonth->format('Y-m'),
                'user_id' => $effectiveUserId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * 対象月の解析とフォールバック処理
     */
    private function resolveTargetMonth(string $requestedMonth, CarbonImmutable $default): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m', $requestedMonth)->startOfMonth();
        } catch (\Throwable $e) {
            return $default->startOfMonth();
        }
    }
}

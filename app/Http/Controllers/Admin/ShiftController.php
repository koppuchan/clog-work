<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertShiftRequest;
use App\Services\CompanySettingService;
use App\Services\DepartmentService;
use App\Services\PermissionService;
use App\Services\ShiftLeaveService;
use App\Services\ShiftPatternService;
use App\Services\ShiftService;
use App\Services\UserService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者向けシフト管理コントローラー
 */
class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly UserService $userService,
        private readonly DepartmentService $departmentService,
        private readonly ShiftPatternService $shiftPatternService,
        private readonly CompanySettingService $companySettingService,
        private readonly PermissionService $permissionService,
        private readonly ShiftLeaveService $shiftLeaveService
    ) {}

    /**
     * シフト一覧を表示
     */
    public function index(Request $request): Response
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id;

        // スコープに基づいてアクセス可能なユーザーIDを取得（閲覧用）
        $accessibleUserIdsForView = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_SHIFT_VIEW
        );

        // スコープに基づいてアクセス可能なユーザーIDを取得（編集用）
        $accessibleUserIdsForEdit = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_SHIFT_EDIT
        );

        // シフト表示期間設定を取得
        $settings = $this->companySettingService->getSettings($companyId);
        $shiftDisplayPeriod = match ($settings['shiftDisplay']['periodId']) {
            2 => 'closing_day_based',
            default => 'monthly',
        };
        $payrollClosingDay = $settings['payrollClosingDay'] ?? 25;

        // クエリパラメータから日付範囲を取得（デフォルトはシフト表示期間設定に従う）
        $defaultDates = $this->getDefaultDateRange($shiftDisplayPeriod, $payrollClosingDay);
        $startDate = $request->query('start_date', $defaultDates['start']);
        $endDate = $request->query('end_date', $defaultDates['end']);

        // 部署フィルター
        $departmentId = $request->query('department_id');

        // シフトデータを取得
        if ($departmentId) {
            $shifts = $this->shiftService->getShiftsByDepartmentIdAndDateRange(
                $companyId,
                (int) $departmentId,
                $startDate,
                $endDate
            );
        } else {
            $shifts = $this->shiftService->getShiftsByCompanyIdAndDateRange(
                $companyId,
                $startDate,
                $endDate
            );
        }

        // スコープに基づいてシフトをフィルタリング
        $shifts = $shifts->filter(fn ($shift) => in_array($shift->user_id, $accessibleUserIdsForView, true))
            ->values();

        // シフト表示用にユーザー一覧を取得（対象日付時点で在籍しているユーザーのみ、スコープでフィルタリング）
        $users = $this->userService->findByCompanyIdForShift($companyId, $startDate)
            ->filter(fn ($user) => in_array($user->id, $accessibleUserIdsForView, true))
            ->values();

        // 部署一覧を取得
        $departments = $this->departmentService->findByCompanyId($companyId);

        // シフトパターン一覧を取得
        $shiftPatterns = $this->shiftPatternService->getShiftPatternsByCompanyId($companyId);

        // シフトデータをフロントエンド用にフォーマット
        $shiftsFormatted = $shifts->map(function ($shift) {
            $color = $shift->shiftPattern?->colors->first();

            return [
                'id' => $shift->id,
                'user_id' => $shift->user_id,
                'user_name' => $shift->user->name,
                'shift_date' => $shift->shift_date->format('Y-m-d'),
                'shift_pattern_id' => $shift->shift_pattern_id,
                'shift_pattern_name' => $shift->shiftPattern?->name,
                'shift_pattern_color' => $color ? [
                    'background_color' => $color->background_color,
                    'text_color' => $color->text_color,
                ] : null,
                'note' => $shift->note,
            ];
        });

        // 承認済みの休暇をシフト表に反映する（セル表示と出勤人数の減算）
        $leaves = $this->shiftLeaveService->getLeavesForShift(
            $companyId,
            $users->pluck('id')->all(),
            $startDate,
            $endDate,
        );

        return Inertia::render('Admin/Shifts', [
            'shifts' => $shiftsFormatted,
            'leaves' => $leaves,
            'users' => $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $user->employee_code,
                'department_id' => $user->departments->where('pivot.is_primary', true)->first()?->id,
            ]),
            'departments' => $departments->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
            ]),
            'shiftPatterns' => $shiftPatterns,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_id' => $departmentId,
            ],
            'shiftDisplayPeriod' => $shiftDisplayPeriod,
            'payrollClosingDay' => $payrollClosingDay,
            'editableUserIds' => $accessibleUserIdsForEdit,
        ]);
    }

    /**
     * シフトを一括登録・更新
     */
    public function upsert(UpsertShiftRequest $request): JsonResponse
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id;
        $validated = $request->validated();

        // スコープに基づいて編集可能なユーザーIDを取得
        $accessibleUserIdsForEdit = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_SHIFT_EDIT
        );

        // 編集対象のユーザーIDが全て編集可能か確認
        $requestedUserIds = collect($validated['shifts'])->pluck('user_id')->unique()->toArray();
        foreach ($requestedUserIds as $userId) {
            if (! in_array($userId, $accessibleUserIdsForEdit, true)) {
                abort(403, 'このユーザーのシフトを編集する権限がありません。');
            }
        }

        $this->shiftService->upsertMany($companyId, $validated['shifts']);

        return response()->json([
            'message' => 'シフトを保存しました。',
        ]);
    }

    /**
     * シフトを削除
     */
    public function destroy(int $id): JsonResponse
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id;

        // 削除対象のシフトが自社のものか確認
        $shift = $this->shiftService->findById($id);
        if ($shift->company_id !== $companyId) {
            abort(403, 'このシフトを削除する権限がありません。');
        }

        // スコープに基づいて編集可能なユーザーIDを取得
        $accessibleUserIdsForEdit = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_SHIFT_EDIT
        );

        // 削除対象のシフトのユーザーが編集可能か確認
        if (! in_array($shift->user_id, $accessibleUserIdsForEdit, true)) {
            abort(403, 'このユーザーのシフトを削除する権限がありません。');
        }

        $this->shiftService->delete($id);

        return response()->json([
            'message' => 'シフトを削除しました。',
        ]);
    }

    /**
     * ユーザーの曜日別デフォルトシフトパターンと勤務可能曜日を取得
     */
    public function getUserShiftInfo(int $userId): JsonResponse
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id;

        // ユーザーが同じ会社に所属しているか確認
        $user = $this->userService->findById($userId);
        if ($user->company_id !== $companyId) {
            abort(403, 'このユーザーの情報を取得する権限がありません。');
        }

        // スコープに基づいてアクセス可能か確認
        $accessibleUserIdsForView = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_SHIFT_VIEW
        );

        if (! in_array($userId, $accessibleUserIdsForView, true)) {
            abort(403, 'このユーザーの情報を取得する権限がありません。');
        }

        // 曜日別シフトパターンを取得
        $shiftPatterns = $this->shiftService->getUserShiftPatterns($userId);

        return response()->json([
            'shift_patterns' => $shiftPatterns,
        ]);
    }

    /**
     * 指定月に在籍している全ユーザーのシフトパターンと勤務可能曜日を一括取得
     */
    public function getBulkUsersShiftInfo(Request $request): JsonResponse
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id;

        // リクエストから対象年月を取得（デフォルトは今月）
        $targetDate = $request->query('target_date', CarbonImmutable::now()->startOfMonth()->format('Y-m-d'));

        // スコープに基づいてアクセス可能なユーザーIDを取得
        $accessibleUserIdsForView = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_SHIFT_VIEW
        );

        // 対象月に在籍しているユーザーを取得（スコープでフィルタリング）
        $users = $this->userService->findByCompanyIdForShift($companyId, $targetDate)
            ->filter(fn ($user) => in_array($user->id, $accessibleUserIdsForView, true));

        if ($users->isEmpty()) {
            return response()->json([
                'users_shift_info' => [],
            ]);
        }

        // ユーザーIDの配列を取得
        $userIds = $users->pluck('id')->toArray();

        // 一括取得
        $usersShiftInfo = $this->shiftService->getBulkUsersShiftInfo($userIds);

        return response()->json([
            'users_shift_info' => $usersShiftInfo,
        ]);
    }

    /**
     * シフト表示期間設定に基づいてデフォルトの日付範囲を取得
     *
     * @param  string  $displayPeriod  表示期間タイプ（'monthly', 'closing_day_based'）
     * @param  int  $closingDay  給与締め日（1-31）
     * @return array{start: string, end: string}
     */
    private function getDefaultDateRange(string $displayPeriod, int $closingDay = 25): array
    {
        $now = CarbonImmutable::now();

        if ($displayPeriod === 'closing_day_based') {
            // 締め日翌日スタート
            return $this->getClosingDayBasedDateRange($now, $closingDay);
        }

        // 月初〜月末
        $start = $now->startOfMonth();
        $end = $now->endOfMonth();

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
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
        // 安全な日付設定（月末を超えないように）
        $safeDay = fn (CarbonImmutable $date, int $day): int => min($day, $date->daysInMonth);

        if ($now->day <= $safeDay($now, $closingDay)) {
            // 締め日以前: 前月(締め日+1)〜今月締め日
            $endDate = $now->setDay($safeDay($now, $closingDay));
            $startMonth = $now->subMonth();
            $startDay = $closingDay + 1;

            // 締め日が月末の場合、翌日は翌月1日
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
}

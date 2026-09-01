<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompanySettingService;
use App\Services\PermissionService;
use App\Services\ShiftLeaveService;
use App\Services\ShiftPatternService;
use App\Services\ShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * スタッフ向けシフト管理コントローラー
 */
class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly ShiftPatternService $shiftPatternService,
        private readonly PermissionService $permissionService,
        private readonly CompanySettingService $companySettingService,
        private readonly ShiftLeaveService $shiftLeaveService
    ) {}

    /**
     * シフト一覧を表示
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $user->company_id;
        $userId = $user->id;

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

        // ユーザーの権限からシフト閲覧スコープを取得
        $scope = $this->permissionService->getShiftViewScope($user);
        $departmentId = $user->primaryDepartment()->first()?->id;

        // 権限に基づいたシフトデータを取得
        $shiftData = $this->shiftService->getStaffShiftViewData(
            $companyId,
            $userId,
            $startDate,
            $endDate,
            $scope,
            $departmentId
        );

        // シフトパターン一覧を取得
        $shiftPatterns = $this->shiftPatternService->getShiftPatternsByCompanyId($companyId);

        // 承認済みの休暇をシフト表に反映する（管理者画面と同じ仕様）
        $leaves = $this->shiftLeaveService->getLeavesForShift(
            $companyId,
            collect($shiftData['users'])->pluck('id')->all(),
            $startDate,
            $endDate,
        );

        return Inertia::render('Staff/Shifts', [
            'users' => $shiftData['users'],
            'shifts' => $shiftData['shifts'],
            'leaves' => $leaves,
            'shiftPatterns' => $shiftPatterns,
            'currentUserId' => $userId,
            'scope' => $shiftData['scope'],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'shiftDisplayPeriod' => $shiftDisplayPeriod,
            'payrollClosingDay' => $payrollClosingDay,
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
            return $this->getClosingDayBasedDateRange($now, $closingDay);
        }

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
        $safeDay = fn (CarbonImmutable $date, int $day): int => min($day, $date->daysInMonth);

        if ($now->day <= $safeDay($now, $closingDay)) {
            $endDate = $now->setDay($safeDay($now, $closingDay));
            $startMonth = $now->subMonth();
            $startDay = $closingDay + 1;

            if ($closingDay >= $startMonth->daysInMonth) {
                $startDate = $now->startOfMonth();
            } else {
                $startDate = $startMonth->setDay(min($startDay, $startMonth->daysInMonth));
            }
        } else {
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

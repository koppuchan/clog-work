<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\StoreShiftPatternRequest;
use App\Http\Requests\UpdateCompanySettingsRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Requests\UpdateShiftPatternRequest;
use App\Services\CompanyLaborAlertSettingService;
use App\Services\CompanyService;
use App\Services\CompanySettingService;
use App\Services\DepartmentService;
use App\Services\ShiftPatternService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 設定コントローラー
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
        private readonly CompanySettingService $companySettingService,
        private readonly DepartmentService $departmentService,
        private readonly ShiftPatternService $shiftPatternService,
        private readonly CompanyLaborAlertSettingService $laborAlertSettingService
    ) {}

    /**
     * 設定画面を表示
     */
    public function index(): Response
    {
        $companyId = auth()->user()->company_id;
        $company = $this->companyService->findById($companyId);

        // 会社の定休日を取得
        $companyRegularHolidays = $this->companyService->getRegularHolidays($companyId);

        // 会社設定を取得（シフト表示期間、打刻丸め等）
        $companySettingsData = $this->companySettingService->getSettings($companyId);

        // 部署一覧を取得
        $departments = $this->departmentService->getDepartmentsForSettings($companyId);

        // シフトパターン一覧を取得
        $shiftPatterns = $this->shiftPatternService->getShiftPatternsForSettings($companyId);

        // シフトカラー一覧を取得
        $shiftColors = $this->shiftPatternService->getShiftColorsForSettings();

        // 労務アラート設定を取得
        $laborAlertSettings = $this->laborAlertSettingService->getSettings($companyId);

        // 打刻専用画面のURLを生成
        $publicStampUrl = config('attendance.public_stamp_base_url').'/stamp/'.$company->uuid;

        return Inertia::render('Admin/Settings', [
            'company' => [
                'id' => $company->id,
                'company_code' => $company->company_code,
                'name' => $company->name,
                'is_closed_on_holidays' => $company->is_closed_on_holidays,
                'payroll_closing_day' => $company->payroll_closing_day,
                'paid_leave_half_day' => $company->paid_leave_half_day,
                'paid_leave_hourly' => $company->paid_leave_hourly,
                'daily_working_hours' => $company->daily_working_hours,
                'is_stamp_hidden' => $company->is_stamp_hidden,
                'public_stamp_url' => $publicStampUrl,
            ],
            'companyRegularHolidays' => $companyRegularHolidays,
            'companySettings' => $companySettingsData,
            'departments' => $departments,
            'shiftPatterns' => $shiftPatterns,
            'shiftColors' => $shiftColors,
            'laborAlertSettings' => $laborAlertSettings,
            'canEdit' => auth()->user()->isAdmin(),
        ]);
    }

    /**
     * 設定を更新
     */
    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $companyId = auth()->user()->company_id;
        $data = $request->validated();

        // 会社基本情報の更新
        // 定休日設定（weekendDays / includeNationalHolidays）は UI から非表示にしたため、
        // フィールドが送られてきた場合のみ既存値を上書きする
        $basicInfo = ['name' => $data['companyName']];
        if (array_key_exists('weekendDays', $data)) {
            $basicInfo['weekendDays'] = $this->convertWeekendDaysToWeekdayIds($data['weekendDays']);
        }
        if (array_key_exists('includeNationalHolidays', $data)) {
            $basicInfo['includeNationalHolidays'] = (bool) $data['includeNationalHolidays'];
        }
        $this->companySettingService->updateBasicInfo($companyId, $basicInfo);

        // 給与締め日の更新
        if (isset($data['payrollClosingDay'])) {
            $closingDay = $data['payrollClosingDay'] === 'end' ? 31 : (int) $data['payrollClosingDay'];
            $this->companySettingService->updatePayrollClosingDay($companyId, $closingDay);
        }

        // シフト表示期間設定の更新
        if (isset($data['shiftDisplayPeriod'])) {
            $periodId = match ($data['shiftDisplayPeriod']) {
                'monthly' => 1,
                'closing_day_based' => 2,
                default => 1,
            };
            $this->companySettingService->updateShiftDisplaySetting($companyId, $periodId);
        }

        // 打刻丸め設定の更新
        if (isset($data['clockRounding'])) {
            $roundingUnitId = $this->convertClockRoundingToId($data['clockRounding']);
            $this->companySettingService->updateClockRoundingSetting($companyId, $roundingUnitId);
        }

        // 基本シフトパターンの更新
        if (array_key_exists('defaultShiftPatternId', $data)) {
            $this->companySettingService->updateDefaultShiftPattern(
                $companyId,
                $data['defaultShiftPatternId'] ? (int) $data['defaultShiftPatternId'] : null
            );
        }

        // 打刻画面非表示設定の更新
        if (array_key_exists('isStampHidden', $data)) {
            $this->companySettingService->updateStampHiddenSetting(
                $companyId,
                (bool) $data['isStampHidden']
            );
        }

        // 有給休暇設定の更新
        $this->companySettingService->updatePaidLeaveSettings($companyId, [
            'halfDay' => $data['paidLeaveHalfDay'] ?? false,
            'hourly' => $data['paidLeaveHourly'] ?? false,
            'dailyWorkingHours' => isset($data['dailyWorkingHours']) ? (float) $data['dailyWorkingHours'] : null,
        ]);

        // 労務アラート設定の更新
        $this->laborAlertSettingService->updateSettings($companyId, [
            'overtimeNotification' => isset($data['alertOvertimeNotification']) ? (int) $data['alertOvertimeNotification'] : null,
            'overtimeLimit' => isset($data['alertOvertimeLimit']) ? (int) $data['alertOvertimeLimit'] : null,
            'messages' => [
                'warning' => [
                    'title' => $data['alertMessages']['warningTitle'] ?? null,
                    'message' => $data['alertMessages']['warningMessage'] ?? null,
                ],
                'caution' => [
                    'title' => $data['alertMessages']['cautionTitle'] ?? null,
                    'message' => $data['alertMessages']['cautionMessage'] ?? null,
                ],
            ],
        ]);

        // 部署の一括同期
        $this->departmentService->syncDepartments(
            $companyId,
            $data['departments'] ?? [],
            $data['deletedDepartmentIds'] ?? []
        );

        // シフトパターンの一括同期
        $this->shiftPatternService->syncShiftPatterns(
            $companyId,
            $data['shiftPatterns'] ?? [],
            $data['deletedShiftPatternIds'] ?? []
        );

        return redirect()
            ->route('admin.settings')
            ->with('success', '設定を保存しました。');
    }

    /**
     * 曜日設定を曜日IDの配列に変換
     *
     * @param  array<string, bool>  $weekendDays  曜日設定
     * @return array<int> 曜日IDの配列 (1=月曜, 2=火曜, ..., 7=日曜)
     */
    private function convertWeekendDaysToWeekdayIds(array $weekendDays): array
    {
        $weekdayMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        $weekdayIds = [];
        foreach ($weekendDays as $day => $isHoliday) {
            if ($isHoliday && isset($weekdayMap[$day])) {
                $weekdayIds[] = $weekdayMap[$day];
            }
        }

        return $weekdayIds;
    }

    /**
     * 打刻丸め設定をIDに変換
     *
     * @param  string  $clockRounding  打刻丸め設定
     * @return int 丸め単位ID
     */
    private function convertClockRoundingToId(string $clockRounding): int
    {
        return match ($clockRounding) {
            '5min' => 2,
            '10min' => 3,
            '15min' => 4,
            '30min' => 5,
            default => 1, // 'none' = 丸めなし
        };
    }

    /**
     * 部署を追加
     */
    public function storeDepartment(StoreDepartmentRequest $request): JsonResponse
    {
        $companyId = auth()->user()->company_id;
        $department = $this->departmentService->createDepartment($companyId, $request->validated());

        return response()->json($department);
    }

    /**
     * 部署を更新
     */
    public function updateDepartment(UpdateDepartmentRequest $request, int $id): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        if (! $this->departmentService->belongsToCompany($id, $companyId)) {
            return response()->json(['error' => '部署が見つかりません'], 404);
        }

        $department = $this->departmentService->updateDepartment($id, $request->validated());

        return response()->json($department);
    }

    /**
     * 部署を削除
     */
    public function destroyDepartment(int $id): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        if (! $this->departmentService->belongsToCompany($id, $companyId)) {
            return response()->json(['error' => '部署が見つかりません'], 404);
        }

        try {
            $this->departmentService->deleteDepartment($id);

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * シフトパターンを追加
     */
    public function storeShiftPattern(StoreShiftPatternRequest $request): JsonResponse
    {
        $companyId = auth()->user()->company_id;
        $shiftPattern = $this->shiftPatternService->createShiftPattern($companyId, $request->validated());

        return response()->json($shiftPattern);
    }

    /**
     * シフトパターンを更新
     */
    public function updateShiftPattern(UpdateShiftPatternRequest $request, int $id): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        if (! $this->shiftPatternService->belongsToCompany($id, $companyId)) {
            return response()->json(['error' => 'シフトパターンが見つかりません'], 404);
        }

        $shiftPattern = $this->shiftPatternService->updateShiftPattern($id, $request->validated());

        return response()->json($shiftPattern);
    }

    /**
     * シフトパターンを削除
     */
    public function destroyShiftPattern(int $id): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        if (! $this->shiftPatternService->belongsToCompany($id, $companyId)) {
            return response()->json(['error' => 'シフトパターンが見つかりません'], 404);
        }

        try {
            $this->shiftPatternService->deleteShiftPattern($id);

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}

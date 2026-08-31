<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyRegularHoliday;
use App\Models\CompanyShiftDisplaySetting;
use App\Models\CompanyShiftRoundingSetting;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * 会社設定サービス
 *
 * 設定画面での会社設定の取得・更新を行う
 */
class CompanySettingService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository
    ) {}

    /**
     * 会社設定を取得（フロントエンド用にフォーマット）
     *
     * @param  int  $companyId  会社ID
     * @return array{
     *   name: string,
     *   weekendDays: array<int>,
     *   includeNationalHolidays: bool,
     *   payrollClosingDay: int|null,
     *   isStampHidden: bool,
     *   paidLeave: array{halfDay: bool, hourly: bool, dailyWorkingHours: float|null},
     *   shiftDisplay: array{periodId: int|null},
     *   clockRounding: array{roundingUnitId: int|null}
     * }
     */
    public function getSettings(int $companyId): array
    {
        $company = $this->companyRepository->findByIdWithRelations($companyId);

        if (! $company) {
            return $this->getDefaultSettings();
        }

        // 定休日（曜日ID）を取得
        $weekendDays = $company->regularHolidays->pluck('weekday_id')->toArray();

        // シフト表示期間設定を取得
        $shiftDisplaySetting = CompanyShiftDisplaySetting::query()
            ->where('company_id', $companyId)
            ->first();

        // 打刻丸め設定を取得
        $clockRoundingSetting = CompanyShiftRoundingSetting::query()
            ->where('company_id', $companyId)
            ->first();

        return [
            'name' => $company->name ?? '',
            'weekendDays' => $weekendDays,
            'includeNationalHolidays' => $company->is_closed_on_holidays ?? false,
            'payrollClosingDay' => $company->payroll_closing_day,
            'defaultShiftPatternId' => $company->default_shift_pattern_id,
            'isStampHidden' => $company->is_stamp_hidden ?? false,
            'paidLeave' => [
                'halfDay' => $company->paid_leave_half_day ?? false,
                'hourly' => $company->paid_leave_hourly ?? false,
                'dailyWorkingHours' => $company->daily_working_hours ? (float) $company->daily_working_hours : null,
            ],
            'shiftDisplay' => [
                'periodId' => $shiftDisplaySetting?->shift_period_id,
            ],
            'clockRounding' => [
                'roundingUnitId' => $clockRoundingSetting?->rounding_unit_id,
            ],
        ];
    }

    /**
     * 会社基本情報を更新
     *
     * @param  int  $companyId  会社ID
     * @param  array{
     *   name?: string,
     *   weekendDays?: array<int>,
     *   includeNationalHolidays?: bool
     * }  $data  更新データ
     */
    public function updateBasicInfo(int $companyId, array $data): Company
    {
        return DB::transaction(function () use ($companyId, $data) {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['includeNationalHolidays'])) {
                $updateData['is_closed_on_holidays'] = $data['includeNationalHolidays'];
            }

            if (! empty($updateData)) {
                $this->companyRepository->update($companyId, $updateData);
            }

            // 定休日を更新
            if (isset($data['weekendDays'])) {
                $this->updateRegularHolidays($companyId, $data['weekendDays']);
            }

            return $this->companyRepository->findById($companyId);
        });
    }

    /**
     * 給与締め日を更新
     *
     * @param  int  $companyId  会社ID
     * @param  int  $closingDay  締め日（1-31）
     */
    public function updatePayrollClosingDay(int $companyId, int $closingDay): Company
    {
        return $this->companyRepository->update($companyId, [
            'payroll_closing_day' => $closingDay,
        ]);
    }

    /**
     * 有給休暇設定を更新
     *
     * 時間有給がONの場合は半日有給も自動的にONになる
     *
     * @param  int  $companyId  会社ID
     * @param  array{
     *   halfDay?: bool,
     *   hourly?: bool
     * }  $data  更新データ
     */
    public function updatePaidLeaveSettings(int $companyId, array $data): Company
    {
        $updateData = [];

        $hourly = $data['hourly'] ?? false;

        if (isset($data['halfDay'])) {
            // 時間有給ONなら半日有給も強制ON
            $updateData['paid_leave_half_day'] = $hourly || $data['halfDay'];
        }

        if (isset($data['hourly'])) {
            $updateData['paid_leave_hourly'] = $hourly;
        }

        if (array_key_exists('dailyWorkingHours', $data)) {
            $updateData['daily_working_hours'] = $data['dailyWorkingHours'];
        }

        return $this->companyRepository->update($companyId, $updateData);
    }

    /**
     * シフト表示期間設定を更新
     *
     * @param  int  $companyId  会社ID
     * @param  int  $periodId  シフト表示期間ID
     */
    public function updateShiftDisplaySetting(int $companyId, int $periodId): CompanyShiftDisplaySetting
    {
        return CompanyShiftDisplaySetting::query()->updateOrCreate(
            ['company_id' => $companyId],
            ['shift_period_id' => $periodId]
        );
    }

    /**
     * 打刻丸め設定を更新
     *
     * @param  int  $companyId  会社ID
     * @param  int  $roundingUnitId  丸め単位ID
     */
    public function updateClockRoundingSetting(int $companyId, int $roundingUnitId): CompanyShiftRoundingSetting
    {
        return CompanyShiftRoundingSetting::query()->updateOrCreate(
            ['company_id' => $companyId],
            ['rounding_unit_id' => $roundingUnitId]
        );
    }

    /**
     * 基本シフトパターンを更新
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $shiftPatternId  シフトパターンID（nullで解除）
     */
    public function updateDefaultShiftPattern(int $companyId, ?int $shiftPatternId): Company
    {
        return $this->companyRepository->update($companyId, [
            'default_shift_pattern_id' => $shiftPatternId,
        ]);
    }

    /**
     * 打刻画面非表示設定を更新
     *
     * @param  int  $companyId  会社ID
     * @param  bool  $isStampHidden  打刻画面を非表示にするかどうか
     */
    public function updateStampHiddenSetting(int $companyId, bool $isStampHidden): Company
    {
        return $this->companyRepository->update($companyId, [
            'is_stamp_hidden' => $isStampHidden,
        ]);
    }

    /**
     * 定休日を更新
     *
     * @param  int  $companyId  会社ID
     * @param  array<int>  $weekdayIds  曜日ID配列
     */
    private function updateRegularHolidays(int $companyId, array $weekdayIds): void
    {
        // 既存の定休日を削除
        CompanyRegularHoliday::query()
            ->where('company_id', $companyId)
            ->delete();

        // 新しい定休日を登録
        foreach ($weekdayIds as $weekdayId) {
            CompanyRegularHoliday::query()->create([
                'company_id' => $companyId,
                'weekday_id' => $weekdayId,
            ]);
        }
    }

    /**
     * デフォルト設定を取得
     *
     * @return array{
     *   name: string,
     *   weekendDays: array<int>,
     *   includeNationalHolidays: bool,
     *   payrollClosingDay: int|null,
     *   isStampHidden: bool,
     *   paidLeave: array{halfDay: bool, hourly: bool, dailyWorkingHours: float|null},
     *   shiftDisplay: array{periodId: int|null},
     *   clockRounding: array{roundingUnitId: int|null}
     * }
     */
    private function getDefaultSettings(): array
    {
        return [
            'name' => '',
            'weekendDays' => [],
            'includeNationalHolidays' => false,
            'payrollClosingDay' => null,
            'defaultShiftPatternId' => null,
            'isStampHidden' => false,
            'paidLeave' => [
                'halfDay' => false,
                'hourly' => false,
                'dailyWorkingHours' => null,
            ],
            'shiftDisplay' => [
                'periodId' => null,
            ],
            'clockRounding' => [
                'roundingUnitId' => null,
            ],
        ];
    }
}

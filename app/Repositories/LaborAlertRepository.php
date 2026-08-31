<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\AlertLevelEnum;
use App\Models\CompanyAlertMessage;
use App\Models\CompanyLaborAlertSetting;
use App\Models\DailyWorkSummary;
use App\Models\LaborAlertHistory;
use App\Repositories\Contracts\LaborAlertRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * 労務アラートリポジトリ実装
 */
class LaborAlertRepository implements LaborAlertRepositoryInterface
{
    public function __construct(
        private readonly DailyWorkSummary $dailyWorkSummary,
        private readonly CompanyLaborAlertSetting $alertSetting,
        private readonly CompanyAlertMessage $alertMessage,
        private readonly LaborAlertHistory $alertHistory
    ) {}

    // ========================================
    // 設定関連
    // ========================================

    /**
     * {@inheritDoc}
     */
    public function findAlertSettingsByCompanyId(int $companyId): Collection
    {
        return $this->alertSetting->query()
            ->where('company_id', $companyId)
            ->where('is_enabled', true)
            ->orderBy('alert_level_id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findAlertMessagesByCompanyId(int $companyId): Collection
    {
        return $this->alertMessage->query()
            ->where('company_id', $companyId)
            ->orderBy('alert_level_id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findAlertSettingByLevel(int $companyId, AlertLevelEnum $alertLevel): ?CompanyLaborAlertSetting
    {
        return $this->alertSetting->query()
            ->where('company_id', $companyId)
            ->where('alert_level_id', $alertLevel->value)
            ->where('is_enabled', true)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findAlertMessageByLevel(int $companyId, AlertLevelEnum $alertLevel): ?CompanyAlertMessage
    {
        return $this->alertMessage->query()
            ->where('company_id', $companyId)
            ->where('alert_level_id', $alertLevel->value)
            ->first();
    }

    // ========================================
    // アラート検出関連
    // ========================================

    /**
     * {@inheritDoc}
     */
    public function findUsersExceedingOvertimeThreshold(int $companyId, int $year, int $month, int $thresholdMinutes): Collection
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = CarbonImmutable::parse($startDate)->endOfMonth()->format('Y-m-d');

        return $this->dailyWorkSummary->query()
            ->selectRaw('user_id, company_id, SUM(overtime_minutes) as overtime_minutes')
            ->with(['user'])
            ->where('company_id', $companyId)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->groupBy('user_id', 'company_id')
            ->havingRaw('SUM(overtime_minutes) >= ?', [$thresholdMinutes])
            ->orderByRaw('SUM(overtime_minutes) DESC')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findMonthlyWorkSummaries(int $companyId, int $year, int $month): Collection
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = CarbonImmutable::parse($startDate)->endOfMonth()->format('Y-m-d');

        return $this->dailyWorkSummary->query()
            ->selectRaw('user_id, company_id, SUM(overtime_minutes) as overtime_minutes')
            ->with(['user'])
            ->where('company_id', $companyId)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->groupBy('user_id', 'company_id')
            ->get();
    }

    // ========================================
    // 履歴関連
    // ========================================

    /**
     * {@inheritDoc}
     */
    public function saveAlertHistory(array $attributes): LaborAlertHistory
    {
        return $this->alertHistory->query()->updateOrCreate(
            [
                'user_id' => $attributes['user_id'],
                'alert_level_id' => $attributes['alert_level_id'] instanceof AlertLevelEnum
                    ? $attributes['alert_level_id']->value
                    : $attributes['alert_level_id'],
                'target_year' => $attributes['target_year'],
                'target_month' => $attributes['target_month'],
            ],
            $attributes
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findUnreadAlertHistories(int $companyId, ?int $limit = null): Collection
    {
        $query = $this->alertHistory->query()
            ->with(['user'])
            ->where('company_id', $companyId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findAlertHistoriesByMonth(int $companyId, int $year, int $month): Collection
    {
        return $this->alertHistory->query()
            ->with(['user'])
            ->where('company_id', $companyId)
            ->where('target_year', $year)
            ->where('target_month', $month)
            ->orderBy('alert_level_id', 'desc')
            ->orderBy('alert_value', 'desc')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findUserAlertHistory(int $userId, int $year, int $month, ?AlertLevelEnum $alertLevel = null): ?LaborAlertHistory
    {
        $query = $this->alertHistory->query()
            ->where('user_id', $userId)
            ->where('target_year', $year)
            ->where('target_month', $month);

        if ($alertLevel !== null) {
            $query->where('alert_level_id', $alertLevel->value);
        }

        return $query->first();
    }

    /**
     * {@inheritDoc}
     */
    public function markAlertHistoryAsRead(int $historyId): void
    {
        $history = $this->alertHistory->query()->find($historyId);

        if ($history !== null) {
            $history->is_read = true;
            $history->read_at = CarbonImmutable::now();
            $history->save();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function markAllAlertHistoriesAsRead(int $companyId): void
    {
        $this->alertHistory->query()
            ->where('company_id', $companyId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => CarbonImmutable::now(),
            ]);
    }
}

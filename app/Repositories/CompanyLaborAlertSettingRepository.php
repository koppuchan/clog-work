<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\AlertLevelEnum;
use App\Models\CompanyAlertMessage;
use App\Models\CompanyLaborAlertSetting;
use App\Repositories\Contracts\CompanyLaborAlertSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * 労務アラート設定リポジトリ実装
 */
class CompanyLaborAlertSettingRepository implements CompanyLaborAlertSettingRepositoryInterface
{
    public function __construct(
        private readonly CompanyLaborAlertSetting $alertSetting,
        private readonly CompanyAlertMessage $alertMessage
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findSettingsByCompanyId(int $companyId): Collection
    {
        return $this->alertSetting->query()
            ->where('company_id', $companyId)
            ->orderBy('alert_level_id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findMessagesByCompanyId(int $companyId): Collection
    {
        return $this->alertMessage->query()
            ->where('company_id', $companyId)
            ->orderBy('alert_level_id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findSettingByLevel(int $companyId, AlertLevelEnum $alertLevel): ?CompanyLaborAlertSetting
    {
        return $this->alertSetting->query()
            ->where('company_id', $companyId)
            ->where('alert_level_id', $alertLevel->value)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findMessageByLevel(int $companyId, AlertLevelEnum $alertLevel): ?CompanyAlertMessage
    {
        return $this->alertMessage->query()
            ->where('company_id', $companyId)
            ->where('alert_level_id', $alertLevel->value)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function saveSetting(int $companyId, AlertLevelEnum $alertLevel, array $attributes): CompanyLaborAlertSetting
    {
        return $this->alertSetting->query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'alert_level_id' => $alertLevel->value,
            ],
            array_merge($attributes, [
                'company_id' => $companyId,
                'alert_level_id' => $alertLevel->value,
            ])
        );
    }

    /**
     * {@inheritDoc}
     */
    public function saveMessage(int $companyId, AlertLevelEnum $alertLevel, array $attributes): CompanyAlertMessage
    {
        return $this->alertMessage->query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'alert_level_id' => $alertLevel->value,
            ],
            array_merge($attributes, [
                'company_id' => $companyId,
                'alert_level_id' => $alertLevel->value,
            ])
        );
    }

    /**
     * {@inheritDoc}
     */
    public function deleteSetting(int $companyId, AlertLevelEnum $alertLevel): void
    {
        $this->alertSetting->query()
            ->where('company_id', $companyId)
            ->where('alert_level_id', $alertLevel->value)
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMessage(int $companyId, AlertLevelEnum $alertLevel): void
    {
        $this->alertMessage->query()
            ->where('company_id', $companyId)
            ->where('alert_level_id', $alertLevel->value)
            ->delete();
    }
}

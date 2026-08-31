<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\AlertLevelEnum;
use App\Models\CompanyAlertMessage;
use App\Models\CompanyLaborAlertSetting;
use Illuminate\Database\Eloquent\Collection;

/**
 * 労務アラート設定リポジトリインターフェース
 */
interface CompanyLaborAlertSettingRepositoryInterface
{
    /**
     * 会社の労務アラート設定一覧を取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, CompanyLaborAlertSetting>
     */
    public function findSettingsByCompanyId(int $companyId): Collection;

    /**
     * 会社のアラートメッセージ一覧を取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, CompanyAlertMessage>
     */
    public function findMessagesByCompanyId(int $companyId): Collection;

    /**
     * 特定レベルのアラート設定を取得
     *
     * @param  int  $companyId  会社ID
     * @param  AlertLevelEnum  $alertLevel  アラートレベル
     */
    public function findSettingByLevel(int $companyId, AlertLevelEnum $alertLevel): ?CompanyLaborAlertSetting;

    /**
     * 特定レベルのアラートメッセージを取得
     *
     * @param  int  $companyId  会社ID
     * @param  AlertLevelEnum  $alertLevel  アラートレベル
     */
    public function findMessageByLevel(int $companyId, AlertLevelEnum $alertLevel): ?CompanyAlertMessage;

    /**
     * アラート設定を保存または更新
     *
     * @param  int  $companyId  会社ID
     * @param  AlertLevelEnum  $alertLevel  アラートレベル
     * @param  array<string, mixed>  $attributes  属性
     */
    public function saveSetting(int $companyId, AlertLevelEnum $alertLevel, array $attributes): CompanyLaborAlertSetting;

    /**
     * アラートメッセージを保存または更新
     *
     * @param  int  $companyId  会社ID
     * @param  AlertLevelEnum  $alertLevel  アラートレベル
     * @param  array<string, mixed>  $attributes  属性
     */
    public function saveMessage(int $companyId, AlertLevelEnum $alertLevel, array $attributes): CompanyAlertMessage;

    /**
     * アラート設定を削除
     *
     * @param  int  $companyId  会社ID
     * @param  AlertLevelEnum  $alertLevel  アラートレベル
     */
    public function deleteSetting(int $companyId, AlertLevelEnum $alertLevel): void;

    /**
     * アラートメッセージを削除
     *
     * @param  int  $companyId  会社ID
     * @param  AlertLevelEnum  $alertLevel  アラートレベル
     */
    public function deleteMessage(int $companyId, AlertLevelEnum $alertLevel): void;
}

<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\AlertLevelEnum;
use App\Models\CompanyAlertMessage;
use App\Models\CompanyLaborAlertSetting;
use App\Models\DailyWorkSummary;
use App\Models\LaborAlertHistory;
use Illuminate\Database\Eloquent\Collection;

/**
 * 労務アラートリポジトリインターフェース
 *
 * 労務アラートの検出に必要なデータアクセスを提供
 */
interface LaborAlertRepositoryInterface
{
    // ========================================
    // 設定関連
    // ========================================

    /**
     * 会社の労務アラート設定を取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, CompanyLaborAlertSetting>
     */
    public function findAlertSettingsByCompanyId(int $companyId): Collection;

    /**
     * 会社のアラートメッセージ設定を取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, CompanyAlertMessage>
     */
    public function findAlertMessagesByCompanyId(int $companyId): Collection;

    /**
     * 特定レベルのアラート設定を取得
     *
     * @param  int  $companyId  会社ID
     * @param  AlertLevelEnum  $alertLevel  アラートレベル
     */
    public function findAlertSettingByLevel(int $companyId, AlertLevelEnum $alertLevel): ?CompanyLaborAlertSetting;

    /**
     * 特定レベルのアラートメッセージを取得
     *
     * @param  int  $companyId  会社ID
     * @param  AlertLevelEnum  $alertLevel  アラートレベル
     */
    public function findAlertMessageByLevel(int $companyId, AlertLevelEnum $alertLevel): ?CompanyAlertMessage;

    // ========================================
    // アラート検出関連
    // ========================================

    /**
     * 指定月の残業時間が閾値以上のユーザーを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $year  年
     * @param  int  $month  月
     * @param  int  $thresholdMinutes  閾値（分）
     * @return Collection<int, DailyWorkSummary>
     */
    public function findUsersExceedingOvertimeThreshold(int $companyId, int $year, int $month, int $thresholdMinutes): Collection;

    /**
     * 指定月の全ユーザーの勤務実績サマリーを取得（日次実績から集計）
     *
     * @param  int  $companyId  会社ID
     * @param  int  $year  年
     * @param  int  $month  月
     * @return Collection<int, DailyWorkSummary>
     */
    public function findMonthlyWorkSummaries(int $companyId, int $year, int $month): Collection;

    // ========================================
    // 履歴関連
    // ========================================

    /**
     * アラート履歴を保存または更新
     *
     * @param  array<string, mixed>  $attributes  属性
     */
    public function saveAlertHistory(array $attributes): LaborAlertHistory;

    /**
     * 会社の未読アラート履歴を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $limit  取得件数上限
     * @return Collection<int, LaborAlertHistory>
     */
    public function findUnreadAlertHistories(int $companyId, ?int $limit = null): Collection;

    /**
     * 指定月のアラート履歴を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $year  年
     * @param  int  $month  月
     * @return Collection<int, LaborAlertHistory>
     */
    public function findAlertHistoriesByMonth(int $companyId, int $year, int $month): Collection;

    /**
     * ユーザーの指定月のアラート履歴を取得
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $year  年
     * @param  int  $month  月
     * @param  AlertLevelEnum|null  $alertLevel  アラートレベル（指定時は絞り込み）
     */
    public function findUserAlertHistory(int $userId, int $year, int $month, ?AlertLevelEnum $alertLevel = null): ?LaborAlertHistory;

    /**
     * アラート履歴を既読にする
     *
     * @param  int  $historyId  履歴ID
     */
    public function markAlertHistoryAsRead(int $historyId): void;

    /**
     * 会社の全未読アラートを既読にする
     *
     * @param  int  $companyId  会社ID
     */
    public function markAllAlertHistoriesAsRead(int $companyId): void;
}

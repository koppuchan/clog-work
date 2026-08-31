<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertLevelEnum;
use App\Models\CompanyAlertMessage;
use App\Models\CompanyLaborAlertSetting;
use App\Repositories\Contracts\LaborAlertRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * 労務アラートサービス
 *
 * 労働基準法や会社規定に基づく労務アラートの検出と生成を行う
 */
class LaborAlertService
{
    /**
     * デフォルト設定値
     */
    private const DEFAULT_CAUTION_HOURS = 45;

    private const DEFAULT_WARNING_HOURS = 80;

    /**
     * デフォルトメッセージ
     */

    // TOdo: Enum化しても良いかも（会社ごとにカスタマイズできるようにするため、DBで管理する形にしているが、デフォルト値はコード上で管理）
    private const DEFAULT_CAUTION_TITLE = '【注意】残業時間が多くなっています';

    private const DEFAULT_CAUTION_MESSAGE = '今月の残業時間が{hours}時間に達しています。体調管理に注意してください。';

    private const DEFAULT_WARNING_TITLE = '【警告】残業時間超過';

    private const DEFAULT_WARNING_MESSAGE = '今月の残業時間が{hours}時間に達しています。残業時間が非常に多くなっています。健康管理に十分注意してください。';

    public function __construct(
        private readonly LaborAlertRepositoryInterface $laborAlertRepository
    ) {}

    /**
     * 指定月の労務アラートを取得（DB設定に基づく）
     *
     * @param  int  $companyId  会社ID
     * @param  int  $year  年
     * @param  int  $month  月
     * @return Collection<int, array{
     *   id: int|null,
     *   userId: int,
     *   userName: string,
     *   alertLevel: string,
     *   alertLevelLabel: string,
     *   title: string,
     *   message: string,
     *   value: int,
     *   threshold: int,
     *   isRead: bool,
     *   date: string
     * }>
     */
    public function getAlerts(int $companyId, int $year, int $month): Collection
    {
        // 設定を取得（なければデフォルト値を使用）
        $settings = $this->getSettingsWithDefaults($companyId);
        $messages = $this->getMessagesWithDefaults($companyId);

        // 履歴から既存のアラートを取得
        $existingHistories = $this->laborAlertRepository
            ->findAlertHistoriesByMonth($companyId, $year, $month)
            ->keyBy(fn ($h) => $h->user_id.'-'.$h->alert_level_id->value);

        $alerts = collect();

        // 警告レベル（上限）のアラートを検出
        if (isset($settings[AlertLevelEnum::WARNING->value])) {
            $warningSetting = $settings[AlertLevelEnum::WARNING->value];
            $warningMessage = $messages[AlertLevelEnum::WARNING->value] ?? null;

            $warningAlerts = $this->detectOvertimeAlerts(
                $companyId,
                $year,
                $month,
                $warningSetting,
                $warningMessage,
                AlertLevelEnum::WARNING,
                $existingHistories
            );
            $alerts = $alerts->merge($warningAlerts);
        }

        // 注意レベル（通知）のアラートを検出（警告対象者を除く）
        if (isset($settings[AlertLevelEnum::CAUTION->value])) {
            $cautionSetting = $settings[AlertLevelEnum::CAUTION->value];
            $cautionMessage = $messages[AlertLevelEnum::CAUTION->value] ?? null;
            $warningUserIds = $alerts->pluck('userId')->toArray();

            $cautionAlerts = $this->detectOvertimeAlerts(
                $companyId,
                $year,
                $month,
                $cautionSetting,
                $cautionMessage,
                AlertLevelEnum::CAUTION,
                $existingHistories,
                $warningUserIds
            );
            $alerts = $alerts->merge($cautionAlerts);
        }

        return $alerts->values();
    }

    /**
     * 残業時間アラートを検出
     *
     * @param  array<int>  $excludeUserIds  除外するユーザーID
     */
    private function detectOvertimeAlerts(
        int $companyId,
        int $year,
        int $month,
        CompanyLaborAlertSetting $setting,
        ?CompanyAlertMessage $messageConfig,
        AlertLevelEnum $alertLevel,
        Collection $existingHistories,
        array $excludeUserIds = []
    ): Collection {
        $thresholdMinutes = $setting->overtime_threshold_minutes;

        $summaries = $this->laborAlertRepository->findUsersExceedingOvertimeThreshold(
            $companyId,
            $year,
            $month,
            $thresholdMinutes
        );

        if (! empty($excludeUserIds)) {
            $summaries = $summaries->whereNotIn('user_id', $excludeUserIds);
        }

        $dateString = sprintf('%04d-%02d', $year, $month);

        return $summaries->map(function ($summary) use ($companyId, $year, $month, $setting, $messageConfig, $alertLevel, $existingHistories, $dateString) {
            $overtimeHours = (int) round($summary->overtime_minutes / 60);
            $thresholdHours = $setting->overtime_threshold_hours;

            // プレースホルダを展開
            $replacements = [
                'hours' => $overtimeHours,
                'threshold' => $thresholdHours,
                'user_name' => $summary->user->name ?? '不明',
            ];

            $defaultTitle = $alertLevel === AlertLevelEnum::WARNING ? self::DEFAULT_WARNING_TITLE : self::DEFAULT_CAUTION_TITLE;
            $defaultMessage = $alertLevel === AlertLevelEnum::WARNING ? self::DEFAULT_WARNING_MESSAGE : self::DEFAULT_CAUTION_MESSAGE;

            $title = $messageConfig?->formatTitle($replacements)
                ?? $this->replacePlaceholders($defaultTitle, $replacements);
            $message = $messageConfig?->formatMessage($replacements)
                ?? $this->replacePlaceholders($defaultMessage, $replacements);

            // 既存の履歴を確認
            $historyKey = $summary->user_id.'-'.$alertLevel->value;
            $existingHistory = $existingHistories->get($historyKey);

            // 履歴を保存または更新
            $history = $this->laborAlertRepository->saveAlertHistory([
                'company_id' => $companyId,
                'user_id' => $summary->user_id,
                'alert_level_id' => $alertLevel->value,
                'target_year' => $year,
                'target_month' => $month,
                'alert_value' => $overtimeHours,
                'threshold_value' => $thresholdHours,
                'title' => $title,
                'message' => $message,
                'is_read' => $existingHistory?->is_read ?? false,
                'read_at' => $existingHistory?->read_at,
            ]);

            return [
                'id' => $history->id,
                'userId' => $summary->user_id,
                'userName' => $summary->user->name ?? '不明',
                'alertLevel' => $alertLevel->code(),
                'alertLevelLabel' => $alertLevel->label(),
                'title' => $title,
                'message' => $message,
                'value' => $overtimeHours,
                'threshold' => $thresholdHours,
                'isRead' => $history->is_read,
                'date' => $dateString,
            ];
        });
    }

    /**
     * プレースホルダを置換
     *
     * @param  array<string, string|int>  $replacements
     */
    private function replacePlaceholders(string $template, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * 会社のアラート設定を取得（デフォルト値込み）
     *
     * @return array<int, CompanyLaborAlertSetting>
     */
    private function getSettingsWithDefaults(int $companyId): array
    {
        $settings = $this->laborAlertRepository
            ->findAlertSettingsByCompanyId($companyId)
            ->keyBy(fn ($s) => $s->alert_level_id instanceof AlertLevelEnum ? $s->alert_level_id->value : $s->alert_level_id);

        // デフォルト設定を追加
        if (! $settings->has(AlertLevelEnum::CAUTION->value)) {
            $settings->put(AlertLevelEnum::CAUTION->value, $this->createDefaultSetting($companyId, AlertLevelEnum::CAUTION, self::DEFAULT_CAUTION_HOURS));
        }

        if (! $settings->has(AlertLevelEnum::WARNING->value)) {
            $settings->put(AlertLevelEnum::WARNING->value, $this->createDefaultSetting($companyId, AlertLevelEnum::WARNING, self::DEFAULT_WARNING_HOURS));
        }

        return $settings->all();
    }

    /**
     * 会社のアラートメッセージを取得（デフォルト値込み）
     *
     * @return array<int, CompanyAlertMessage|null>
     */
    private function getMessagesWithDefaults(int $companyId): array
    {
        $messages = $this->laborAlertRepository
            ->findAlertMessagesByCompanyId($companyId)
            ->keyBy(fn ($m) => $m->alert_level_id instanceof AlertLevelEnum ? $m->alert_level_id->value : $m->alert_level_id);

        return [
            AlertLevelEnum::CAUTION->value => $messages->get(AlertLevelEnum::CAUTION->value),
            AlertLevelEnum::WARNING->value => $messages->get(AlertLevelEnum::WARNING->value),
        ];
    }

    /**
     * デフォルト設定オブジェクトを作成
     */
    private function createDefaultSetting(int $companyId, AlertLevelEnum $level, int $thresholdHours): CompanyLaborAlertSetting
    {
        $setting = new CompanyLaborAlertSetting;
        $setting->company_id = $companyId;
        $setting->alert_level_id = $level;
        $setting->overtime_threshold_hours = $thresholdHours;
        $setting->is_enabled = true;

        return $setting;
    }

    /**
     * アラートサマリーを取得
     *
     * @param  Collection<int, array<string, mixed>>  $alerts  アラート一覧
     * @return array{total: int, warning: int, caution: int, unread: int}
     */
    public function getAlertSummary(Collection $alerts): array
    {
        return [
            'total' => $alerts->count(),
            'warning' => $alerts->filter(fn (array $alert) => $alert['alertLevel'] === 'warning')->count(),
            'caution' => $alerts->filter(fn (array $alert) => $alert['alertLevel'] === 'caution')->count(),
            'unread' => $alerts->filter(fn (array $alert) => ! $alert['isRead'])->count(),
        ];
    }

    /**
     * アラートを既読にする
     */
    public function markAsRead(int $historyId): void
    {
        $this->laborAlertRepository->markAlertHistoryAsRead($historyId);
    }

    /**
     * 会社の全アラートを既読にする
     */
    public function markAllAsRead(int $companyId): void
    {
        $this->laborAlertRepository->markAllAlertHistoriesAsRead($companyId);
    }
}

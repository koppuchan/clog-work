<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertLevelEnum;
use App\Repositories\Contracts\CompanyLaborAlertSettingRepositoryInterface;

/**
 * 労務アラート設定サービス
 *
 * 設定画面での労務アラート設定の取得・更新を行う
 */
class CompanyLaborAlertSettingService
{
    /**
     * デフォルト設定値
     */
    private const DEFAULT_CAUTION_HOURS = 45;

    private const DEFAULT_WARNING_HOURS = 80;

    /**
     * デフォルトメッセージ
     */
    private const DEFAULT_CAUTION_TITLE = '【注意】残業時間が多くなっています';

    private const DEFAULT_CAUTION_MESSAGE = '今月の残業時間が{hours}時間に達しています。体調管理に注意してください。';

    private const DEFAULT_WARNING_TITLE = '【警告】残業時間超過';

    private const DEFAULT_WARNING_MESSAGE = '今月の残業時間が{hours}時間に達しています。残業時間が非常に多くなっています。健康管理に十分注意してください。';

    public function __construct(
        private readonly CompanyLaborAlertSettingRepositoryInterface $repository
    ) {}

    /**
     * 労務アラート設定を取得（フロントエンド用にフォーマット）
     *
     * @param  int  $companyId  会社ID
     * @return array{
     *   overtimeNotification: int,
     *   overtimeLimit: int,
     *   messages: array{
     *     warning: array{title: string, message: string},
     *     caution: array{title: string, message: string}
     *   }
     * }
     */
    public function getSettings(int $companyId): array
    {
        $settings = $this->repository->findSettingsByCompanyId($companyId)
            ->keyBy(fn ($s) => $s->alert_level_id instanceof AlertLevelEnum
                ? $s->alert_level_id->value
                : $s->alert_level_id);

        $messages = $this->repository->findMessagesByCompanyId($companyId)
            ->keyBy(fn ($m) => $m->alert_level_id instanceof AlertLevelEnum
                ? $m->alert_level_id->value
                : $m->alert_level_id);

        // 注意レベル設定
        $cautionSetting = $settings->get(AlertLevelEnum::CAUTION->value);
        $cautionMessage = $messages->get(AlertLevelEnum::CAUTION->value);

        // 警告レベル設定
        $warningSetting = $settings->get(AlertLevelEnum::WARNING->value);
        $warningMessage = $messages->get(AlertLevelEnum::WARNING->value);

        return [
            'overtimeNotification' => $cautionSetting?->overtime_threshold_hours ?? self::DEFAULT_CAUTION_HOURS,
            'overtimeLimit' => $warningSetting?->overtime_threshold_hours ?? self::DEFAULT_WARNING_HOURS,
            'messages' => [
                'warning' => [
                    'title' => $warningMessage?->title ?? self::DEFAULT_WARNING_TITLE,
                    'message' => $warningMessage?->message ?? self::DEFAULT_WARNING_MESSAGE,
                ],
                'caution' => [
                    'title' => $cautionMessage?->title ?? self::DEFAULT_CAUTION_TITLE,
                    'message' => $cautionMessage?->message ?? self::DEFAULT_CAUTION_MESSAGE,
                ],
            ],
        ];
    }

    /**
     * 労務アラート設定を更新
     *
     * @param  int  $companyId  会社ID
     * @param  array{
     *   overtimeNotification?: int,
     *   overtimeLimit?: int,
     *   messages?: array{
     *     warning?: array{title?: string, message?: string},
     *     caution?: array{title?: string, message?: string}
     *   }
     * }  $data  更新データ
     */
    public function updateSettings(int $companyId, array $data): void
    {
        // 注意レベル（通知）設定の更新
        if (isset($data['overtimeNotification'])) {
            $this->repository->saveSetting($companyId, AlertLevelEnum::CAUTION, [
                'overtime_threshold_hours' => (int) $data['overtimeNotification'],
                'is_enabled' => true,
            ]);
        }

        // 警告レベル（上限）設定の更新
        if (isset($data['overtimeLimit'])) {
            $this->repository->saveSetting($companyId, AlertLevelEnum::WARNING, [
                'overtime_threshold_hours' => (int) $data['overtimeLimit'],
                'is_enabled' => true,
            ]);
        }

        // メッセージの更新
        if (isset($data['messages'])) {
            // 注意メッセージ
            if (isset($data['messages']['caution'])) {
                $cautionMessage = $data['messages']['caution'];
                $this->repository->saveMessage($companyId, AlertLevelEnum::CAUTION, [
                    'title' => $cautionMessage['title'] ?? self::DEFAULT_CAUTION_TITLE,
                    'message' => $cautionMessage['message'] ?? self::DEFAULT_CAUTION_MESSAGE,
                ]);
            }

            // 警告メッセージ
            if (isset($data['messages']['warning'])) {
                $warningMessage = $data['messages']['warning'];
                $this->repository->saveMessage($companyId, AlertLevelEnum::WARNING, [
                    'title' => $warningMessage['title'] ?? self::DEFAULT_WARNING_TITLE,
                    'message' => $warningMessage['message'] ?? self::DEFAULT_WARNING_MESSAGE,
                ]);
            }
        }
    }

    /**
     * デフォルト設定を取得
     *
     * @return array{
     *   overtimeNotification: int,
     *   overtimeLimit: int,
     *   messages: array{
     *     warning: array{title: string, message: string},
     *     caution: array{title: string, message: string}
     *   }
     * }
     */
    public function getDefaultSettings(): array
    {
        return [
            'overtimeNotification' => self::DEFAULT_CAUTION_HOURS,
            'overtimeLimit' => self::DEFAULT_WARNING_HOURS,
            'messages' => [
                'warning' => [
                    'title' => self::DEFAULT_WARNING_TITLE,
                    'message' => self::DEFAULT_WARNING_MESSAGE,
                ],
                'caution' => [
                    'title' => self::DEFAULT_CAUTION_TITLE,
                    'message' => self::DEFAULT_CAUTION_MESSAGE,
                ],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertLevelEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 会社ごとのアラートメッセージ設定
 *
 * プレースホルダを含むカスタムメッセージを管理
 * 使用可能なプレースホルダ: {hours}, {threshold}, {user_name}
 */
class CompanyAlertMessage extends Model
{
    /**
     * テーブル名
     */
    protected $table = 'company_alert_messages';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'company_id',
        'alert_level_id',
        'title',
        'message',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'alert_level_id' => AlertLevelEnum::class,
        ];
    }

    /**
     * 所属する会社を取得
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * アラートレベルを取得
     */
    public function alertLevel(): BelongsTo
    {
        return $this->belongsTo(AlertLevel::class, 'alert_level_id');
    }

    /**
     * プレースホルダを展開したメッセージを取得
     *
     * @param  array<string, string|int>  $replacements  置換マップ（例: ['hours' => 50, 'threshold' => 45]）
     */
    public function formatMessage(array $replacements): string
    {
        $message = $this->message;

        foreach ($replacements as $key => $value) {
            $message = str_replace('{'.$key.'}', (string) $value, $message);
        }

        return $message;
    }

    /**
     * プレースホルダを展開したタイトルを取得
     *
     * @param  array<string, string|int>  $replacements  置換マップ
     */
    public function formatTitle(array $replacements): string
    {
        $title = $this->title;

        foreach ($replacements as $key => $value) {
            $title = str_replace('{'.$key.'}', (string) $value, $title);
        }

        return $title;
    }
}

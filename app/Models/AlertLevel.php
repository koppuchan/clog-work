<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertLevelEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * アラートレベルマスター
 *
 * 労務アラートの重要度レベル（注意・警告）を管理
 */
class AlertLevel extends Model
{
    /**
     * テーブル名
     */
    protected $table = 'alert_levels';

    /**
     * プライマリキーの自動増分を無効化
     */
    public $incrementing = false;

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'id',
        'code',
        'name',
        'display_order',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'display_order' => 'integer',
        ];
    }

    /**
     * アラート設定を取得
     */
    public function laborAlertSettings(): HasMany
    {
        return $this->hasMany(CompanyLaborAlertSetting::class, 'alert_level_id');
    }

    /**
     * アラートメッセージを取得
     */
    public function alertMessages(): HasMany
    {
        return $this->hasMany(CompanyAlertMessage::class, 'alert_level_id');
    }

    /**
     * アラート履歴を取得
     */
    public function alertHistories(): HasMany
    {
        return $this->hasMany(LaborAlertHistory::class, 'alert_level_id');
    }

    /**
     * Enumを取得
     */
    public function toEnum(): ?AlertLevelEnum
    {
        return AlertLevelEnum::tryFrom($this->id);
    }
}

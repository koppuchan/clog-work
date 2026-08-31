<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertLevelEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 会社ごとの労務アラート閾値設定
 *
 * 残業時間等の閾値を会社・アラートレベルごとに管理
 */
class CompanyLaborAlertSetting extends Model
{
    /**
     * テーブル名
     */
    protected $table = 'company_labor_alert_settings';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'company_id',
        'alert_level_id',
        'overtime_threshold_hours',
        'is_enabled',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'alert_level_id' => AlertLevelEnum::class,
            'overtime_threshold_hours' => 'integer',
            'is_enabled' => 'boolean',
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
     * 閾値を分に変換して取得
     */
    public function getOvertimeThresholdMinutesAttribute(): int
    {
        return $this->overtime_threshold_hours * 60;
    }
}

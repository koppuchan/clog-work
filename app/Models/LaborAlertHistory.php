<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertLevelEnum;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 労務アラート履歴
 *
 * 発生したアラートの記録を管理
 * ユーザーごと・月ごとにアラートの履歴と既読状態を保持
 */
class LaborAlertHistory extends Model
{
    /**
     * テーブル名
     */
    protected $table = 'labor_alert_histories';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'alert_level_id',
        'target_year',
        'target_month',
        'alert_value',
        'threshold_value',
        'title',
        'message',
        'is_read',
        'read_at',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'alert_level_id' => AlertLevelEnum::class,
            'target_year' => 'integer',
            'target_month' => 'integer',
            'alert_value' => 'integer',
            'threshold_value' => 'integer',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
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
     * 対象ユーザーを取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * アラートレベルを取得
     */
    public function alertLevel(): BelongsTo
    {
        return $this->belongsTo(AlertLevel::class, 'alert_level_id');
    }

    /**
     * 既読にする
     */
    public function markAsRead(): void
    {
        $this->is_read = true;
        $this->read_at = CarbonImmutable::now();
        $this->save();
    }

    /**
     * 対象年月の文字列を取得
     */
    public function getTargetYearMonthAttribute(): string
    {
        return sprintf('%04d-%02d', $this->target_year, $this->target_month);
    }

    /**
     * 対象年月の表示用文字列を取得
     */
    public function getTargetYearMonthLabelAttribute(): string
    {
        return sprintf('%d年%d月', $this->target_year, $this->target_month);
    }
}

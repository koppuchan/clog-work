<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 日次勤務実績
 *
 * 従業員の1日ごとの勤務実績を集計・管理するテーブル
 * record_source: 1=自動集計, 2=手動修正, 3=申請修正
 */
class DailyWorkSummary extends Model
{
    use HasFactory;

    /**
     * テーブル名
     */
    protected $table = 'daily_work_summaries';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'work_date',
        'scheduled_start_time',
        'scheduled_end_time',
        'work_start',
        'work_end',
        'work_minutes',
        'break_minutes',
        'net_work_minutes',
        'night_minutes',
        'holiday_minutes',
        'overtime_minutes',
        'late_minutes',
        'early_leave_minutes',
        'is_cross_day',
        'record_source',
        'note',
        'leave_type',
        'leave_minutes',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'work_date' => 'date',
            'scheduled_start_time' => 'string',
            'scheduled_end_time' => 'string',
            'work_start' => 'datetime',
            'work_end' => 'datetime',
            'work_minutes' => 'integer',
            'break_minutes' => 'integer',
            'net_work_minutes' => 'integer',
            'night_minutes' => 'integer',
            'holiday_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'is_cross_day' => 'boolean',
            'record_source' => RecordSourceEnum::class,
            'note' => 'string',
            'leave_type' => LeaveTypeEnum::class,
            'leave_minutes' => 'integer',
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
}

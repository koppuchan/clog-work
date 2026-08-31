<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 打刻レコード
 *
 * 従業員の勤務開始、終了、休憩開始・終了などの打刻記録を管理するテーブル
 * record_type: 1=勤務開始, 2=勤務終了, 3=日付越え終了, 4=休憩開始, 5=休憩終了
 * record_source: 1=自動打刻, 2=手動入力, 3=申請修正
 */
class TimeRecord extends Model
{
    use HasFactory;

    /**
     * テーブル名
     */
    protected $table = 'time_records';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'record_type',
        'record_time',
        'rounded_time',
        'record_source',
        'note',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'record_type' => TimeRecordTypeEnum::class,
            'record_time' => 'datetime',
            'rounded_time' => 'datetime',
            'record_source' => RecordSourceEnum::class,
            'note' => 'string',
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
     * 打刻したユーザーを取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この打刻レコードに対する修正申請明細一覧を取得
     */
    public function correctionRequestDetails(): HasMany
    {
        return $this->hasMany(TimeRecordCorrectionRequestDetail::class);
    }

    /**
     * この打刻レコードに対する修正履歴一覧を取得
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(TimeRecordCorrection::class);
    }
}

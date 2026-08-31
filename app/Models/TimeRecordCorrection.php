<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 打刻修正履歴
 *
 * 承認された打刻修正の実行履歴を記録するテーブル
 */
class TimeRecordCorrection extends Model
{
    use HasFactory;

    /**
     * テーブル名
     */
    protected $table = 'time_record_corrections';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'correction_request_detail_id',
        'time_record_id',
        'record_type',
        'before_record_time',
        'before_rounded_time',
        'before_record_source',
        'after_record_time',
        'after_rounded_time',
        'after_record_source',
        'corrected_by',
        'correction_note',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'correction_request_detail_id' => 'integer',
            'time_record_id' => 'integer',
            'record_type' => TimeRecordTypeEnum::class,
            'before_record_time' => 'datetime',
            'before_rounded_time' => 'datetime',
            'before_record_source' => RecordSourceEnum::class,
            'after_record_time' => 'datetime',
            'after_rounded_time' => 'datetime',
            'after_record_source' => RecordSourceEnum::class,
            'corrected_by' => 'integer',
            'correction_note' => 'string',
        ];
    }

    /**
     * 所属する打刻修正申請明細を取得
     */
    public function correctionRequestDetail(): BelongsTo
    {
        return $this->belongsTo(TimeRecordCorrectionRequestDetail::class, 'correction_request_detail_id');
    }

    /**
     * 修正対象の打刻レコードを取得
     */
    public function timeRecord(): BelongsTo
    {
        return $this->belongsTo(TimeRecord::class);
    }

    /**
     * 修正実行者を取得
     */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}

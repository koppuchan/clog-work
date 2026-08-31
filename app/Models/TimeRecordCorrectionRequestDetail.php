<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TimeRecordTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 打刻修正申請明細
 *
 * 打刻修正申請の個々の修正内容を管理するテーブル
 * record_type: 1=勤務開始, 2=勤務終了, 3=日付越え終了, 4=休憩開始, 5=休憩終了
 */
class TimeRecordCorrectionRequestDetail extends Model
{
    use HasFactory;

    /**
     * テーブル名
     */
    protected $table = 'time_record_correction_request_details';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'correction_request_id',
        'time_record_id',
        'record_type',
        'original_record_time',
        'original_rounded_time',
        'corrected_record_time',
        'corrected_rounded_time',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'correction_request_id' => 'integer',
            'time_record_id' => 'integer',
            'record_type' => TimeRecordTypeEnum::class,
            'original_record_time' => 'datetime',
            'original_rounded_time' => 'datetime',
            'corrected_record_time' => 'datetime',
            'corrected_rounded_time' => 'datetime',
        ];
    }

    /**
     * 所属する打刻修正申請を取得
     */
    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(TimeRecordCorrectionRequest::class, 'correction_request_id');
    }

    /**
     * 修正対象の打刻レコードを取得
     */
    public function timeRecord(): BelongsTo
    {
        return $this->belongsTo(TimeRecord::class);
    }

    /**
     * この明細に対応する修正履歴一覧を取得
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(TimeRecordCorrection::class, 'correction_request_detail_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 打刻修正申請
 *
 * 従業員からの打刻修正申請を管理するテーブル
 */
class TimeRecordCorrectionRequest extends Model
{
    use HasFactory;

    /**
     * テーブル名
     */
    protected $table = 'time_record_correction_requests';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'target_date',
        'reason',
        'status',
        'approver_id',
        'approved_at',
        'rejection_reason',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'target_date' => 'date',
            'reason' => 'string',
            'status' => RequestStatusEnum::class,
            'approver_id' => 'integer',
            'approved_at' => 'datetime',
            'rejection_reason' => 'string',
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
     * 申請者を取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 承認者を取得
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * 承認ステータスを取得
     */
    public function approvalStatus(): BelongsTo
    {
        return $this->belongsTo(ApprovalStatus::class, 'status', 'id');
    }

    /**
     * 打刻修正申請の明細一覧を取得
     */
    public function details(): HasMany
    {
        return $this->hasMany(TimeRecordCorrectionRequestDetail::class, 'correction_request_id');
    }
}

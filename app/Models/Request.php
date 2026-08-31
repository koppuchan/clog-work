<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 各種申請
 *
 * 休暇、遅刻、早退、欠席などの各種申請を管理するテーブル
 */
class Request extends Model
{
    use HasFactory;

    /**
     * テーブル名
     */
    protected $table = 'requests';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'company_id',
        'requested_by',
        'type',
        'target_date',
        'start_time',
        'end_time',
        'reason',
        'status',
        'approver_id',
        'decided_at',
        'decision_note',
        'applied_at',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'requested_by' => 'integer',
            'type' => 'integer',
            'target_date' => 'date',
            'start_time' => 'string',
            'end_time' => 'string',
            'reason' => 'string',
            'status' => RequestStatusEnum::class,
            'approver_id' => 'integer',
            'decided_at' => 'datetime',
            'decision_note' => 'string',
            'applied_at' => 'datetime',
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
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
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
     * 申請タイプを取得
     */
    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class, 'type');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 承認ステータスマスター
 *
 * 申請の承認状態を表すマスターテーブル
 * 1=承認待ち, 2=承認済み, 3=却下
 */
class ApprovalStatus extends Model
{
    use HasFactory;

    /**
     * テーブル名
     */
    protected $table = 'approval_statuses';

    /**
     * 主キーのデータ型
     */
    protected $keyType = 'int';

    /**
     * 主キーの自動増分を無効化
     */
    public $incrementing = false;

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'id',
        'name',
    ];

    /**
     * 属性のキャスト
     */
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
    ];

    /**
     * このステータスを持つ申請一覧を取得
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'status', 'id');
    }

    /**
     * このステータスを持つ打刻修正申請一覧を取得
     */
    public function timeRecordCorrectionRequests(): HasMany
    {
        return $this->hasMany(TimeRecordCorrectionRequest::class, 'status', 'id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FeliCa打刻試行ログ
 *
 * 打刻専用画面（ブラウザ）にFeliCaカードでの打刻結果をリアルタイムで表示するため、
 * 常駐アプリからの打刻試行を成功・失敗問わず記録する。
 * ブラウザ側はこのテーブルをポーリングして新しい試行を検知し、トースト表示する。
 */
class FelicaStampAttempt extends Model
{
    use HasFactory;

    /**
     * updated_at は使用しない（追記のみのログのため）
     */
    const UPDATED_AT = null;

    /**
     * テーブル名
     */
    protected $table = 'felica_stamp_attempts';

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'felica_idm',
        'status',
        'message',
        'detail',
        'time_record_id',
    ];

    /**
     * 属性のキャスト
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'time_record_id' => 'integer',
            'created_at' => 'datetime',
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
     * カード番号をマスクして表示用に整形する（例: 012e****7b5f）
     */
    public function maskedIdm(): string
    {
        return substr($this->felica_idm, 0, 4).'****'.substr($this->felica_idm, -4);
    }
}

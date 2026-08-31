<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ユーザー勤務可能曜日
 *
 * @property int $id
 * @property int $user_id ユーザーID
 * @property int $weekday_id 曜日ID
 * @property bool $is_available 勤務可能フラグ
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Weekday $weekday
 */
class UserAvailableWorkDay extends Model
{
    /**
     * テーブル名
     *
     * @var string
     */
    protected $table = 'user_available_work_days';

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'weekday_id',
        'is_available',
    ];

    /**
     * キャストする属性
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'weekday_id' => 'integer',
            'is_available' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * ユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 曜日
     */
    public function weekday(): BelongsTo
    {
        return $this->belongsTo(Weekday::class);
    }
}

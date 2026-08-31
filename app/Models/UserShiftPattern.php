<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserShiftPattern extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'weekday_id',
        'shift_pattern_id',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'weekday_id' => 'integer',
        'shift_pattern_id' => 'integer',
    ];

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

    /**
     * シフトパターン
     */
    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }
}

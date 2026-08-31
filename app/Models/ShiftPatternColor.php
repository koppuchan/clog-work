<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftPatternColor extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'shift_pattern_id',
        'shift_color_id',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'shift_pattern_id' => 'integer',
        'shift_color_id' => 'integer',
    ];

    /**
     * シフトパターン
     */
    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    /**
     * シフトカラー
     */
    public function shiftColor(): BelongsTo
    {
        return $this->belongsTo(ShiftColor::class);
    }
}

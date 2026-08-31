<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShiftColor extends Model
{
    protected $fillable = [
        'background_color',
        'text_color',
    ];

    /**
     * シフトパターンとの関連
     */
    public function shiftPatterns(): BelongsToMany
    {
        return $this->belongsToMany(ShiftPattern::class, 'shift_pattern_colors', 'shift_color_id', 'shift_pattern_id');
    }
}

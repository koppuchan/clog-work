<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShiftPattern extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'work_minutes',
        'break_mode',
        'break_minutes',
        'break_start',
        'break_end',
        'auto_fill_break',
    ];

    protected $casts = [
        'work_minutes' => 'integer',
        'break_mode' => 'integer',
        'break_minutes' => 'integer',
        'auto_fill_break' => 'boolean',
    ];

    /**
     * 会社との関連
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * シフトカラーとの関連
     */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(ShiftColor::class, 'shift_pattern_colors', 'shift_pattern_id', 'shift_color_id');
    }
}

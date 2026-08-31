<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'shift_date',
        'shift_pattern_id',
        'note',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'company_id' => 'integer',
        'user_id' => 'integer',
        'shift_date' => 'date',
        'shift_pattern_id' => 'integer',
    ];

    /**
     * 所属する会社
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * 所属するユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * シフトパターン
     */
    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }
}

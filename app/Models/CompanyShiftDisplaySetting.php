<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyShiftDisplaySetting extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'company_id',
        'shift_period_id',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'company_id' => 'integer',
        'shift_period_id' => 'integer',
    ];

    /**
     * 会社
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * シフト表示期間
     */
    public function shiftPeriod(): BelongsTo
    {
        return $this->belongsTo(ShiftPeriod::class);
    }
}

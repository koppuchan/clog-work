<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyShiftRoundingSettingHistory extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'company_id',
        'rounding_unit_id',
        'changed_at',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'company_id' => 'integer',
        'rounding_unit_id' => 'integer',
        'changed_at' => 'datetime',
    ];

    /**
     * 会社
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * 丸め単位
     */
    public function roundingUnit(): BelongsTo
    {
        return $this->belongsTo(ShiftRoundingUnit::class, 'rounding_unit_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyShiftRoundingSetting extends Model
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
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'company_id' => 'integer',
        'rounding_unit_id' => 'integer',
    ];

    /**
     * 所属する会社
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * シフト丸め単位マスター
     */
    public function roundingUnit(): BelongsTo
    {
        return $this->belongsTo(ShiftRoundingUnit::class);
    }
}

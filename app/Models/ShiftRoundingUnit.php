<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftRoundingUnit extends Model
{
    use HasFactory;

    /**
     * タイムスタンプを使用しない
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'minutes',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'minutes' => 'integer',
    ];

    /**
     * 打刻丸め設定
     */
    public function companyShiftRoundingSettings(): HasMany
    {
        return $this->hasMany(CompanyShiftRoundingSetting::class, 'rounding_unit_id');
    }

    /**
     * 打刻丸め設定変更履歴
     */
    public function companyShiftRoundingSettingHistories(): HasMany
    {
        return $this->hasMany(CompanyShiftRoundingSettingHistory::class, 'rounding_unit_id');
    }
}

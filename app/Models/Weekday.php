<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Weekday extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
    ];

    /**
     * ユーザーの勤務可能曜日
     */
    public function userAvailableWorkDays(): HasMany
    {
        return $this->hasMany(UserAvailableWorkDay::class);
    }

    /**
     * ユーザーのシフトパターン
     */
    public function userShiftPatterns(): HasMany
    {
        return $this->hasMany(UserShiftPattern::class);
    }

    /**
     * 会社の定休日
     */
    public function companyRegularHolidays(): HasMany
    {
        return $this->hasMany(CompanyRegularHoliday::class);
    }
}

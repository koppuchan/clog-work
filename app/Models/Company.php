<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'company_code',
        'uuid',
        'name',
        'is_closed_on_holidays',
        'payroll_closing_day',
        'paid_leave_half_day',
        'paid_leave_hourly',
        'daily_working_hours',
        'is_stamp_hidden',
        'default_shift_pattern_id',
    ];

    /**
     * モデルの「起動」メソッド
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Company $company): void {
            if (empty($company->uuid)) {
                $company->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_closed_on_holidays' => 'boolean',
        'payroll_closing_day' => 'integer',
        'paid_leave_half_day' => 'boolean',
        'paid_leave_hourly' => 'boolean',
        'is_stamp_hidden' => 'boolean',
        'default_shift_pattern_id' => 'integer',
    ];

    /**
     * 会社に所属するユーザー
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_companies')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * 基本シフトパターン
     */
    public function defaultShiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class, 'default_shift_pattern_id');
    }

    /**
     * 会社の定休日
     */
    public function regularHolidays(): HasMany
    {
        return $this->hasMany(CompanyRegularHoliday::class);
    }

    /**
     * 会社のシフト丸め設定
     */
    public function shiftRoundingSettings(): HasMany
    {
        return $this->hasMany(CompanyShiftRoundingSetting::class);
    }

    /**
     * 会社の部署
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}

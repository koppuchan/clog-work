<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Department extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'is_stamp_hidden',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'company_id' => 'integer',
        'is_stamp_hidden' => 'boolean',
    ];

    /**
     * 所属する会社
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * 部署に所属するユーザー（多対多）
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departments')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}

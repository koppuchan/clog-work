<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 役割権限モデル
 *
 * 役割ごとのデフォルト権限を定義する
 */
class RolePermission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'role_id',
        'resource_id',
        'default_scope_id',
        'is_fixed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'resource_id' => 'integer',
            'default_scope_id' => 'integer',
            'is_fixed' => 'boolean',
        ];
    }

    // ========================================
    // リレーションシップ
    // ========================================

    /**
     * この権限が属する役割
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * この権限のリソース
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(PermissionResource::class, 'resource_id');
    }

    /**
     * この権限のデフォルトスコープ
     */
    public function defaultScope(): BelongsTo
    {
        return $this->belongsTo(PermissionScope::class, 'default_scope_id');
    }
}

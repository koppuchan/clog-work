<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'name_kana',
        'employee_code',
        'email',
        'password',
        'stamp_password',
        'felica_idm',
        'felica_registered_at',
        'must_change_password',
        'is_stamp_hidden',
        'is_shift_hidden',
        'is_owner',
        'is_super_admin',
        'is_retired',
        'retirement_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'stamp_password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'stamp_password' => 'hashed',
            'must_change_password' => 'boolean',
            'felica_registered_at' => 'datetime',
            'is_stamp_hidden' => 'boolean',
            'is_shift_hidden' => 'boolean',
            'is_owner' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    // ========================================
    // リレーションシップ
    // ========================================

    /**
     * ユーザーの役割（多対多）
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withTimestamps();
    }

    /**
     * ユーザーが所属する会社（多対多）
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * ユーザーの主要な会社（1対1）
     */
    public function primaryCompany(): ?\Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->companies()->wherePivot('is_primary', true);
    }

    /**
     * ユーザーが所属する部署（多対多）
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_departments')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * ユーザーの主要な部署（1対1）
     */
    public function primaryDepartment(): ?\Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->departments()->wherePivot('is_primary', true);
    }

    /**
     * ユーザーの個別権限（1対多）
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    // ========================================
    // アクセサ
    // ========================================

    /**
     * 主要な会社のIDを取得
     */
    protected function companyId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->primaryCompany()->first()?->id
        );
    }

    // ========================================
    // ヘルパーメソッド
    // ========================================

    /**
     * オーナー（新規登録で作成された最初のユーザー）かどうか
     */
    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }

    /**
     * 管理者かどうか
     */
    public function isAdmin(): bool
    {
        return $this->roles()->where('role_id', 1)->exists();
    }

    /**
     * 責任者かどうか
     */
    public function isManager(): bool
    {
        return $this->roles()->where('role_id', 2)->exists();
    }

    /**
     * 一般ユーザーかどうか
     */
    public function isGeneral(): bool
    {
        return $this->roles()->where('role_id', 3)->exists();
    }

    /**
     * 特定のロールを持っているか
     */
    public function hasRole(int $roleId): bool
    {
        return $this->roles()->where('role_id', $roleId)->exists();
    }
}

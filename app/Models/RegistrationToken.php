<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class RegistrationToken extends Model
{
    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * トークンが有効期限内かどうか
     */
    public function isValid(): bool
    {
        return $this->expires_at > CarbonImmutable::now() && $this->verified_at === null;
    }

    /**
     * トークンが検証済みかどうか
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * トークンを検証済みにする
     */
    public function markAsVerified(): void
    {
        $this->update(['verified_at' => CarbonImmutable::now()]);
    }
}

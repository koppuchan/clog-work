<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ユーザー個別権限
 *
 * @property int $id
 * @property int $user_id ユーザーID
 * @property int $resource_id リソースID
 * @property int $scope_id スコープID
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read PermissionResource $resource
 * @property-read PermissionScope $scope
 */
class UserPermission extends Model
{
    /**
     * テーブル名
     *
     * @var string
     */
    protected $table = 'user_permissions';

    /**
     * 主キーの型
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'resource_id',
        'scope_id',
    ];

    /**
     * キャストする属性
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'resource_id' => 'integer',
            'scope_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * ユーザーとのリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * リソースとのリレーション
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(PermissionResource::class, 'resource_id');
    }

    /**
     * スコープとのリレーション
     */
    public function scope(): BelongsTo
    {
        return $this->belongsTo(PermissionScope::class, 'scope_id');
    }
}

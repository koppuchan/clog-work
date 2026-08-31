<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 権限リソースマスター
 *
 * @property int $id
 * @property string $resource_code リソースコード
 * @property string $resource_name リソース名
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class PermissionResource extends Model
{
    /**
     * テーブル名
     *
     * @var string
     */
    protected $table = 'permission_resources';

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
        'resource_code',
        'resource_name',
    ];

    /**
     * キャストする属性
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}

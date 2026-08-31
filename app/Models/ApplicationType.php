<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 申請タイプマスター
 *
 * 申請の種類を表すマスターテーブル
 * 1=有給休暇, 2=打刻間違い, 3=遅刻, 4=早退, 5=特別休暇, 6=欠勤, 7=残業申請, 8=その他, 9=半日有給, 10=時間有給
 */
class ApplicationType extends Model
{
    use HasFactory;

    /**
     * テーブル名
     */
    protected $table = 'application_types';

    /**
     * 主キーのデータ型
     */
    protected $keyType = 'int';

    /**
     * 主キーの自動増分を無効化
     */
    public $incrementing = false;

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'id',
        'code',
        'name',
        'display_order',
        'is_active',
    ];

    /**
     * 属性のキャスト
     */
    protected $casts = [
        'id' => 'integer',
        'code' => 'string',
        'name' => 'string',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];
}

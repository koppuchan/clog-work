# Model ガイドライン

## 概要

ModelはEloquent ORMを使用してデータベーステーブルを表現するクラスです。データの構造定義、リレーションシップ、アクセサ/ミューテータ、スコープなどを管理します。

## 基本原則

### 単一責任の原則
- 1つのModelは1つのテーブルを表現する
- ビジネスロジックはServiceレイヤーに委譲する
- Modelはデータ構造とデータアクセスに関する責務のみを持つ

### Fat Modelアンチパターンを避ける
- 複雑なビジネスロジックをModelに書かない
- データ取得はRepositoryに委譲する
- Modelは軽量に保つ

## ファイル構造

```
app/Models/
└── {ModelName}.php
```

## 命名規則

### クラス名
- 単数形、PascalCase
- 例: `User`, `Attendance`, `WorkSchedule`

### テーブル名
- 複数形、snake_case（Eloquentが自動で推測）
- 例: `users`, `attendances`, `work_schedules`
- カスタムテーブル名は`$table`プロパティで指定

### カラム名
- snake_case
- 例: `user_id`, `started_at`, `created_at`

## コード例

### 基本的なModel

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * テーブル名（省略可能。規約に従う場合は自動設定）
     *
     * @var string
     */
    protected $table = 'attendances';

    /**
     * 主キー（省略可能。デフォルトは'id'）
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 主キーの型（省略可能。デフォルトは'int'）
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * 主キーが自動増分か（省略可能。デフォルトはtrue）
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * タイムスタンプを使用するか（省略可能。デフォルトはtrue）
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
        'break_minutes',
        'working_minutes',
        'note',
        'status',
        'approved_by',
        'approved_at',
    ];

    /**
     * 複数代入不可な属性（$fillableと排他）
     *
     * @var array<string>
     */
    protected $guarded = [
        'id',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'approved_at' => 'datetime',
        'break_minutes' => 'integer',
        'working_minutes' => 'integer',
        'status' => 'string',
    ];

    /**
     * デフォルト値
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'working',
        'break_minutes' => 0,
    ];

    /**
     * JSONシリアライズ時に隠す属性
     *
     * @var array<string>
     */
    protected $hidden = [
        // 機密情報がある場合に使用
    ];

    /**
     * JSONシリアライズ時に追加する属性
     *
     * @var array<string>
     */
    protected $appends = [
        'working_hours',
        'is_overtime',
    ];

    // ========================================
    // リレーションシップ
    // ========================================

    /**
     * 出勤記録を持つユーザー
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 承認者（ユーザー）
     *
     * @return BelongsTo
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ========================================
    // アクセサ（Getter）
    // ========================================

    /**
     * 勤務時間（時間単位）を取得
     *
     * @return float|null
     */
    public function getWorkingHoursAttribute(): ?float
    {
        return $this->working_minutes ? round($this->working_minutes / 60, 2) : null;
    }

    /**
     * 残業かどうか
     *
     * @return bool
     */
    public function getIsOvertimeAttribute(): bool
    {
        $standardMinutes = 8 * 60; // 8時間
        return ($this->working_minutes ?? 0) > $standardMinutes;
    }

    /**
     * ステータスの日本語表示
     *
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'working' => '勤務中',
            'completed' => '完了',
            'approved' => '承認済み',
            'rejected' => '却下',
            default => '不明',
        };
    }

    // ========================================
    // ミューテータ（Setter）
    // ========================================

    /**
     * 備考を設定（サニタイズ）
     *
     * @param string|null $value
     * @return void
     */
    public function setNoteAttribute(?string $value): void
    {
        $this->attributes['note'] = $value ? strip_tags($value) : null;
    }

    // ========================================
    // スコープ
    // ========================================

    /**
     * 特定ユーザーの出勤記録に絞り込み
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 期間で絞り込み
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon $from
     * @param Carbon $to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetweenDates($query, Carbon $from, Carbon $to)
    {
        return $query->whereBetween('started_at', [$from, $to]);
    }

    /**
     * アクティブ（勤務中）の記録のみ
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'working')->whereNull('ended_at');
    }

    /**
     * 承認済みの記録のみ
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * 今月の記録
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeThisMonth($query)
    {
        return $query->whereYear('started_at', now()->year)
                     ->whereMonth('started_at', now()->month);
    }

    // ========================================
    // ヘルパーメソッド
    // ========================================

    /**
     * 出勤中かどうか
     *
     * @return bool
     */
    public function isWorking(): bool
    {
        return $this->status === 'working' && is_null($this->ended_at);
    }

    /**
     * 承認済みかどうか
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * 承認可能かどうか
     *
     * @return bool
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'completed' && !is_null($this->ended_at);
    }
}
```

### リレーションシップの例

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ========================================
    // リレーションシップ
    // ========================================

    /**
     * 1対多: ユーザーの出勤記録
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * 多対1: ユーザーが所属する部署
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * 多対多: ユーザーの役割
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withTimestamps()
                    ->withPivot('assigned_at');
    }

    /**
     * 1対多（逆向き）: このユーザーが承認した出勤記録
     */
    public function approvedAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'approved_by');
    }
}
```

## ベストプラクティス

### 1. $fillableまたは$guardedを必ず定義

```php
// Good - セキュリティ対策として必須
protected $fillable = [
    'user_id',
    'started_at',
    'ended_at',
    'note',
];

// または
protected $guarded = ['id', 'approved_at'];
```

### 2. キャストを適切に設定

```php
// Good - 型安全性を保証
protected $casts = [
    'started_at' => 'datetime',
    'is_active' => 'boolean',
    'metadata' => 'array',
    'settings' => 'json',
];
```

### 3. スコープで再利用可能なクエリを定義

```php
// Good
public function scopeActive($query)
{
    return $query->where('status', 'active');
}

// 使用例
$activeUsers = User::active()->get();
```

### 4. リレーションシップはメソッド名を明確に

```php
// Good - 複数形（HasMany）
public function attendances(): HasMany

// Good - 単数形（BelongsTo）
public function user(): BelongsTo

// Good - 明示的な命名
public function approver(): BelongsTo
{
    return $this->belongsTo(User::class, 'approved_by');
}
```

### 5. アクセサで計算値を提供

```php
// Good - 計算ロジックをカプセル化
public function getWorkingHoursAttribute(): ?float
{
    return $this->working_minutes ? $this->working_minutes / 60 : null;
}

// 使用例
$attendance->working_hours; // 自動的に計算される
```

## アンチパターン

### ❌ ビジネスロジックをModelに書く

```php
// Bad - ビジネスロジックがModelに混在
class Attendance extends Model
{
    public function approve(int $approverId)
    {
        if ($this->status !== 'completed') {
            throw new \Exception('承認できません');
        }

        $this->status = 'approved';
        $this->approved_by = $approverId;
        $this->approved_at = now();
        $this->save();

        // 通知送信
        Mail::to($this->user)->send(new ApprovedNotification($this));
    }
}

// Good - Serviceに委譲
class AttendanceService
{
    public function approveAttendance(int $attendanceId, int $approverId): Attendance
    {
        return DB::transaction(function () use ($attendanceId, $approverId) {
            $attendance = $this->attendanceRepository->findById($attendanceId);

            if (!$attendance->canBeApproved()) {
                throw new BusinessException('承認できません');
            }

            $attendance = $this->attendanceRepository->update($attendanceId, [
                'status' => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            $this->notificationService->sendApprovalNotification($attendance);

            return $attendance;
        });
    }
}
```

### ❌ 複雑なクエリをModelに書く

```php
// Bad
class Attendance extends Model
{
    public static function getMonthlyReport(int $userId, int $year, int $month)
    {
        return self::where('user_id', $userId)
            ->whereYear('started_at', $year)
            ->whereMonth('started_at', $month)
            ->selectRaw('SUM(working_minutes) as total_minutes')
            ->selectRaw('AVG(working_minutes) as average_minutes')
            ->first();
    }
}

// Good - Repositoryに委譲
class AttendanceRepository
{
    public function getMonthlyStatistics(int $userId, int $year, int $month): array
    {
        $result = Attendance::where('user_id', $userId)
            ->whereYear('started_at', $year)
            ->whereMonth('started_at', $month)
            ->selectRaw('SUM(working_minutes) as total_minutes')
            ->selectRaw('AVG(working_minutes) as average_minutes')
            ->first();

        return [
            'total_hours' => $result->total_minutes / 60,
            'average_hours' => $result->average_minutes / 60,
        ];
    }
}
```

### ❌ $fillableを指定せず全カラムを代入可能にする

```php
// Bad - セキュリティリスク
class User extends Model
{
    protected $guarded = []; // 全てのカラムが代入可能
}

// Good
class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $guarded = [
        'id',
        'is_admin', // 重要なフラグは保護
    ];
}
```

## まとめ

- Modelはデータ構造とアクセス方法を定義
- ビジネスロジックはServiceに委譲
- リレーションシップを適切に定義
- スコープで再利用可能なクエリを作成
- アクセサ/ミューテータでデータの加工をカプセル化
- $fillable/$guardedで複数代入を制御

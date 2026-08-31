# Repository ガイドライン

## 概要

Repositoryパターンはデータアクセスロジックをカプセル化し、ビジネスロジック（Service）とデータ永続化（Model/Database）を分離します。これにより、テストが容易になり、データソースの変更に柔軟に対応できます。

## 基本原則

### データアクセスの抽象化
- データベースへのクエリロジックをRepositoryに集約
- ServiceレイヤーはRepositoryを通じてのみデータにアクセス
- Eloquentの実装詳細を隠蔽し、インターフェースを提供

### 単一責任の原則
- 1つのRepositoryは1つのModelに対するデータアクセスのみを担当
- 複数のModelを跨ぐ複雑なクエリはServiceレイヤーで組み立てる

## ファイル構造

```
app/Repositories/
├── AttendanceRepository.php
├── UserRepository.php
├── DepartmentRepository.php
└── Contracts/
    ├── AttendanceRepositoryInterface.php
    └── UserRepositoryInterface.php
```

## 命名規則

### クラス名
- 単数形のModel名 + `Repository`
- 例: `AttendanceRepository`, `UserRepository`

### インターフェース名
- 単数形のModel名 + `RepositoryInterface`
- 例: `AttendanceRepositoryInterface`, `UserRepositoryInterface`

### メソッド名
- CRUD操作: `findById`, `findAll`, `create`, `update`, `delete`
- 検索系: `findBy*`, `findAllBy*`, `exists*`
- カウント系: `count*`

## コード例

### インターフェース定義

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Attendance;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface AttendanceRepositoryInterface
{
    /**
     * IDで出勤記録を取得
     *
     * @param int $id
     * @return Attendance
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findById(int $id): Attendance;

    /**
     * 全出勤記録を取得
     *
     * @return Collection
     */
    public function findAll(): Collection;

    /**
     * ユーザーの出勤記録を取得（フィルター付き）
     *
     * @param int $userId
     * @param array $filters
     * @return Collection
     */
    public function findByUserWithFilters(int $userId, array $filters = []): Collection;

    /**
     * ユーザーのアクティブな出勤記録を取得
     *
     * @param int $userId
     * @return Attendance|null
     */
    public function findActiveByUser(int $userId): ?Attendance;

    /**
     * 月次の出勤記録を取得
     *
     * @param int $userId
     * @param int $year
     * @param int $month
     * @return Collection
     */
    public function findByMonth(int $userId, int $year, int $month): Collection;

    /**
     * 出勤記録を作成
     *
     * @param array $data
     * @return Attendance
     */
    public function create(array $data): Attendance;

    /**
     * 出勤記録を更新
     *
     * @param int $id
     * @param array $data
     * @return Attendance
     */
    public function update(int $id, array $data): Attendance;

    /**
     * 出勤記録を削除
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * 複数の出勤記録を一括作成
     *
     * @param array $records
     * @return bool
     */
    public function bulkInsert(array $records): bool;

    /**
     * 複数の出勤記録をUpsert（存在すれば更新、なければ挿入）
     *
     * @param array $records
     * @param array|string $uniqueBy
     * @param array|null $update
     * @return int
     */
    public function upsert(array $records, array|string $uniqueBy, ?array $update = null): int;

    /**
     * アクティブな出勤記録が存在するかチェック
     *
     * @param int $userId
     * @return bool
     */
    public function existsActiveByUser(int $userId): bool;

    /**
     * 重複する出勤記録が存在するかチェック
     *
     * @param int $userId
     * @param Carbon $startedAt
     * @param Carbon|null $endedAt
     * @return bool
     */
    public function existsOverlapping(int $userId, Carbon $startedAt, ?Carbon $endedAt = null): bool;
}
```

### Repository実装

```php
<?php

namespace App\Repositories;

use App\Models\Attendance;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class AttendanceRepository implements AttendanceRepositoryInterface
{
    /**
     * コンストラクタ
     *
     * @param Attendance $model
     */
    public function __construct(
        private readonly Attendance $model
    ) {}

    /**
     * IDで出勤記録を取得
     *
     * @param int $id
     * @return Attendance
     * @throws ModelNotFoundException
     */
    public function findById(int $id): Attendance
    {
        return $this->model
            ->with(['user', 'approver'])
            ->findOrFail($id);
    }

    /**
     * 全出勤記録を取得
     *
     * @return Collection
     */
    public function findAll(): Collection
    {
        return $this->model
            ->with(['user'])
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * ユーザーの出勤記録を取得（フィルター付き）
     *
     * @param int $userId
     * @param array $filters
     * @return Collection
     */
    public function findByUserWithFilters(int $userId, array $filters = []): Collection
    {
        return $this->model->query()
            ->where('user_id', $userId)
            ->with(['user', 'approver'])
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->where('started_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->where('started_at', '<=', $dateTo);
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * ユーザーのアクティブな出勤記録を取得
     *
     * @param int $userId
     * @return Attendance|null
     */
    public function findActiveByUser(int $userId): ?Attendance
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('status', 'working')
            ->whereNull('ended_at')
            ->first();
    }

    /**
     * 月次の出勤記録を取得
     *
     * @param int $userId
     * @param int $year
     * @param int $month
     * @return Collection
     */
    public function findByMonth(int $userId, int $year, int $month): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereYear('started_at', $year)
            ->whereMonth('started_at', $month)
            ->orderBy('started_at', 'asc')
            ->get();
    }

    /**
     * 出勤記録を作成
     *
     * @param array $data
     * @return Attendance
     */
    public function create(array $data): Attendance
    {
        return $this->model->create($data);
    }

    /**
     * 出勤記録を更新
     *
     * @param int $id
     * @param array $data
     * @return Attendance
     */
    public function update(int $id, array $data): Attendance
    {
        $attendance = $this->findById($id);
        $attendance->update($data);

        return $attendance->fresh();
    }

    /**
     * 出勤記録を削除
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $attendance = $this->findById($id);
        return $attendance->delete();
    }

    /**
     * 複数の出勤記録を一括作成
     *
     * @param array $records
     * @return bool
     */
    public function bulkInsert(array $records): bool
    {
        // タイムスタンプを自動付与
        $now = now();
        $records = array_map(function ($record) use ($now) {
            return array_merge($record, [
                'created_at' => $record['created_at'] ?? $now,
                'updated_at' => $record['updated_at'] ?? $now,
            ]);
        }, $records);

        return $this->model->insert($records);
    }

    /**
     * 複数の出勤記録をUpsert（存在すれば更新、なければ挿入）
     *
     * @param array $records
     * @param array|string $uniqueBy
     * @param array|null $update
     * @return int
     */
    public function upsert(array $records, array|string $uniqueBy, ?array $update = null): int
    {
        // タイムスタンプを自動付与
        $now = now();
        $records = array_map(function ($record) use ($now) {
            return array_merge($record, [
                'created_at' => $record['created_at'] ?? $now,
                'updated_at' => $now,
            ]);
        }, $records);

        return $this->model->upsert($records, $uniqueBy, $update);
    }

    /**
     * アクティブな出勤記録が存在するかチェック
     *
     * @param int $userId
     * @return bool
     */
    public function existsActiveByUser(int $userId): bool
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('status', 'working')
            ->whereNull('ended_at')
            ->exists();
    }

    /**
     * 重複する出勤記録が存在するかチェック
     *
     * @param int $userId
     * @param Carbon $startedAt
     * @param Carbon|null $endedAt
     * @return bool
     */
    public function existsOverlapping(int $userId, Carbon $startedAt, ?Carbon $endedAt = null): bool
    {
        $query = $this->model
            ->where('user_id', $userId)
            ->where(function ($q) use ($startedAt, $endedAt) {
                // 新しい記録の開始時刻が、既存記録の期間内にある
                $q->where(function ($subQ) use ($startedAt) {
                    $subQ->where('started_at', '<=', $startedAt)
                         ->where(function ($innerQ) use ($startedAt) {
                             $innerQ->where('ended_at', '>=', $startedAt)
                                    ->orWhereNull('ended_at');
                         });
                });

                // 新しい記録の終了時刻が指定されている場合
                if ($endedAt) {
                    $q->orWhere(function ($subQ) use ($startedAt, $endedAt) {
                        $subQ->whereBetween('started_at', [$startedAt, $endedAt]);
                    });
                }
            });

        return $query->exists();
    }

    /**
     * ページネーション付きで取得
     *
     * @param int $userId
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateByUser(int $userId, int $perPage = 15)
    {
        return $this->model
            ->where('user_id', $userId)
            ->with(['user', 'approver'])
            ->orderBy('started_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * カウント取得
     *
     * @param int $userId
     * @param array $filters
     * @return int
     */
    public function countByUser(int $userId, array $filters = []): int
    {
        $query = $this->model->where('user_id', $userId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->count();
    }
}
```

## ベストプラクティス

### 1. インターフェースを定義する

```php
// Good - インターフェースで契約を定義
interface AttendanceRepositoryInterface
{
    public function findById(int $id): Attendance;
    public function create(array $data): Attendance;
}

// ServiceProviderでバインド
public function register(): void
{
    $this->app->bind(
        AttendanceRepositoryInterface::class,
        AttendanceRepository::class
    );
}

// Serviceで使用
public function __construct(
    private readonly AttendanceRepositoryInterface $attendanceRepository
) {}
```

### 2. Eager Loadingで N+1 問題を回避

```php
// Good
public function findByUserWithFilters(int $userId, array $filters = []): Collection
{
    return $this->model
        ->with(['user', 'approver']) // リレーションを事前読み込み
        ->where('user_id', $userId)
        ->get();
}

// Bad
public function findByUserWithFilters(int $userId, array $filters = []): Collection
{
    return $this->model
        ->where('user_id', $userId)
        ->get(); // N+1問題が発生
}
```

### 3. when() を使って条件付きクエリを構築

```php
// Good - when()メソッドでスマートに条件分岐
public function findByConditions(array $conditions): Collection
{
    return $this->model->query()
        ->when($conditions['status'] ?? null, function ($query, $status) {
            $query->where('status', $status);
        })
        ->when($conditions['from_date'] ?? null, function ($query, $fromDate) {
            $query->where('started_at', '>=', $fromDate);
        })
        ->when($conditions['to_date'] ?? null, function ($query, $toDate) {
            $query->where('started_at', '<=', $toDate);
        })
        ->orderBy('started_at', 'desc')
        ->get();
}

// Bad - if文の連続
public function findByConditions(array $conditions): Collection
{
    $query = $this->model->query();

    if (!empty($conditions['status'])) {
        $query->where('status', $conditions['status']);
    }

    if (!empty($conditions['from_date'])) {
        $query->where('started_at', '>=', $conditions['from_date']);
    }

    if (!empty($conditions['to_date'])) {
        $query->where('started_at', '<=', $conditions['to_date']);
    }

    return $query->orderBy('started_at', 'desc')->get();
}
```

### 4. トランザクションはServiceレイヤーで管理

```php
// Repository - トランザクションを意識しない
public function create(array $data): Attendance
{
    return $this->model->create($data);
}

// Service - トランザクション管理
public function createAttendanceWithNotification(int $userId, array $data): Attendance
{
    return DB::transaction(function () use ($userId, $data) {
        $attendance = $this->attendanceRepository->create($data);
        $this->notificationRepository->create([...]);
        return $attendance;
    });
}
```

### 5. exists* メソッドでパフォーマンス改善

```php
// Good - exists()を使用
public function existsActiveByUser(int $userId): bool
{
    return $this->model
        ->where('user_id', $userId)
        ->where('status', 'working')
        ->exists(); // 高速
}

// Bad - count()を使用
public function existsActiveByUser(int $userId): bool
{
    return $this->model
        ->where('user_id', $userId)
        ->where('status', 'working')
        ->count() > 0; // 遅い
}
```

### 6. bulkInsert と upsert でパフォーマンス向上

```php
// Good - bulkInsertで大量データを高速に挿入
public function bulkInsert(array $records): bool
{
    $now = now();
    $records = array_map(function ($record) use ($now) {
        return array_merge($record, [
            'created_at' => $record['created_at'] ?? $now,
            'updated_at' => $record['updated_at'] ?? $now,
        ]);
    }, $records);

    return $this->model->insert($records);
}

// 使用例：CSVインポートなどで大量データを一括登録
$records = [
    ['user_id' => 1, 'started_at' => '2024-01-01 09:00:00', 'status' => 'completed'],
    ['user_id' => 1, 'started_at' => '2024-01-02 09:00:00', 'status' => 'completed'],
    ['user_id' => 2, 'started_at' => '2024-01-01 09:00:00', 'status' => 'completed'],
    // ... 数千〜数万件
];

// 1回のクエリで全て挿入（超高速）
$this->attendanceRepository->bulkInsert($records);

// Bad - ループで1件ずつ挿入（遅い）
foreach ($records as $record) {
    $this->attendanceRepository->create($record); // N回のクエリが発生
}
```

```php
// Good - upsertで存在チェック＋挿入/更新を1クエリで実行
public function upsert(array $records, array|string $uniqueBy, ?array $update = null): int
{
    $now = now();
    $records = array_map(function ($record) use ($now) {
        return array_merge($record, [
            'created_at' => $record['created_at'] ?? $now,
            'updated_at' => $now,
        ]);
    }, $records);

    return $this->model->upsert($records, $uniqueBy, $update);
}

// 使用例1: ユニークキー（user_id + started_at）で重複を防ぎつつ登録
$records = [
    ['user_id' => 1, 'started_at' => '2024-01-01 09:00:00', 'ended_at' => '2024-01-01 18:00:00'],
    ['user_id' => 1, 'started_at' => '2024-01-02 09:00:00', 'ended_at' => '2024-01-02 18:00:00'],
];

// 既に存在する場合はended_atのみ更新、なければ挿入
$this->attendanceRepository->upsert(
    $records,
    ['user_id', 'started_at'],  // ユニークキー
    ['ended_at', 'updated_at']  // 更新するカラム
);

// 使用例2: 外部システムとの同期
$externalData = [
    ['external_id' => 'EXT001', 'user_id' => 1, 'status' => 'approved'],
    ['external_id' => 'EXT002', 'user_id' => 2, 'status' => 'pending'],
];

// external_idが同じレコードは更新、なければ挿入
$this->attendanceRepository->upsert(
    $externalData,
    'external_id',  // ユニークキー（文字列でも可）
    ['status', 'updated_at']
);
```

### 7. find* と get* の使い分け

```php
// findById - 単一レコード、見つからない場合は例外
public function findById(int $id): Attendance
{
    return $this->model->findOrFail($id);
}

// findByUser - 複数レコード、見つからない場合は空Collection
public function findByUser(int $userId): Collection
{
    return $this->model->where('user_id', $userId)->get();
}

// findActiveByUser - 単一レコード、見つからない場合はnull
public function findActiveByUser(int $userId): ?Attendance
{
    return $this->model->where('user_id', $userId)->first();
}
```

## アンチパターン

### ❌ ビジネスロジックをRepositoryに書く

```php
// Bad
class AttendanceRepository
{
    public function clockIn(int $userId): Attendance
    {
        // ビジネスロジックがRepository内にある
        if ($this->existsActiveByUser($userId)) {
            throw new BusinessException('既に出勤中です');
        }

        return $this->create([
            'user_id' => $userId,
            'started_at' => now(),
        ]);
    }
}

// Good - ビジネスロジックはServiceに
class AttendanceService
{
    public function clockIn(int $userId): Attendance
    {
        if ($this->attendanceRepository->existsActiveByUser($userId)) {
            throw new BusinessException('既に出勤中です');
        }

        return $this->attendanceRepository->create([
            'user_id' => $userId,
            'started_at' => now(),
        ]);
    }
}
```

### ❌ 複数のModelを扱う

```php
// Bad - AttendanceRepositoryでUserも操作
class AttendanceRepository
{
    public function createWithUser(array $userData, array $attendanceData)
    {
        $user = User::create($userData); // NG
        return $this->model->create(array_merge($attendanceData, ['user_id' => $user->id]));
    }
}

// Good - 各Repositoryが自分のModelのみ扱う
class AttendanceService
{
    public function createWithUser(array $userData, array $attendanceData)
    {
        return DB::transaction(function () use ($userData, $attendanceData) {
            $user = $this->userRepository->create($userData);
            return $this->attendanceRepository->create(
                array_merge($attendanceData, ['user_id' => $user->id])
            );
        });
    }
}
```

### ❌ 汎用的すぎるメソッド

```php
// Bad - 汎用的すぎて意図が不明確
public function findBy(string $column, $value)
{
    return $this->model->where($column, $value)->get();
}

// Good - 用途が明確
public function findByStatus(string $status): Collection
{
    return $this->model->where('status', $status)->get();
}

public function findByUser(int $userId): Collection
{
    return $this->model->where('user_id', $userId)->get();
}
```

## まとめ

- Repositoryはデータアクセスを抽象化
- インターフェースで契約を定義し、テストしやすく
- ビジネスロジックは含めず、データ操作のみに専念
- Eager Loadingでパフォーマンスを最適化
- `when()`メソッドで条件付きクエリをスマートに構築
- `bulkInsert()`と`upsert()`で大量データを高速処理
- `exists()`や`first()`を適切に使い分ける
- 1つのRepositoryは1つのModelに対応

## パフォーマンスチートシート

| 操作 | 遅い方法 | 速い方法 |
|------|---------|---------|
| 大量データ挿入 | `foreach` + `create()` | `bulkInsert()` |
| 重複チェック＋挿入/更新 | `find()` → `update()`/`create()` | `upsert()` |
| 存在チェック | `count() > 0` | `exists()` |
| リレーション取得 | Lazy Loading | Eager Loading (`with()`) |
| 条件付きクエリ | `if`文の連続 | `when()`メソッド |

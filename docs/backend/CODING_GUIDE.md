# Backend 共通コーディングガイドライン

## 概要

このドキュメントは、バックエンド開発における共通のコーディング規約とベストプラクティスをまとめたものです。

## 目次

1. [アーキテクチャ概要](#アーキテクチャ概要)
2. [コーディング規約](#コーディング規約)
3. [日付・時刻の扱い（重要）](#日付時刻の扱い重要)
4. [命名規則](#命名規則)
9. [ドキュメント](#ドキュメント)

## アーキテクチャ概要

このプロジェクトは、レイヤードアーキテクチャを採用しています。各レイヤーは明確な責務を持ち、単方向の依存関係を維持します。

### レイヤー構成

```
Controller (HTTP層)
    ↓
Service (ビジネスロジック層)
    ↓
Repository (データアクセス層)
    ↓
Model (データモデル層)
```

### 各レイヤーの責務

#### Controller層 (`app/Http/Controllers/`)

- **責務**: HTTPリクエストの受け取りとレスポンスの返却
- **配置**:
  - `Admin/` - 管理者用Controller
  - `Staff/` - スタッフ用Controller
- **役割**:
  - リクエストのバリデーション（Form Request使用）
  - 認可チェック（Policy使用）
  - Serviceの呼び出し
  - Inertia.jsを通じてReactコンポーネントへのデータ受け渡し
- **禁止事項**:
  - ビジネスロジックの実装
  - 直接Modelの操作
  - データベースクエリの実行

詳細: [Controller ガイドライン](./controller.md)

#### Service層 (`app/Services/`)

- **責務**: ビジネスロジックの実装
- **役割**:
  - ドメインロジックの集約
  - トランザクション管理
  - 複数のRepositoryの調整
  - ビジネスルールのバリデーション（入力検証ではない）
  - 例外処理
- **禁止事項**:
  - HTTPリクエスト/レスポンスの直接操作
  - 直接Modelの操作（Repositoryを経由）
  - **入力バリデーション（Form Requestで実装すること）**

⚠️ **重要**: 入力検証（必須チェック、型チェック、unique制約など）はForm Requestで行います。Serviceでは入力検証を行わず、ビジネスルールの検証のみを実装してください。

詳細: [Service ガイドライン](./service.md)

#### Repository層 (`app/Repositories/`)

- **責務**: データアクセスロジックの抽象化
- **役割**:
  - CRUDオペレーション
  - クエリの構築
  - データの永続化
  - キャッシュ制御
- **特徴**:
  - インターフェースと実装を分離
  - テスタビリティの向上
  - データソースの切り替えが容易

詳細: [Repository ガイドライン](./repository.md)

#### Model層 (`app/Models/`)

- **責務**: データ構造の定義とリレーション
- **役割**:
  - テーブル構造の定義
  - リレーションの定義
  - アクセサ/ミューテータ
  - キャスト定義
- **禁止事項**:
  - ビジネスロジックの実装
  - 複雑なクエリロジック

詳細: [Model ガイドライン](./model.md)

#### Policy層 (`app/Policies/`)

- **責務**: 認可ロジックの実装
- **役割**:
  - リソースへのアクセス制御
  - ユーザー権限の判定
  - カスタム認可ルール

詳細: [Policy ガイドライン](./policy.md)

### データフロー例

```
1. ユーザーが出勤記録作成をリクエスト
   ↓
2. Admin/AttendanceController::store()
   - Form Requestでバリデーション
   - Policyで認可チェック
   ↓
3. AttendanceService::createAttendance()
   - ビジネスルールチェック（重複チェックなど）
   - トランザクション開始
   ↓
4. AttendanceRepository::create()
   - データベースへの保存
   ↓
5. Attendance Model
   - Eloquentを通じてデータ永続化
   ↓
6. Controllerがレスポンス
   - Inertia.jsでReactコンポーネントへデータ返却
```

### 依存性注入

全てのレイヤーで依存性注入（DI）を使用します。

```php
// Controller
public function __construct(
    private readonly AttendanceService $attendanceService
) {}

// Service
public function __construct(
    private readonly AttendanceRepositoryInterface $attendanceRepository,
    private readonly UserRepositoryInterface $userRepository
) {}

// Repository実装
public function __construct(
    private readonly Attendance $model
) {}
```

### インターフェース分離

Repository層ではインターフェースを定義し、実装を分離します。

```
app/Repositories/
├── Interfaces/
│   └── {Model}RepositoryInterface.php
└── {Model}Repository.php
```

サービスプロバイダーでバインド:

```php
$this->app->bind(
    AttendanceRepositoryInterface::class,
    AttendanceRepository::class
);
```

### まとめ

- **単一責任の原則**: 各レイヤーは1つの責務のみを持つ
- **依存性の方向**: 上位レイヤーから下位レイヤーへの単方向
- **疎結合**: インターフェースによる抽象化
- **テスタビリティ**: DIとインターフェースによるモックの容易性

## コーディング規約

### PHP基本規約

#### PSR準拠

- PSR-12（コーディングスタイル）に準拠
- PSR-4（オートローディング）に準拠
- Laravel Pintで自動フォーマット

#### 型宣言

厳密な型宣言を使用します。

```php
<?php


namespace App\Services;

// ✅ Good - 引数と戻り値に型を指定
public function createAttendance(int $userId, array $data): Attendance
{
    // ...
}

// ✅ Good - Nullable型の明示
public function findAttendance(?int $id): ?Attendance
{
    // ...
}

// ❌ Bad - 型宣言なし
public function createAttendance($userId, $data)
{
    // ...
}
```

#### readonly プロパティ

PHP 8.2以降の`readonly`を積極的に使用します。

```php
// ✅ Good - 依存性注入でreadonly使用
public function __construct(
    private readonly AttendanceService $attendanceService,
    private readonly UserService $userService
) {}

// ❌ Bad - readonlyなし
public function __construct(
    private AttendanceService $attendanceService
) {}
```

#### Named Arguments

可読性のため、Named Argumentsを使用します。

```php
// ✅ Good - 引数名を明示
$attendance = $this->attendanceService->createAttendance(
    userId: $request->user()->id,
    data: $request->validated()
);

// ❌ Bad - 位置引数のみ
$attendance = $this->attendanceService->createAttendance(
    $request->user()->id,
    $request->validated()
);
```

### Laravel規約

#### Migrationファイルの作成禁止

**このプロジェクトでは新規のMigrationファイルを作成しないでください。**

```php
// ❌ Bad - Migrationファイルの新規作成は禁止
php artisan make:migration create_xxx_table
php artisan make:migration add_column_to_xxx_table
```

**理由**:
1. **テーブル設計は完了済み**: すべてのテーブルは既に設計・作成済みです
2. **設計書との整合性**: `docs/tables/README.md` にテーブル設計が文書化されています
3. **データ整合性の維持**: 既存データへの影響を最小限に抑えるため

**テーブル構造の確認方法**:
- テーブル設計: `docs/tables/README.md` を参照
- 実際のスキーマ: `mcp__laravel-boost__database-schema` ツールで確認

**カラム追加・変更が必要な場合**:
1. まず `docs/tables/README.md` を確認してテーブル設計を理解
2. 必ずユーザーに相談し、承認を得る
3. 承認後、既存のマイグレーションファイルを修正するか、新規作成の指示を受ける

#### データベース操作は必ずModelを使用

**データベースの操作は必ずEloquent Modelを経由して行います。`DB::table()`の直接使用は禁止します。**

```php
// ✅ Good - Eloquent Model経由（必ずこちらを使用）
$attendances = Attendance::query()
    ->where('user_id', $userId)
    ->with('user')
    ->get();

// ✅ Good - Repository経由（推奨）
$attendances = $this->attendanceRepository->findByUser($userId);

// ❌ Bad - DB::table()の直接使用（禁止）
$attendances = DB::table('attendances')
    ->where('user_id', $userId)
    ->get();
```

#### Eloquent vs Query Builder

基本的にEloquentを使用します。大量データ処理など特別な理由がある場合のみ、Modelのメソッド内でQuery Builderの使用を検討します。

```php
// ✅ Good - Eloquent（通常はこちら）
$attendances = $this->model->query()
    ->where('user_id', $userId)
    ->with('user')
    ->get();

// ✅ 許容される - Repository内で大量データ処理時のみQuery Builder使用
class AttendanceRepository
{
    public function bulkUpdateStatus(array $ids, AttendanceStatusEnum $status): int
    {
        // 大量データ更新のためQuery Builderを使用
        return $this->model->newQuery()
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }
}

// ❌ Bad - Controller/ServiceでDB::table()を直接使用
class AttendanceController
{
    public function index()
    {
        $attendances = DB::table('attendances')->get(); // 禁止
    }
}
```

#### DB::table()を使用してはいけない理由

1. **Modelのイベントが発火しない**: `creating`, `updating`, `deleted`などのイベントが動作しない
2. **リレーションが使えない**: Eager Loadingやリレーション機能が使えない
3. **キャストが効かない**: `$casts`で定義した型変換が適用されない
4. **Enumが使えない**: Enum型のキャストが動作しない
5. **保守性の低下**: Modelの変更が反映されず、予期しない不具合の原因になる

```php
// ❌ Bad - DB::table()の問題例
$attendance = DB::table('attendances')->find(1);
echo $attendance->status; // 文字列 "pending" - Enumにならない
echo $attendance->created_at; // 文字列 - CarbonImmutableにならない

// ✅ Good - Eloquentの利点
$attendance = Attendance::find(1);
echo $attendance->status->label(); // "承認待ち" - Enumのメソッドが使える
echo $attendance->created_at->format('Y-m-d'); // CarbonImmutableのメソッドが使える
```

#### クエリの条件分岐

`when()`メソッドを使用します。

```php
// ✅ Good - when()メソッド
return $this->model->query()
    ->when($filters['status'] ?? null, fn($q, $status) =>
        $q->where('status', $status)
    )
    ->when($filters['date_from'] ?? null, fn($q, $date) =>
        $q->where('started_at', '>=', $date)
    )
    ->get();

// ❌ Bad - if文での条件分岐
$query = $this->model->query();
if (!empty($filters['status'])) {
    $query->where('status', $filters['status']);
}
if (!empty($filters['date_from'])) {
    $query->where('started_at', '>=', $filters['date_from']);
}
return $query->get();
```

#### N+1問題の回避

必ず`with()`でEager Loadingを使用します。

```php
// ✅ Good - Eager Loading
$attendances = Attendance::with(['user', 'approver'])->get();

// ❌ Bad - N+1問題発生
$attendances = Attendance::all();
foreach ($attendances as $attendance) {
    echo $attendance->user->name; // 毎回クエリ発行
}
```

#### トランザクション

複数のデータ操作は必ずトランザクションで囲みます。

```php
// ✅ Good - トランザクション使用
public function createAttendance(int $userId, array $data): Attendance
{
    return DB::transaction(function () use ($userId, $data) {
        $attendance = $this->attendanceRepository->create([
            'user_id' => $userId,
            ...$data,
        ]);

        $this->notificationService->sendCreatedNotification($attendance);

        return $attendance;
    });
}

// ❌ Bad - トランザクションなし
public function createAttendance(int $userId, array $data): Attendance
{
    $attendance = $this->attendanceRepository->create([
        'user_id' => $userId,
        ...$data,
    ]);

    // ここで失敗すると不整合が発生
    $this->notificationService->sendCreatedNotification($attendance);

    return $attendance;
}
```

### 配列・Collection操作

#### Collection を基本とする

**配列の代わりに Laravel Collection を使用することを基本とします。**

```php
// ✅ Good - Collectionを使用
$attendances = $this->attendanceRepository->getAll();

$approvedCount = $attendances
    ->filter(fn($attendance) => $attendance->status === AttendanceStatusEnum::APPROVED)
    ->count();

$totalMinutes = $attendances
    ->sum(fn($attendance) => $attendance->work_minutes);

$groupedByUser = $attendances
    ->groupBy('user_id')
    ->map(fn($group) => $group->count());

// ❌ Bad - 配列操作
$attendances = $this->attendanceRepository->getAll()->toArray();

$approvedCount = count(array_filter($attendances, function($attendance) {
    return $attendance['status'] === 'approved';
}));
```

#### Collection の主な使用例

```php
// filter - 条件に合う要素のみ抽出
$pending = $attendances->filter(fn($a) => $a->status === AttendanceStatusEnum::PENDING);

// map - 各要素を変換
$userIds = $attendances->map(fn($a) => $a->user_id);

// pluck - 特定のカラムを抽出
$ids = $attendances->pluck('id');
$nameById = $users->pluck('name', 'id'); // キー付き

// groupBy - グループ化
$byStatus = $attendances->groupBy('status');
$byDate = $attendances->groupBy(fn($a) => $a->started_at->format('Y-m-d'));

// sum - 合計
$totalMinutes = $attendances->sum('work_minutes');
$totalMinutes = $attendances->sum(fn($a) => $a->work_minutes);

// each - 各要素に処理を実行
$attendances->each(fn($a) => $this->notifyUser($a));

// first - 最初の要素
$first = $attendances->first();
$firstApproved = $attendances->first(fn($a) => $a->status === AttendanceStatusEnum::APPROVED);

// contains - 要素の存在確認
$hasApproved = $attendances->contains('status', AttendanceStatusEnum::APPROVED);

// isEmpty / isNotEmpty - 空チェック
if ($attendances->isEmpty()) {
    // ...
}

// chunk - 分割処理
$attendances->chunk(100)->each(function ($chunk) {
    // 100件ずつ処理
});
```

#### メソッドチェーン

Collectionのメリットを活かし、メソッドチェーンで処理を記述します。

```php
// ✅ Good - メソッドチェーン
$result = $attendances
    ->filter(fn($a) => $a->status === AttendanceStatusEnum::APPROVED)
    ->groupBy(fn($a) => $a->started_at->format('Y-m'))
    ->map(fn($group) => [
        'count' => $group->count(),
        'total_minutes' => $group->sum('work_minutes'),
    ])
    ->sortKeys();

// ❌ Bad - 個別の変数に分割
$approved = $attendances->filter(fn($a) => $a->status === AttendanceStatusEnum::APPROVED);
$grouped = $approved->groupBy(fn($a) => $a->started_at->format('Y-m'));
$mapped = $grouped->map(fn($group) => [
    'count' => $group->count(),
    'total_minutes' => $group->sum('work_minutes'),
]);
$result = $mapped->sortKeys();
```

#### 配列からCollectionへの変換

必要に応じて`collect()`で配列をCollectionに変換します。

```php
// ✅ Good - 配列をCollectionに変換
$data = collect($request->validated())
    ->filter(fn($value) => !is_null($value))
    ->toArray();

// リクエストパラメータの処理
$filters = collect($request->only(['status', 'date_from', 'date_to']))
    ->filter()
    ->toArray();
```

#### スプレッド演算子

配列のマージには`array_merge()`よりスプレッド演算子を使用します。

```php
// ✅ Good - スプレッド演算子
$data = [
    'user_id' => $userId,
    ...$validatedData,
    'created_at' => CarbonImmutable::now(),
];

// ❌ Bad - array_merge()
$data = array_merge(
    ['user_id' => $userId],
    $validatedData,
    ['created_at' => CarbonImmutable::now()]
);
```

#### null合体演算子

デフォルト値の設定には`??`を使用します。

```php
// ✅ Good - null合体演算子
$status = $request->input('status') ?? 'pending';
$date = $data['date'] ?? CarbonImmutable::today();

// ❌ Bad - 三項演算子
$status = isset($request->input('status')) ? $request->input('status') : 'pending';
```

#### 配列操作が許容される場合

以下の場合は配列操作も許容されます：

- パフォーマンスが重要な大量データ処理
- 単純な配列構造の構築
- 外部ライブラリとのインターフェース

```php
// ✅ 許容される - シンプルな配列構築
$data = [
    'user_id' => $userId,
    'status' => AttendanceStatusEnum::PENDING->value,
];

// ✅ 許容される - パフォーマンス重視
$ids = array_column($rows, 'id'); // 大量データの場合
```

### コメント

#### PHPDoc

全てのpublicメソッドにPHPDocを記述します。

```php
/**
 * 出勤記録を作成する
 *
 * @param int $userId ユーザーID
 * @param array $data 出勤記録データ
 * @return Attendance 作成された出勤記録
 * @throws BusinessException ビジネスルール違反時
 */
public function createAttendance(int $userId, array $data): Attendance
{
    // ...
}
```

#### インラインコメント

複雑なロジックにのみコメントを記述します。

```php
// ✅ Good - 複雑なロジックの説明
// 勤務時間が8時間を超える場合、自動的に1時間の休憩を追加
if ($workMinutes > 480 && !isset($data['break_minutes'])) {
    $data['break_minutes'] = 60;
}

// ❌ Bad - 自明なコードへのコメント
// ユーザーIDを取得
$userId = $request->user()->id;
```

### その他

#### 早期return

ネストを減らすため、早期returnを使用します。

```php
// ✅ Good - 早期return
public function approve(int $id): void
{
    $attendance = $this->findAttendance($id);

    if ($attendance->status === 'approved') {
        throw new BusinessException('既に承認済みです。');
    }

    if (!$attendance->ended_at) {
        throw new BusinessException('退勤記録がありません。');
    }

    $this->attendanceRepository->update($id, ['status' => 'approved']);
}

// ❌ Bad - ネストが深い
public function approve(int $id): void
{
    $attendance = $this->findAttendance($id);

    if ($attendance->status !== 'approved') {
        if ($attendance->ended_at) {
            $this->attendanceRepository->update($id, ['status' => 'approved']);
        } else {
            throw new BusinessException('退勤記録がありません。');
        }
    } else {
        throw new BusinessException('既に承認済みです。');
    }
}
```

#### マジックナンバーの禁止

定数やenumを使用します。

```php
// ✅ Good - 定数/enum使用
enum AttendanceStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

if ($attendance->status === AttendanceStatus::APPROVED->value) {
    // ...
}

// ❌ Bad - マジックナンバー/文字列
if ($attendance->status === 'approved') {
    // ...
}
```

## 日付・時刻の扱い（重要）

### ⚠️ 絶対に `now()` を使用しないこと

**このプロジェクトでは `now()` の使用を禁止します。** 代わりに必ず `CarbonImmutable` を使用してください。

### 理由

1. **不変性の保証**: `Carbon`は可変オブジェクトのため、予期しない副作用が発生する可能性があります
2. **バグの防止**: 日付オブジェクトが意図せず変更されることを防ぎます
3. **テストの容易性**: イミュータブルなオブジェクトはテストが簡単です
4. **関数型プログラミングの原則**: 副作用のないコードを書くことができます

### ✅ Good - CarbonImmutableを使用

```php
<?php

use Carbon\CarbonImmutable;

// 現在日時の取得
$now = CarbonImmutable::now();
$today = CarbonImmutable::today();

// 日付の操作（元のオブジェクトは変更されない）
$tomorrow = $now->addDay();
$yesterday = $now->subDay();
$nextWeek = $now->addWeek();

// $nowは変更されていない
echo $now->toDateTimeString(); // 元の値のまま

// 特定の日時を作成
$specificDate = CarbonImmutable::create(2024, 1, 1, 9, 0, 0);
$parsed = CarbonImmutable::parse('2024-01-01 09:00:00');

// タイムゾーン指定
$tokyo = CarbonImmutable::now('Asia/Tokyo');
```

### ❌ Bad - now()やCarbonを使用

```php
<?php

use Carbon\Carbon;

// ❌ 絶対に使用しないこと
$now = now(); // Laravel ヘルパー - 禁止
$now = Carbon::now(); // 可変オブジェクト - 禁止

// ❌ 可変オブジェクトの問題例
$date = Carbon::now();
$modifiedDate = $date->addDay(); // $dateも変更されてしまう！
echo $date->toDateTimeString(); // 元の値ではない
```

### 実装例

```php
<?php

use Carbon\CarbonImmutable;

// ✅ 現在日時の取得
$now = CarbonImmutable::now();

// ✅ 日付操作（イミュータブル）
$tomorrow = $now->addDay();
$startOfMonth = $now->startOfMonth();

// ✅ パース
$date = CarbonImmutable::parse('2024-01-01 09:00:00');

// ✅ 型ヒントで明示
public function clockIn(?CarbonImmutable $startedAt = null): Attendance
{
    $startedAt = $startedAt ?? CarbonImmutable::now();
    // ...
}
```

### まとめ

- ✅ **必ず `CarbonImmutable` を使用**
- ❌ **`now()` は絶対に使用しない**
- ❌ **`Carbon` (可変) は使用しない**

## 命名規則

### 変数名

#### キャメルケース (camelCase)

変数名は必ずキャメルケースを使用します。

```php
// ✅ Good - キャメルケース
$userId = 1;
$userName = 'John Doe';
$attendanceList = [];
$isApproved = true;
$hasError = false;
$createdAt = CarbonImmutable::now();

// ❌ Bad - スネークケース
$user_id = 1;
$user_name = 'John Doe';
$attendance_list = [];
$is_approved = true;
```

#### 意味のある名前

変数名は具体的で意味のある名前を付けます。

```php
// ✅ Good - 意味が明確
$attendanceCount = 10;
$approvedAttendances = [];
$startedAt = CarbonImmutable::now();

// ❌ Bad - 意味が不明確
$cnt = 10;
$arr = [];
$dt = CarbonImmutable::now();
$data = []; // 何のデータか不明
```

### 配列のキー

#### スネークケース (snake_case)

配列のキーはスネークケースを使用します（データベースのカラム名と一致）。

```php
// ✅ Good - スネークケース
$data = [
    'user_id' => 1,
    'started_at' => '2024-01-01 09:00:00',
    'ended_at' => '2024-01-01 18:00:00',
    'break_minutes' => 60,
    'status' => AttendanceStatusEnum::PENDING,
];

// ❌ Bad - キャメルケース
$data = [
    'userId' => 1,
    'startedAt' => '2024-01-01 09:00:00',
    'endedAt' => '2024-01-01 18:00:00',
];
```

### クラス名

#### パスカルケース (PascalCase)

クラス名はパスカルケースを使用します。

```php
// ✅ Good
class AttendanceService {}
class UserController {}
class AttendanceStatusEnum {}
class BusinessException {}

// ❌ Bad
class attendanceService {}
class user_controller {}
```

### メソッド名

#### キャメルケース (camelCase)

メソッド名はキャメルケースを使用します。

```php
// ✅ Good
public function createAttendance(int $userId, array $data): Attendance {}
public function findByStatus(AttendanceStatusEnum $status): Collection {}
public function isApproved(): bool {}
public function hasPermission(): bool {}

// ❌ Bad
public function CreateAttendance() {} // パスカルケース
public function find_by_status() {} // スネークケース
```

#### 動詞で始める

メソッド名は動詞で始めます。

```php
// ✅ Good - 動詞で始まる
public function getAttendances(): Collection {}
public function createAttendance(array $data): Attendance {}
public function updateStatus(int $id, AttendanceStatusEnum $status): void {}
public function deleteAttendance(int $id): void {}
public function isApproved(): bool {}
public function canApprove(): bool {}

// ❌ Bad - 名詞のみ
public function attendances(): Collection {} // getを付ける
public function approval(): bool {} // isApprovedやcanApproveにする
```

### 定数

#### UPPER_SNAKE_CASE

定数は大文字のスネークケースを使用します。

```php
// ✅ Good
class AttendanceService
{
    private const MAX_WORK_HOURS = 12;
    private const DEFAULT_BREAK_MINUTES = 60;
    private const APPROVAL_THRESHOLD_DAYS = 7;
}

// ❌ Bad
class AttendanceService
{
    private const maxWorkHours = 12;
    private const default_break_minutes = 60;
}
```

### Enum Case名

#### UPPER_SNAKE_CASE

Enumのケース名は大文字のスネークケースを使用します。

```php
// ✅ Good
enum AttendanceStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

// ❌ Bad
enum AttendanceStatusEnum: string
{
    case Pending = 'pending';
    case approved = 'approved';
}
```

### データベーステーブル名

#### 複数形・スネークケース

テーブル名は複数形のスネークケースを使用します。

```php
// ✅ Good
attendances
users
attendance_logs

// ❌ Bad
Attendance
user
AttendanceLogs
```

### データベースカラム名

#### スネークケース (snake_case)

カラム名はスネークケースを使用します。

```php
// ✅ Good
id
user_id
started_at
ended_at
break_minutes
created_at
updated_at

// ❌ Bad
userId
startedAt
endedAt
```

### ルート名

#### ドット区切り・スネークケース

ルート名はドット区切りのスネークケースを使用します。

```php
// ✅ Good
Route::get('/admin/attendances', [AttendanceController::class, 'index'])
    ->name('admin.attendances.index');

Route::post('/admin/attendances', [AttendanceController::class, 'store'])
    ->name('admin.attendances.store');

Route::get('/admin/attendances/{id}', [AttendanceController::class, 'show'])
    ->name('admin.attendances.show');

// ❌ Bad
->name('adminAttendancesIndex')
->name('admin-attendances-store')
```

### Bool型の変数・メソッド

#### is/has/can で始める

真偽値を返す変数やメソッドは`is`、`has`、`can`で始めます。

```php
// ✅ Good - Bool型の変数
$isApproved = true;
$hasError = false;
$canEdit = true;

// ✅ Good - Bool型を返すメソッド
public function isApproved(): bool {}
public function hasPermission(): bool {}
public function canApprove(): bool {}

// ❌ Bad
$approved = true; // isApprovedにする
$error = false; // hasErrorにする
public function approved(): bool {} // isApprovedにする
```

### Collection/配列の変数名

#### 複数形を使用

複数の要素を持つ変数は複数形を使用します。

```php
// ✅ Good - 複数形
$attendances = Attendance::all();
$users = User::where('active', true)->get();
$statuses = AttendanceStatusEnum::cases();

// ❌ Bad - 単数形
$attendance = Attendance::all();
$user = User::where('active', true)->get();
```

### プライベートプロパティ

プライベートプロパティには特別な接頭辞は不要です。

```php
// ✅ Good - シンプルな命名
class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {}
}

// ❌ Bad - 接頭辞を付けない
class AttendanceService
{
    private readonly AttendanceRepositoryInterface $_attendanceRepository;
    private readonly UserRepositoryInterface $m_userRepository;
}
```

### Inertia.jsのコンポーネント名

フロントエンド（React）のコンポーネント名はパスカルケースを使用します。

```php
// ✅ Good
return Inertia::render('Admin/Attendance/Index', [...]);
return Inertia::render('Admin/Attendance/Create', [...]);
return Inertia::render('Staff/Dashboard', [...]);

// ❌ Bad
return Inertia::render('admin/attendance/index', [...]);
return Inertia::render('Admin/attendance_create', [...]);
```

## 命名規則まとめ

| 対象 | 命名規則 | 例 |
|------|---------|-----|
| 変数名 | camelCase | `$userId`, `$attendanceList` |
| 配列のキー | snake_case | `['user_id' => 1, 'started_at' => '...']` |
| クラス名 | PascalCase | `AttendanceService`, `UserController` |
| メソッド名 | camelCase | `createAttendance()`, `findByStatus()` |
| 定数 | UPPER_SNAKE_CASE | `MAX_WORK_HOURS`, `DEFAULT_BREAK_MINUTES` |
| Enum Case | UPPER_SNAKE_CASE | `PENDING`, `APPROVED` |
| テーブル名 | snake_case (複数形) | `attendances`, `users` |
| カラム名 | snake_case | `user_id`, `started_at` |
| ルート名 | dot.snake_case | `admin.attendances.index` |
| Bool変数/メソッド | is/has/can + camelCase | `$isApproved`, `canApprove()` |
| Collection/配列 | 複数形 | `$attendances`, `$users` |

## エラーハンドリング

詳細は [エラーハンドリング ガイドライン](./error-handling.md) を参照してください。

### 概要

- ビジネス例外はカスタム例外クラスを使用
- ServiceでPHPDocに`@throws`を明示
- Controllerで例外をキャッチしてユーザーに返す
- システム例外はHandler.phpで一元処理

## セキュリティ

詳細は [セキュリティ ガイドライン](./security.md) を参照してください。

### 概要

- Form Requestで必ず入力検証
- Policyで認可チェック
- Eloquent/Query Builderを使用してSQLインジェクション対策
- 機密情報は環境変数で管理
- `$fillable`で Mass Assignment 対策

## パフォーマンス

（内容は後で追加）

## テスト

詳細は [テスト ガイドライン](./testing.md) を参照してください。

### 概要

- Service層を重点的にテスト
- AAA パターンでテストを構造化
- 日本語でテスト名を記述
- Mockeryでモックを作成
- Factoryでテストデータを生成

## ドキュメント

（内容は後で追加）

## 関連ドキュメント

- [Controller ガイドライン](./controller.md)
- [Service ガイドライン](./service.md)
- [Model ガイドライン](./model.md)
- [Repository ガイドライン](./repository.md)
- [Policy ガイドライン](./policy.md)
- [Enum ガイドライン](./enum.md)
- [エラーハンドリング ガイドライン](./error-handling.md)
- [セキュリティ ガイドライン](./security.md)
- [テスト ガイドライン](./testing.md)

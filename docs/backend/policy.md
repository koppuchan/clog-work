# Policy ガイドライン

## 概要

Policyは認可ロジックをカプセル化し、特定のユーザーがリソースに対してアクションを実行できるかを判定します。

## 基本原則

### 単一責任の原則
- 1つのPolicyは1つのModelに対する認可のみを扱う
- 認可ロジックはPolicyに集約し、ControllerやServiceに散在させない

### 明示的な認可
- すべての重要なアクションに対してPolicyを定義
- デフォルトで拒否し、明示的に許可する

## ファイル構造

```
app/Policies/
└── {Model}Policy.php
```

## 命名規則

### クラス名
- 単数形のModel名 + `Policy`
- 例: `AttendancePolicy`, `UserPolicy`

### メソッド名
- RESTfulアクション名を使用
  - `viewAny`: 一覧表示の権限
  - `view`: 詳細表示の権限
  - `create`: 作成の権限
  - `update`: 更新の権限
  - `delete`: 削除の権限
  - `restore`: 復元の権限
  - `forceDelete`: 完全削除の権限

## コード例

### 基本的なPolicy

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attendance;

class AttendancePolicy
{
    /**
     * 一覧表示の権限
     */
    public function viewAny(User $user): bool
    {
        // すべてのログインユーザーが自分の出勤記録を見られる
        return true;
    }

    /**
     * 詳細表示の権限
     */
    public function view(User $user, Attendance $attendance): bool
    {
        // 自分の出勤記録または管理者のみ閲覧可能
        return $user->id === $attendance->user_id || $user->isAdmin();
    }

    /**
     * 作成の権限
     */
    public function create(User $user): bool
    {
        // すべてのログインユーザーが作成可能
        return true;
    }

    /**
     * 更新の権限
     */
    public function update(User $user, Attendance $attendance): bool
    {
        // 自分の出勤記録のみ更新可能
        // ただし、承認済みは更新不可
        return $user->id === $attendance->user_id
            && $attendance->status !== 'approved';
    }

    /**
     * 削除の権限
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        // 自分の出勤記録のみ削除可能
        // ただし、承認済みは削除不可
        return $user->id === $attendance->user_id
            && $attendance->status !== 'approved';
    }

    /**
     * カスタムアクション: 承認の権限
     */
    public function approve(User $user, Attendance $attendance): bool
    {
        // 管理者のみ承認可能
        // ただし、自分の記録は承認できない
        return $user->isAdmin() && $user->id !== $attendance->user_id;
    }

    /**
     * カスタムアクション: 却下の権限
     */
    public function reject(User $user, Attendance $attendance): bool
    {
        // 管理者のみ却下可能
        return $user->isAdmin();
    }
}
```

### Controllerでの使用

```php
<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Http\Requests\UpdateAttendanceRequest;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * 詳細表示
     */
    public function show(int $id)
    {
        $attendance = $this->attendanceService->findAttendance($id);

        // Policyで認可チェック
        $this->authorize('view', $attendance);

        return Inertia::render('Attendance/Show', [
            'attendance' => $attendance,
        ]);
    }

    /**
     * 更新
     */
    public function update(UpdateAttendanceRequest $request, int $id)
    {
        $attendance = $this->attendanceService->findAttendance($id);

        // Policyで認可チェック
        $this->authorize('update', $attendance);

        $this->attendanceService->updateAttendance($id, $request->validated());

        return redirect()->route('attendances.show', $id);
    }

    /**
     * 承認（カスタムアクション）
     */
    public function approve(int $id)
    {
        $attendance = $this->attendanceService->findAttendance($id);

        // カスタムアクションの認可チェック
        $this->authorize('approve', $attendance);

        $this->attendanceService->approveAttendance(
            attendanceId: $id,
            approverId: auth()->id()
        );

        return redirect()->route('attendances.show', $id);
    }
}
```

### Gate での使用（コンストラクタで一括認可）

```php
<?php

namespace App\Http\Controllers;

class AttendanceController extends Controller
{
    public function __construct()
    {
        // リソースコントローラーの認可を一括設定
        $this->authorizeResource(Attendance::class, 'attendance');
    }

    // showメソッドは自動的に 'view' ポリシーでチェックされる
    public function show(Attendance $attendance)
    {
        return Inertia::render('Attendance/Show', [
            'attendance' => $attendance,
        ]);
    }

    // updateメソッドは自動的に 'update' ポリシーでチェックされる
    public function update(UpdateAttendanceRequest $request, Attendance $attendance)
    {
        $this->attendanceService->updateAttendance(
            $attendance->id,
            $request->validated()
        );

        return redirect()->route('attendances.show', $attendance);
    }
}
```

### Bladeでの使用（Inertiaで渡す）

```php
<?php

namespace App\Http\Controllers;

class AttendanceController extends Controller
{
    public function show(int $id)
    {
        $attendance = $this->attendanceService->findAttendance($id);
        $this->authorize('view', $attendance);

        return Inertia::render('Attendance/Show', [
            'attendance' => $attendance,
            // 各アクションの権限をフロントエンドに渡す
            'can' => [
                'update' => auth()->user()->can('update', $attendance),
                'delete' => auth()->user()->can('delete', $attendance),
                'approve' => auth()->user()->can('approve', $attendance),
            ],
        ]);
    }
}
```

### Reactでの使用（ボタンの表示制御）

```tsx
import { Head } from '@inertiajs/react';

interface ShowProps {
  attendance: Attendance;
  can: {
    update: boolean;
    delete: boolean;
    approve: boolean;
  };
}

export default function Show({ attendance, can }: ShowProps) {
  return (
    <>
      <Head title="出勤記録詳細" />

      <div>
        <h1>出勤記録</h1>

        {/* 更新権限がある場合のみ表示 */}
        {can.update && (
          <Link href={`/attendances/${attendance.id}/edit`}>
            編集
          </Link>
        )}

        {/* 承認権限がある場合のみ表示 */}
        {can.approve && (
          <button onClick={() => handleApprove()}>
            承認
          </button>
        )}

        {/* 削除権限がある場合のみ表示 */}
        {can.delete && (
          <button onClick={() => handleDelete()}>
            削除
          </button>
        )}
      </div>
    </>
  );
}
```

## ベストプラクティス

### 1. 複雑な条件はprivateメソッドに分離

```php
class AttendancePolicy
{
    public function update(User $user, Attendance $attendance): bool
    {
        return $this->isOwner($user, $attendance)
            && $this->isEditable($attendance);
    }

    private function isOwner(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    private function isEditable(Attendance $attendance): bool
    {
        return $attendance->status !== 'approved';
    }
}
```

### 2. 早期リターンで可読性向上

```php
public function update(User $user, Attendance $attendance): bool
{
    // 管理者は常に更新可能
    if ($user->isAdmin()) {
        return true;
    }

    // 自分の記録でない場合は拒否
    if ($user->id !== $attendance->user_id) {
        return false;
    }

    // 承認済みは更新不可
    if ($attendance->status === 'approved') {
        return false;
    }

    return true;
}
```

### 3. 役割ベースの権限チェック

```php
class AttendancePolicy
{
    public function approve(User $user, Attendance $attendance): bool
    {
        // 役割で判定
        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function viewAny(User $user): bool
    {
        // 権限で判定
        return $user->hasPermission('view-attendances');
    }
}
```

## アンチパターン

### ❌ ビジネスロジックをPolicyに書く

```php
// Bad - ビジネスロジックが混在
public function update(User $user, Attendance $attendance): bool
{
    // 勤務時間の計算などはServiceで行うべき
    $workingHours = $attendance->calculateWorkingHours();

    if ($workingHours > 12) {
        return false;
    }

    return $user->id === $attendance->user_id;
}

// Good - 認可のみに専念
public function update(User $user, Attendance $attendance): bool
{
    return $user->id === $attendance->user_id
        && $attendance->status !== 'approved';
}
```

### ❌ Controllerで認可ロジックを書く

```php
// Bad
public function update(Request $request, int $id)
{
    $attendance = Attendance::findOrFail($id);

    if (auth()->id() !== $attendance->user_id) {
        abort(403);
    }

    // 更新処理
}

// Good
public function update(Request $request, int $id)
{
    $attendance = $this->attendanceService->findAttendance($id);

    $this->authorize('update', $attendance);

    // 更新処理
}
```

## Policyの登録

### AuthServiceProviderで自動検出

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * ポリシーマッピング（省略可能 - 自動検出される）
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Model => Policy の明示的なマッピング
        // Attendance::class => AttendancePolicy::class,
    ];

    public function boot(): void
    {
        // Policyの自動検出を使用（推奨）
        // app/Policies/ ディレクトリのPolicyが自動で登録される
    }
}
```

## まとめ

- Policyで認可ロジックを一元管理
- `authorize()`メソッドでControllerから呼び出す
- `authorizeResource()`でリソースコントローラーの認可を一括設定
- フロントエンドに権限情報を渡してUIを制御
- ビジネスロジックとは分離し、認可のみに専念

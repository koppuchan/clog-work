# Controller ガイドライン

## 概要

ControllerはHTTPリクエストを受け取り、適切なServiceを呼び出し、Inertia.jsを通じてReactコンポーネントにレスポンスを返す責務を持ちます。

## 基本原則

### 単一責任の原則
- 1つのControllerは1つのリソースに対する操作のみを扱う
- ビジネスロジックはServiceレイヤーに委譲する
- Controllerは薄く保ち、リクエスト/レスポンスの制御に専念する

### RESTful設計
- 標準的なRESTfulアクションを使用する（index, show, create, store, edit, update, destroy）
- カスタムアクションは必要最小限に留める

## ファイル構造

```
app/Http/Controllers/
├── Controller.php          # ベースコントローラー
├── Admin/                  # 管理者用
│   └── {Resource}Controller.php
└── Staff/                  # スタッフ用
    └── {Resource}Controller.php
```

## 命名規則

### クラス名
- 単数形のリソース名 + `Controller`
- 例: `AttendanceController`, `UserController`

### メソッド名
- RESTfulアクション名を使用
  - `index`: 一覧表示
  - `show`: 詳細表示
  - `create`: 作成フォーム表示
  - `store`: 新規作成処理
  - `edit`: 編集フォーム表示
  - `update`: 更新処理
  - `destroy`: 削除処理

## コード例

### 基本的なController

```php
<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService
    ) {}

    /**
     * 出勤記録一覧を表示
     */
    public function index(Request $request): Response
    {
        $attendances = $this->attendanceService->getAttendances(
            userId: $request->user()->id,
            filters: $request->only(['date_from', 'date_to', 'status'])
        );

        return Inertia::render('Attendance/Index', [
            'attendances' => $attendances,
            'filters' => $request->only(['date_from', 'date_to', 'status']),
        ]);
    }

    /**
     * 出勤記録詳細を表示
     */
    public function show(int $id): Response
    {
        $attendance = $this->attendanceService->findAttendance($id);

        return Inertia::render('Attendance/Show', [
            'attendance' => $attendance,
        ]);
    }

    /**
     * 新規出勤記録を作成
     */
    public function store(StoreAttendanceRequest $request)
    {
        $attendance = $this->attendanceService->createAttendance(
            userId: $request->user()->id,
            data: $request->validated()
        );

        return redirect()
            ->route('attendances.show', $attendance->id)
            ->with('success', '出勤記録を作成しました。');
    }

    /**
     * 出勤記録を更新
     */
    public function update(UpdateAttendanceRequest $request, int $id)
    {
        $this->attendanceService->updateAttendance(
            id: $id,
            data: $request->validated()
        );

        return redirect()
            ->route('attendances.show', $id)
            ->with('success', '出勤記録を更新しました。');
    }

    /**
     * 出勤記録を削除
     */
    public function destroy(int $id)
    {
        $this->attendanceService->deleteAttendance($id);

        return redirect()
            ->route('attendances.index')
            ->with('success', '出勤記録を削除しました。');
    }
}
```

### Form Requestの使用

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 認可はポリシーで行う
    }

    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'started_at.required' => '出勤時刻は必須です。',
            'ended_at.after' => '退勤時刻は出勤時刻より後の時刻を指定してください。',
        ];
    }
}
```

## ベストプラクティス

### 1. 依存性注入を使用する

```php
// Good
public function __construct(
    private readonly AttendanceService $attendanceService,
    private readonly UserService $userService
) {}

// Bad - Facadeの使用は最小限に
public function index()
{
    $data = DB::table('attendances')->get(); // NG
}
```

### 2. Inertia.jsでデータを渡す

```php
// Good - 必要なデータのみを渡す
return Inertia::render('Attendance/Index', [
    'attendances' => $attendances,
    'statistics' => $this->attendanceService->getStatistics(),
]);

// Bad - Modelをそのまま渡さない（N+1問題の原因）
return Inertia::render('Attendance/Index', [
    'attendances' => Attendance::all(), // NG
]);
```

### 3. 認可はPolicyを使用

```php
public function update(UpdateAttendanceRequest $request, int $id)
{
    $attendance = $this->attendanceService->findAttendance($id);

    // Policyで認可チェック
    $this->authorize('update', $attendance);

    $this->attendanceService->updateAttendance($id, $request->validated());

    return redirect()->route('attendances.show', $id);
}
```

### 4. リダイレクト時にフラッシュメッセージを使用

```php
return redirect()
    ->route('attendances.index')
    ->with('success', '出勤記録を作成しました。');

// エラー時
return redirect()
    ->back()
    ->withErrors(['error' => '処理に失敗しました。'])
    ->withInput();
```

### 5. 例外処理

ビジネス例外はControllerでキャッチし、ユーザーフレンドリーなエラーメッセージを表示します。

#### ビジネス例外のキャッチ

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Http\Requests\StoreAttendanceRequest;
use Illuminate\Http\RedirectResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService
    ) {}

    /**
     * 出勤記録を作成
     */
    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        try {
            $attendance = $this->attendanceService->createAttendance(
                userId: $request->user()->id,
                data: $request->validated()
            );

            return redirect()
                ->route('admin.attendances.show', $attendance->id)
                ->with('success', '出勤記録を作成しました。');

        } catch (BusinessException $e) {
            // ビジネスロジック違反の場合
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * 出勤記録を承認
     */
    public function approve(int $id): RedirectResponse
    {
        try {
            $this->attendanceService->approve($id);

            return redirect()
                ->route('admin.attendances.show', $id)
                ->with('success', '承認しました。');

        } catch (BusinessException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
```

#### 複数の例外タイプをキャッチ

```php
use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;

public function update(UpdateAttendanceRequest $request, int $id): RedirectResponse
{
    try {
        $this->attendanceService->updateAttendance(
            id: $id,
            data: $request->validated()
        );

        return redirect()
            ->route('admin.attendances.show', $id)
            ->with('success', '更新しました。');

    } catch (NotFoundException $e) {
        // リソースが見つからない
        return redirect()
            ->route('admin.attendances.index')
            ->withErrors(['error' => 'データが見つかりませんでした。']);

    } catch (UnauthorizedException $e) {
        // 権限がない
        return redirect()
            ->back()
            ->withErrors(['error' => '権限がありません。']);

    } catch (BusinessException $e) {
        // その他のビジネス例外
        return redirect()
            ->back()
            ->withErrors(['error' => $e->getMessage()])
            ->withInput();
    }
}
```

#### Inertia.jsでのエラーハンドリング

```php
use Inertia\Inertia;
use Inertia\Response;

public function create(): Response
{
    try {
        $users = $this->userService->getActiveUsers();

        return Inertia::render('Admin/Attendance/Create', [
            'users' => $users,
        ]);

    } catch (BusinessException $e) {
        return Inertia::render('Admin/Attendance/Create', [
            'users' => [],
            'error' => $e->getMessage(),
        ]);
    }
}
```

#### Service側での例外スロー

```php
<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\Attendance;

class AttendanceService
{
    /**
     * 出勤記録を作成
     *
     * @throws BusinessException ビジネスルール違反時
     */
    public function createAttendance(int $userId, array $data): Attendance
    {
        // 重複チェック
        if ($this->hasOverlappingAttendance($userId, $data['started_at'])) {
            throw new BusinessException('既に出勤記録が存在します。');
        }

        return DB::transaction(function () use ($userId, $data) {
            return $this->attendanceRepository->create([
                'user_id' => $userId,
                ...$data,
            ]);
        });
    }

    /**
     * 出勤記録を承認
     *
     * @throws NotFoundException データが見つからない場合
     * @throws BusinessException ビジネスルール違反時
     */
    public function approve(int $id): Attendance
    {
        $attendance = $this->attendanceRepository->find($id);

        if (!$attendance) {
            throw new NotFoundException('出勤記録が見つかりません。');
        }

        if ($attendance->status !== AttendanceStatusEnum::PENDING) {
            throw new BusinessException('承認待ち以外の記録は承認できません。');
        }

        if (!$attendance->ended_at) {
            throw new BusinessException('退勤記録がない記録は承認できません。');
        }

        return $this->attendanceRepository->update($id, [
            'status' => AttendanceStatusEnum::APPROVED,
        ]);
    }
}
```

#### カスタム例外クラス

```php
<?php

namespace App\Exceptions;

use Exception;

/**
 * ビジネスロジック違反の例外
 */
class BusinessException extends Exception
{
    public function __construct(string $message = '', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
```

```php
<?php

namespace App\Exceptions;

use Exception;

/**
 * リソースが見つからない例外
 */
class NotFoundException extends Exception
{
    public function __construct(string $message = 'リソースが見つかりません。', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
```

#### 例外処理のまとめ

- ✅ **ビジネス例外はControllerでキャッチ**
- ✅ **ユーザーフレンドリーなメッセージを返す**
- ✅ **例外タイプごとに適切な処理を行う**
- ✅ **ServiceではPHPDocで例外を明示**
- ❌ **システム例外（DB接続エラーなど）はキャッチしない（Handler.phpに委譲）**

## アンチパターン

### ❌ ビジネスロジックをControllerに書く

```php
// Bad
public function store(Request $request)
{
    // ビジネスロジックがControllerに書かれている
    $user = $request->user();
    $lastAttendance = Attendance::where('user_id', $user->id)
        ->orderBy('started_at', 'desc')
        ->first();

    if ($lastAttendance && !$lastAttendance->ended_at) {
        return back()->withErrors(['error' => '既に出勤中です。']);
    }

    $attendance = Attendance::create([
        'user_id' => $user->id,
        'started_at' => now(),
    ]);

    return redirect()->route('attendances.show', $attendance->id);
}

// Good - Serviceに委譲
public function store(StoreAttendanceRequest $request)
{
    $attendance = $this->attendanceService->clockIn(
        userId: $request->user()->id
    );

    return redirect()->route('attendances.show', $attendance->id);
}
```

### ❌ 直接Modelを操作する

```php
// Bad
public function destroy(int $id)
{
    Attendance::find($id)->delete();
    return redirect()->route('attendances.index');
}

// Good
public function destroy(int $id)
{
    $this->attendanceService->deleteAttendance($id);
    return redirect()->route('attendances.index');
}
```

## まとめ

- Controllerは薄く保つ
- ビジネスロジックはServiceに委譲
- Form Requestでバリデーション
- Policyで認可
- Inertia.jsで型安全なデータ受け渡し
- 名前付きルートを使用したリダイレクト

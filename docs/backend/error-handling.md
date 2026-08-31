# エラーハンドリング ガイドライン

## 概要

アプリケーション全体で一貫したエラーハンドリングを行うためのガイドラインです。

## 基本原則

### 例外の分類

1. **ビジネス例外**: ビジネスロジック違反（ユーザーに表示）
2. **システム例外**: データベース接続エラー、ファイルI/Oエラーなど（ログ記録）
3. **バリデーション例外**: 入力値の検証エラー（Form Requestで処理）

### 例外処理の責務分担

```
Controller: ビジネス例外をキャッチしてユーザーに返す
    ↓
Service: ビジネスロジックで例外をスロー
    ↓
Repository: データアクセスエラーは上位に委譲
    ↓
Handler.php: システム例外を一元的に処理
```

## カスタム例外クラス

### ディレクトリ構造

```
app/Exceptions/
├── BusinessException.php
├── NotFoundException.php
├── UnauthorizedException.php
└── ValidationException.php
```

### BusinessException

ビジネスロジック違反時に使用します。

```php
<?php

namespace App\Exceptions;

use Exception;

/**
 * ビジネスロジック違反の例外
 */
class BusinessException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * ユーザーに表示可能なメッセージかどうか
     */
    public function isUserFriendly(): bool
    {
        return true;
    }
}
```

### NotFoundException

リソースが見つからない場合に使用します。

```php
<?php

namespace App\Exceptions;

use Exception;

/**
 * リソースが見つからない例外
 */
class NotFoundException extends Exception
{
    public function __construct(
        string $message = 'リソースが見つかりません。',
        int $code = 404,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```

### UnauthorizedException

権限がない場合に使用します。

```php
<?php

namespace App\Exceptions;

use Exception;

/**
 * 権限がない例外
 */
class UnauthorizedException extends Exception
{
    public function __construct(
        string $message = '権限がありません。',
        int $code = 403,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```

## Serviceでの例外スロー

### 基本パターン

```php
<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Repositories\Interfaces\AttendanceRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository
    ) {}

    /**
     * 出勤記録を作成
     *
     * @param int $userId ユーザーID
     * @param array $data 出勤記録データ
     * @return Attendance 作成された出勤記録
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
                'status' => AttendanceStatusEnum::PENDING,
            ]);
        });
    }

    /**
     * 出勤記録を承認
     *
     * @param int $id 出勤記録ID
     * @return Attendance 承認された出勤記録
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

    /**
     * 出勤記録を削除
     *
     * @param int $id 出勤記録ID
     * @return void
     * @throws NotFoundException データが見つからない場合
     * @throws BusinessException 削除できない状態の場合
     */
    public function deleteAttendance(int $id): void
    {
        $attendance = $this->attendanceRepository->find($id);

        if (!$attendance) {
            throw new NotFoundException('出勤記録が見つかりません。');
        }

        if ($attendance->status === AttendanceStatusEnum::APPROVED) {
            throw new BusinessException('承認済みの記録は削除できません。');
        }

        $this->attendanceRepository->delete($id);
    }

    /**
     * 重複する出勤記録があるかチェック
     */
    private function hasOverlappingAttendance(int $userId, string $startedAt): bool
    {
        return $this->attendanceRepository->existsOverlapping($userId, $startedAt);
    }
}
```

### PHPDocで例外を明示

全てのpublicメソッドで`@throws`を使用します。

```php
/**
 * 出勤記録を更新
 *
 * @param int $id 出勤記録ID
 * @param array $data 更新データ
 * @return Attendance 更新された出勤記録
 * @throws NotFoundException データが見つからない場合
 * @throws UnauthorizedException 権限がない場合
 * @throws BusinessException ビジネスルール違反時
 */
public function updateAttendance(int $id, array $data): Attendance
{
    // ...
}
```

## Controllerでの例外処理

### 基本パターン

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Services\AttendanceService;
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
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * 出勤記録を更新
     */
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
            return redirect()
                ->route('admin.attendances.index')
                ->withErrors(['error' => 'データが見つかりませんでした。']);

        } catch (BusinessException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * 出勤記録を削除
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->attendanceService->deleteAttendance($id);

            return redirect()
                ->route('admin.attendances.index')
                ->with('success', '削除しました。');

        } catch (NotFoundException $e) {
            return redirect()
                ->route('admin.attendances.index')
                ->withErrors(['error' => 'データが見つかりませんでした。']);

        } catch (BusinessException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
```

### Inertia.jsでのエラーハンドリング

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

## Handler.phpでの一元処理

### システム例外の処理

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * 例外のレポート
     */
    public function report(Throwable $e): void
    {
        // システム例外のみログ記録
        if (!($e instanceof BusinessException)) {
            parent::report($e);
        }
    }

    /**
     * 例外のレンダリング
     */
    public function render($request, Throwable $e)
    {
        // ModelNotFoundExceptionを404に変換
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'リソースが見つかりません。',
            ], 404);
        }

        // NotFoundHttpExceptionを404に変換
        if ($e instanceof NotFoundHttpException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'ページが見つかりません。',
                ], 404);
            }

            return response()->view('errors.404', [], 404);
        }

        return parent::render($request, $e);
    }
}
```

## ベストプラクティス

### ✅ Good

```php
// 具体的なエラーメッセージ
throw new BusinessException('2024-01-01 09:00 の出勤記録が既に存在します。');

// PHPDocで例外を明示
/**
 * @throws BusinessException
 */
public function approve(int $id): Attendance
{
    // ...
}

// 例外タイプごとに適切な処理
try {
    // ...
} catch (NotFoundException $e) {
    // 404エラー処理
} catch (BusinessException $e) {
    // ビジネスエラー処理
}

// 早期return
if (!$attendance) {
    throw new NotFoundException('出勤記録が見つかりません。');
}
```

### ❌ Bad

```php
// 曖昧なエラーメッセージ
throw new BusinessException('エラーが発生しました。');

// PHPDocなし
public function approve(int $id): Attendance
{
    // ...
}

// 全ての例外を同じように処理
try {
    // ...
} catch (Exception $e) {
    // 全て同じ処理
}

// システム例外をキャッチ
try {
    // ...
} catch (\PDOException $e) {
    // DBエラーをキャッチすべきでない
}
```

## エラーメッセージのガイドライン

### ユーザーフレンドリーなメッセージ

```php
// ✅ Good - 具体的で解決策が分かる
throw new BusinessException('出勤時刻は退勤時刻より前の時刻を指定してください。');
throw new BusinessException('2024-01-01 09:00 の出勤記録が既に存在します。');
throw new BusinessException('承認待ち以外の記録は承認できません。現在のステータス: 承認済み');

// ❌ Bad - 曖昧で解決策が分からない
throw new BusinessException('無効な値です。');
throw new BusinessException('エラーが発生しました。');
throw new BusinessException('処理できません。');
```

### 日本語メッセージ

ユーザーに表示するメッセージは日本語で記述します。

```php
// ✅ Good
throw new BusinessException('既に出勤記録が存在します。');

// ❌ Bad
throw new BusinessException('Attendance already exists.');
```

## まとめ

- ✅ **ビジネス例外はカスタム例外クラスを使用**
- ✅ **ServiceでPHPDocに`@throws`を明示**
- ✅ **Controllerで例外をキャッチしてユーザーに返す**
- ✅ **システム例外はHandler.phpで一元処理**
- ✅ **具体的でユーザーフレンドリーなエラーメッセージ**
- ❌ **システム例外をControllerでキャッチしない**
- ❌ **曖昧なエラーメッセージは使用しない**

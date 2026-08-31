# セキュリティ ガイドライン

## 概要

アプリケーションのセキュリティを確保するためのガイドラインです。

## 基本原則

### 多層防御

複数のセキュリティレイヤーで保護します：

1. **入力検証**: Form Requestでバリデーション
2. **認証**: Laravelの認証機能
3. **認可**: Policy
4. **データ保護**: SQLインジェクション、XSS対策
5. **ログ記録**: セキュリティイベントの記録

## 認証 (Authentication)

### ユーザー認証

Laravel標準の認証機能を使用します。

```php
// ✅ Good - 認証済みユーザーの取得
$user = $request->user();

// ✅ Good - 認証チェック
if (!auth()->check()) {
    return redirect()->route('login');
}

// ❌ Bad - セッションから直接取得
$userId = session('user_id'); // 使用しない
```

### パスワード管理

```php
use Illuminate\Support\Facades\Hash;

// ✅ Good - パスワードのハッシュ化
$user->password = Hash::make($request->input('password'));

// ✅ Good - パスワードの検証
if (Hash::check($request->input('password'), $user->password)) {
    // 認証成功
}

// ❌ Bad - 平文パスワード
$user->password = $request->input('password'); // 絶対に使用しない
```

### パスワードポリシー

```php
use Illuminate\Validation\Rules\Password;

public function rules(): array
{
    return [
        'password' => [
            'required',
            'confirmed',
            Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(),
        ],
    ];
}
```

## 認可 (Authorization)

### Policyの使用

必ずPolicyで認可チェックを行います。

```php
// ✅ Good - Policyを使用
public function update(UpdateAttendanceRequest $request, int $id): RedirectResponse
{
    $attendance = $this->attendanceService->findAttendance($id);

    // 認可チェック
    $this->authorize('update', $attendance);

    $this->attendanceService->updateAttendance($id, $request->validated());

    return redirect()->route('admin.attendances.show', $id);
}

// ❌ Bad - 手動で権限チェック
public function update(UpdateAttendanceRequest $request, int $id): RedirectResponse
{
    $attendance = $this->attendanceService->findAttendance($id);

    if ($request->user()->id !== $attendance->user_id) {
        abort(403);
    }

    // ...
}
```

### Gate の使用

特定の機能へのアクセス制御にGateを使用します。

```php
use Illuminate\Support\Facades\Gate;

// Gate定義（AuthServiceProvider）
Gate::define('approve-attendance', function (User $user) {
    return $user->role === UserRoleEnum::ADMIN
        || $user->role === UserRoleEnum::SUPER_ADMIN;
});

// Controller
public function approve(int $id): RedirectResponse
{
    if (!Gate::allows('approve-attendance')) {
        abort(403);
    }

    $this->attendanceService->approve($id);

    return redirect()->route('admin.attendances.show', $id);
}
```

## 入力検証

### Form Requestの使用

全ての入力値をForm Requestで検証します。

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 認可はPolicyで行うためtrueを返す
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date', 'before:ended_at'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // 入力値のサニタイズ
        $this->merge([
            'note' => strip_tags($this->note),
        ]);
    }
}
```

### XSS対策

Bladeテンプレートでは自動的にエスケープされますが、Inertia.jsでも注意が必要です。

```php
// ✅ Good - エスケープされたデータを渡す
return Inertia::render('Admin/Attendance/Show', [
    'attendance' => [
        'id' => $attendance->id,
        'note' => e($attendance->note), // HTMLエスケープ
    ],
]);

// React側でもエスケープ
<div>{attendance.note}</div> {/* Reactが自動エスケープ */}

// ❌ Bad - dangerouslySetInnerHTMLの使用
<div dangerouslySetInnerHTML={{ __html: attendance.note }} /> {/* 危険 */}
```

## SQLインジェクション対策

### Eloquent/Query Builderの使用

必ずEloquentまたはQuery Builderを使用します。

```php
// ✅ Good - Eloquent（自動的にエスケープ）
$attendances = Attendance::where('user_id', $userId)->get();

// ✅ Good - Query Builder（バインディング使用）
$attendances = DB::table('attendances')
    ->where('user_id', $userId)
    ->get();

// ✅ Good - 名前付きバインディング
$attendances = DB::select('SELECT * FROM attendances WHERE user_id = :userId', [
    'userId' => $userId,
]);

// ❌ Bad - 生のSQL（SQLインジェクションの危険）
$attendances = DB::select("SELECT * FROM attendances WHERE user_id = {$userId}");
```

### LIKE検索のエスケープ

```php
use Illuminate\Support\Str;

// ✅ Good - LIKE検索のエスケープ
$keyword = Str::replace(['%', '_'], ['\\%', '\\_'], $request->input('keyword'));
$users = User::where('name', 'LIKE', "%{$keyword}%")->get();

// ❌ Bad - エスケープなし
$keyword = $request->input('keyword');
$users = User::where('name', 'LIKE', "%{$keyword}%")->get();
```

## CSRF対策

### フォーム送信

Laravelが自動的にCSRFトークンを検証します。

```php
// Inertia.jsでは自動的にCSRFトークンが含まれる
import { useForm } from '@inertiajs/react';

const form = useForm({
    started_at: '',
    ended_at: '',
});

form.post('/admin/attendances'); // CSRFトークン自動付与
```

### API エンドポイント

APIではCSRF対策の代わりにトークン認証を使用します。

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/attendances', [AttendanceController::class, 'index']);
});
```

## Mass Assignment対策

### fillableの使用

必ず`$fillable`または`$guarded`を定義します。

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    // ✅ Good - fillableで許可するカラムを明示
    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
        'break_minutes',
        'status',
        'note',
    ];

    // ❌ Bad - guarded = [] は危険
    // protected $guarded = [];
}
```

### Controllerでの検証

```php
// ✅ Good - Form Requestで検証済みのデータのみ使用
public function store(StoreAttendanceRequest $request): RedirectResponse
{
    $attendance = $this->attendanceService->createAttendance(
        userId: $request->user()->id,
        data: $request->validated() // 検証済みデータのみ
    );

    return redirect()->route('admin.attendances.show', $attendance->id);
}

// ❌ Bad - 全てのリクエストデータを使用
public function store(Request $request): RedirectResponse
{
    $attendance = Attendance::create($request->all()); // 危険

    return redirect()->route('admin.attendances.show', $attendance->id);
}
```

## 機密データの保護

### 環境変数の使用

機密情報は必ず`.env`ファイルで管理します。

```php
// ✅ Good - 環境変数から取得
$apiKey = config('services.external_api.key');

// .env
EXTERNAL_API_KEY=your-secret-key

// config/services.php
'external_api' => [
    'key' => env('EXTERNAL_API_KEY'),
],

// ❌ Bad - ハードコード
$apiKey = 'your-secret-key'; // 絶対に使用しない
```

### ログ出力時の注意

```php
use Illuminate\Support\Facades\Log;

// ✅ Good - 機密情報をマスク
Log::info('User logged in', [
    'user_id' => $user->id,
    'email' => Str::mask($user->email, '*', 3),
]);

// ❌ Bad - 機密情報をそのまま出力
Log::info('User logged in', [
    'user_id' => $user->id,
    'password' => $password, // 絶対に使用しない
]);
```

### Modelでの機密データ非表示

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // ✅ Good - 機密情報を非表示
    protected $hidden = [
        'password',
        'remember_token',
    ];
}
```

## ファイルアップロード

### バリデーション

```php
public function rules(): array
{
    return [
        'avatar' => [
            'required',
            'file',
            'image',
            'max:2048', // 2MB
            'mimes:jpeg,png,jpg',
            'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
        ],
    ];
}
```

### セキュアなファイル保存

```php
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// ✅ Good - ランダムなファイル名で保存
public function uploadAvatar(UploadedFile $file): string
{
    $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs('avatars', $filename, 'private');

    return $path;
}

// ❌ Bad - オリジナルのファイル名を使用
public function uploadAvatar(UploadedFile $file): string
{
    $path = $file->store('avatars', 'public'); // パストラバーサルの危険

    return $path;
}
```

## レート制限

### APIのレート制限

```php
use Illuminate\Support\Facades\RateLimiter;

// RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/attendances', [AttendanceController::class, 'index']);
});
```

### ログインのレート制限

```php
// RouteServiceProvider
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->input('email') . $request->ip());
});

// routes/web.php
Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:login');
```

## HTTPSの強制

### ミドルウェアで強制

```php
// app/Http/Middleware/ForceHttps.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
```

## セキュリティヘッダー

### ミドルウェアで設定

```php
// app/Http/Middleware/SecurityHeaders.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
```

## 監査ログ

### セキュリティイベントの記録

```php
use Illuminate\Support\Facades\Log;

// ✅ Good - セキュリティイベントをログ記録
public function login(LoginRequest $request): RedirectResponse
{
    if (auth()->attempt($request->validated())) {
        Log::info('User logged in', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('dashboard');
    }

    Log::warning('Login failed', [
        'email' => $request->input('email'),
        'ip' => $request->ip(),
    ]);

    return back()->withErrors(['email' => '認証情報が正しくありません。']);
}
```

## まとめ

- ✅ **Form Requestで必ず入力検証**
- ✅ **Policyで認可チェック**
- ✅ **Eloquent/Query Builderを使用してSQLインジェクション対策**
- ✅ **機密情報は環境変数で管理**
- ✅ **`$fillable`で Mass Assignment 対策**
- ✅ **レート制限を設定**
- ✅ **セキュリティイベントをログ記録**
- ❌ **生のSQLは使用しない**
- ❌ **機密情報をハードコードしない**
- ❌ **`$guarded = []` は使用しない**

# Enum ガイドライン

## 概要

Enumは定数値をタイプセーフに管理するための仕組みです。PHP 8.1以降で導入されたネイティブEnumを使用し、マジックナンバーやマジック文字列を排除します。

## 基本原則

### 使用すべき場面

- 固定された選択肢がある場合（ステータス、種別など）
- マジックナンバー/文字列を排除したい場合
- 型安全性を確保したい場合

### 使用すべきでない場面

- 動的に変更される値
- データベースから取得する値
- ユーザー入力値

## ファイル構造

```
app/Enums/
└── {Name}Enum.php
```

## 命名規則

### ファイル名・クラス名

- 単数形 + `Enum`サフィックス
- 例: `AttendanceStatusEnum`, `UserRoleEnum`

### Case名

- UPPER_SNAKE_CASE
- 例: `PENDING`, `APPROVED`, `REJECTED`

## 基本的なEnum

### String Backed Enum

文字列値を持つEnumを定義します（推奨）。

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '承認待ち',
            self::APPROVED => '承認済み',
            self::REJECTED => '却下',
        };
    }

    /**
     * 承認可能かどうか
     */
    public function canApprove(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 却下可能かどうか
     */
    public function canReject(): bool
    {
        return $this === self::PENDING;
    }
}
```

### Integer Backed Enum

整数値を持つEnumを定義します。

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRoleEnum: int
{
    case STAFF = 1;
    case ADMIN = 2;
    case SUPER_ADMIN = 3;

    public function label(): string
    {
        return match ($this) {
            self::STAFF => 'スタッフ',
            self::ADMIN => '管理者',
            self::SUPER_ADMIN => 'システム管理者',
        };
    }

    public function hasAdminPrivilege(): bool
    {
        return $this === self::ADMIN || $this === self::SUPER_ADMIN;
    }
}
```

## Modelでの使用

### キャストの定義

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatusEnum::class,
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
```

### 使用例

```php
// ✅ Good - Enumとして扱える
$attendance = Attendance::find(1);
echo $attendance->status->label(); // "承認待ち"

if ($attendance->status->canApprove()) {
    // 承認処理
}

// ✅ Good - Enumで保存
$attendance->status = AttendanceStatusEnum::APPROVED;
$attendance->save();

// ❌ Bad - 文字列で直接指定
$attendance->status = 'approved'; // 型安全性が失われる
```

## Serviceでの使用

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Repositories\Interfaces\AttendanceRepositoryInterface;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository
    ) {}

    /**
     * 出勤記録を承認する
     */
    public function approve(int $id): Attendance
    {
        $attendance = $this->attendanceRepository->findOrFail($id);

        // ✅ Good - Enumのメソッドで判定
        if (!$attendance->status->canApprove()) {
            throw new BusinessException('この記録は承認できません。');
        }

        return $this->attendanceRepository->update($id, [
            'status' => AttendanceStatusEnum::APPROVED,
        ]);
    }

    /**
     * ステータスでフィルタリング
     */
    public function getAttendances(array $filters): Collection
    {
        // ✅ Good - Enumの値を渡す
        return $this->attendanceRepository->findByFilters([
            'status' => $filters['status'] ?? null,
        ]);
    }
}
```

## Repositoryでの使用

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Collection;

class AttendanceRepository implements AttendanceRepositoryInterface
{
    public function __construct(
        private readonly Attendance $model
    ) {}

    /**
     * ステータスで検索
     */
    public function findByStatus(AttendanceStatusEnum $status): Collection
    {
        // ✅ Good - Enumの値が自動的に文字列に変換される
        return $this->model->query()
            ->where('status', $status)
            ->get();
    }

    /**
     * 承認待ちの記録を取得
     */
    public function findPending(): Collection
    {
        return $this->model->query()
            ->where('status', AttendanceStatusEnum::PENDING)
            ->get();
    }
}
```

## Controllerでの使用

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatusEnum;
use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService
    ) {}

    /**
     * 一覧表示
     */
    public function index(Request $request)
    {
        $attendances = $this->attendanceService->getAttendances(
            filters: $request->only(['status'])
        );

        return Inertia::render('Admin/Attendance/Index', [
            'attendances' => $attendances,
            // ✅ Good - フロントエンドに選択肢を渡す
            'statusOptions' => $this->getStatusOptions(),
        ]);
    }

    /**
     * 承認
     */
    public function approve(int $id)
    {
        $this->attendanceService->approve($id);

        return redirect()
            ->route('admin.attendances.show', $id)
            ->with('success', '承認しました。');
    }

    /**
     * ステータス選択肢を取得
     */
    private function getStatusOptions(): array
    {
        return array_map(
            fn(AttendanceStatusEnum $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            AttendanceStatusEnum::cases()
        );
    }
}
```

## Form Requestでのバリデーション

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AttendanceStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            // ✅ Good - Enumのバリデーション
            'status' => ['required', Rule::enum(AttendanceStatusEnum::class)],
        ];
    }
}
```

## 便利なメソッド

### 全ケースを取得

```php
// 全てのケースを配列で取得
$allStatuses = AttendanceStatusEnum::cases();

// 値の配列を取得
$statusValues = array_map(
    fn($status) => $status->value,
    AttendanceStatusEnum::cases()
);
```

### 文字列から生成

```php
// ✅ Good - tryFromで安全に変換
$status = AttendanceStatusEnum::tryFrom('pending');
if ($status === null) {
    // 無効な値の処理
}

// ✅ Good - fromで変換（無効な値の場合は例外）
try {
    $status = AttendanceStatusEnum::from('pending');
} catch (\ValueError $e) {
    // 無効な値の処理
}

// ❌ Bad - 文字列のまま使用
$status = 'pending'; // 型安全性が失われる
```

### カスタムメソッド

```php
<?php

namespace App\Enums;

enum AttendanceStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * 最終ステータスかどうか
     */
    public function isFinal(): bool
    {
        return $this === self::APPROVED || $this === self::REJECTED;
    }

    /**
     * 次に遷移可能なステータスを取得
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::PENDING => [self::APPROVED, self::REJECTED],
            self::APPROVED, self::REJECTED => [],
        };
    }

    /**
     * CSSクラス名を取得
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::PENDING => 'text-yellow-600',
            self::APPROVED => 'text-green-600',
            self::REJECTED => 'text-red-600',
        };
    }
}
```

## Inertia.jsとの連携

### バックエンド

```php
return Inertia::render('Admin/Attendance/Index', [
    'attendances' => $attendances->map(fn($attendance) => [
        'id' => $attendance->id,
        'status' => $attendance->status->value,
        'statusLabel' => $attendance->status->label(),
        'statusCssClass' => $attendance->status->cssClass(),
    ]),
]);
```

### フロントエンド (TypeScript)

```typescript
// Enumを定義
export enum AttendanceStatus {
  PENDING = 'pending',
  APPROVED = 'approved',
  REJECTED = 'rejected',
}

// ラベルマッピング
export const attendanceStatusLabels: Record<AttendanceStatus, string> = {
  [AttendanceStatus.PENDING]: '承認待ち',
  [AttendanceStatus.APPROVED]: '承認済み',
  [AttendanceStatus.REJECTED]: '却下',
};

// 使用例
interface Attendance {
  id: number;
  status: AttendanceStatus;
  statusLabel: string;
  statusCssClass: string;
}

export default function AttendanceList({ attendances }: { attendances: Attendance[] }) {
  return (
    <div>
      {attendances.map((attendance) => (
        <div key={attendance.id}>
          <span className={attendance.statusCssClass}>
            {attendance.statusLabel}
          </span>
        </div>
      ))}
    </div>
  );
}
```

## ベストプラクティス

### ✅ Good

```php
// Enumで型安全に
if ($attendance->status === AttendanceStatusEnum::APPROVED) {
    // ...
}

// Enumのメソッドを活用
if ($attendance->status->canApprove()) {
    // ...
}

// 値の取得
$statusValue = $attendance->status->value;

// 全ケースの取得
$allStatuses = AttendanceStatusEnum::cases();
```

### ❌ Bad

```php
// マジック文字列
if ($attendance->status === 'approved') {
    // ...
}

// 定数の代わりに文字列
$attendance->status = 'pending';

// 型安全性の欠如
public function updateStatus(string $status): void // Enumを使うべき
{
    // ...
}
```

## まとめ

- ✅ **Backed Enumを使用**（String推奨）
- ✅ **Modelでキャスト定義**
- ✅ **カスタムメソッドで振る舞いを追加**
- ✅ **Inertia.jsでフロントエンドと連携**
- ❌ **マジックナンバー/文字列は使用しない**
- ❌ **文字列型の引数は使わず、Enum型を使用**

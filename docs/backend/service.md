# Service ガイドライン

## 概要

Serviceレイヤーはアプリケーションのビジネスロジックを担当します。ControllerとRepositoryの間に位置し、複雑なビジネスルールやトランザクション制御を管理します。

## 基本原則

### 単一責任の原則
- 1つのServiceは1つのドメイン（リソース）に関する処理のみを扱う
- 複数のRepositoryを組み合わせた複雑なビジネスロジックを実装する
- データの永続化はRepositoryに委譲する

### トランザクション境界
- Serviceメソッドはトランザクションの境界となる
- 複数のRepository操作を含む場合、トランザクション管理はServiceが行う

## ファイル構造

```
app/Services/
├── {Domain}Service.php
└── {Category}/             # 必要に応じてサブディレクトリ
    └── {Domain}Service.php
```

## 命名規則

### クラス名
- 単数形のドメイン名 + `Service`
- 例: `AttendanceService`, `UserService`, `NotificationService`

### メソッド名
- ビジネスアクションを表す動詞で始める
- 例: `createAttendance`, `calculateWorkingHours`, `sendNotification`
- CRUD操作: `find`, `create`, `update`, `delete`
- ビジネスロジック: `clockIn`, `clockOut`, `approveAttendance`

## コード例

### 基本的なService

```php
<?php

namespace App\Services;

use App\Models\Attendance;
use App\Repositories\AttendanceRepository;
use App\Repositories\UserRepository;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepository $attendanceRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * ユーザーの出勤記録一覧を取得
     *
     * @param int $userId
     * @param array $filters フィルター条件 ['date_from', 'date_to', 'status']
     * @return \Illuminate\Support\Collection
     */
    public function getAttendances(int $userId, array $filters = []): \Illuminate\Support\Collection
    {
        return $this->attendanceRepository->findByUserWithFilters($userId, $filters);
    }

    /**
     * 出勤記録を取得
     *
     * @param int $id
     * @return Attendance
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findAttendance(int $id): Attendance
    {
        return $this->attendanceRepository->findById($id);
    }

    /**
     * 出勤処理（打刻）
     *
     * @param int $userId
     * @param Carbon|null $startedAt
     * @return Attendance
     * @throws BusinessException
     */
    public function clockIn(int $userId, ?Carbon $startedAt = null): Attendance
    {
        // ビジネスルールチェック：既に出勤中でないか
        if ($this->hasActiveAttendance($userId)) {
            throw new BusinessException('既に出勤中です。先に退勤処理を行ってください。');
        }

        $user = $this->userRepository->findById($userId);
        $startedAt = $startedAt ?? now();

        // 出勤記録を作成
        $attendance = $this->attendanceRepository->create([
            'user_id' => $userId,
            'started_at' => $startedAt,
            'status' => 'working',
        ]);

        // 通知送信（非同期）
        $this->notificationService->sendClockInNotification($user, $attendance);

        return $attendance;
    }

    /**
     * 退勤処理（打刻）
     *
     * @param int $userId
     * @param Carbon|null $endedAt
     * @return Attendance
     * @throws BusinessException
     */
    public function clockOut(int $userId, ?Carbon $endedAt = null): Attendance
    {
        $attendance = $this->attendanceRepository->findActiveByUser($userId);

        if (!$attendance) {
            throw new BusinessException('出勤記録が見つかりません。');
        }

        $endedAt = $endedAt ?? now();

        // ビジネスルールチェック：退勤時刻が出勤時刻より後か
        if ($endedAt->lessThanOrEqualTo($attendance->started_at)) {
            throw new BusinessException('退勤時刻は出勤時刻より後の時刻を指定してください。');
        }

        // 勤務時間を計算
        $workingMinutes = $this->calculateWorkingMinutes(
            $attendance->started_at,
            $endedAt,
            $attendance->break_minutes ?? 0
        );

        // 退勤記録を更新
        $attendance = $this->attendanceRepository->update($attendance->id, [
            'ended_at' => $endedAt,
            'working_minutes' => $workingMinutes,
            'status' => 'completed',
        ]);

        return $attendance;
    }

    /**
     * 出勤記録を作成（管理者による手動作成）
     *
     * @param int $userId
     * @param array $data
     * @return Attendance
     * @throws BusinessException
     */
    public function createAttendance(int $userId, array $data): Attendance
    {
        return DB::transaction(function () use ($userId, $data) {
            // 重複チェック
            if ($this->hasOverlappingAttendance($userId, $data['started_at'], $data['ended_at'] ?? null)) {
                throw new BusinessException('指定された時間帯に既に出勤記録が存在します。');
            }

            // 勤務時間を計算
            if (isset($data['ended_at'])) {
                $data['working_minutes'] = $this->calculateWorkingMinutes(
                    Carbon::parse($data['started_at']),
                    Carbon::parse($data['ended_at']),
                    $data['break_minutes'] ?? 0
                );
                $data['status'] = 'completed';
            } else {
                $data['status'] = 'working';
            }

            $data['user_id'] = $userId;

            return $this->attendanceRepository->create($data);
        });
    }

    /**
     * 出勤記録を更新
     *
     * @param int $id
     * @param array $data
     * @return Attendance
     */
    public function updateAttendance(int $id, array $data): Attendance
    {
        return DB::transaction(function () use ($id, $data) {
            $attendance = $this->attendanceRepository->findById($id);

            // 勤務時間を再計算
            if (isset($data['ended_at'])) {
                $data['working_minutes'] = $this->calculateWorkingMinutes(
                    Carbon::parse($data['started_at'] ?? $attendance->started_at),
                    Carbon::parse($data['ended_at']),
                    $data['break_minutes'] ?? $attendance->break_minutes ?? 0
                );
            }

            return $this->attendanceRepository->update($id, $data);
        });
    }

    /**
     * 出勤記録を削除
     *
     * @param int $id
     * @return bool
     */
    public function deleteAttendance(int $id): bool
    {
        return $this->attendanceRepository->delete($id);
    }

    /**
     * 月次統計を取得
     *
     * @param int $userId
     * @param int $year
     * @param int $month
     * @return array
     */
    public function getMonthlyStatistics(int $userId, int $year, int $month): array
    {
        $attendances = $this->attendanceRepository->findByMonth($userId, $year, $month);

        return [
            'total_days' => $attendances->count(),
            'total_working_hours' => $attendances->sum('working_minutes') / 60,
            'average_working_hours' => $attendances->avg('working_minutes') / 60,
            'total_break_hours' => $attendances->sum('break_minutes') / 60,
        ];
    }

    /**
     * アクティブな出勤記録が存在するかチェック
     *
     * @param int $userId
     * @return bool
     */
    private function hasActiveAttendance(int $userId): bool
    {
        return $this->attendanceRepository->existsActiveByUser($userId);
    }

    /**
     * 重複する出勤記録が存在するかチェック
     *
     * @param int $userId
     * @param string|Carbon $startedAt
     * @param string|Carbon|null $endedAt
     * @return bool
     */
    private function hasOverlappingAttendance(int $userId, $startedAt, $endedAt = null): bool
    {
        return $this->attendanceRepository->existsOverlapping(
            $userId,
            Carbon::parse($startedAt),
            $endedAt ? Carbon::parse($endedAt) : null
        );
    }

    /**
     * 勤務時間を計算（分単位）
     *
     * @param Carbon $startedAt
     * @param Carbon $endedAt
     * @param int $breakMinutes
     * @return int
     */
    private function calculateWorkingMinutes(Carbon $startedAt, Carbon $endedAt, int $breakMinutes = 0): int
    {
        $totalMinutes = $endedAt->diffInMinutes($startedAt);
        return max(0, $totalMinutes - $breakMinutes);
    }
}
```

## ベストプラクティス

### 1. トランザクション管理

```php
// Good - Serviceレイヤーでトランザクション管理
public function approveAttendance(int $attendanceId, int $approverId): Attendance
{
    return DB::transaction(function () use ($attendanceId, $approverId) {
        $attendance = $this->attendanceRepository->findById($attendanceId);

        $attendance = $this->attendanceRepository->update($attendanceId, [
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        // 承認通知を送信
        $this->notificationService->sendApprovalNotification($attendance);

        return $attendance;
    });
}
```

### 2. ビジネスロジックの分離

```php
// Good - 複雑なビジネスロジックはprivateメソッドに分離
public function calculateOvertimeHours(int $userId, int $year, int $month): float
{
    $attendances = $this->attendanceRepository->findByMonth($userId, $year, $month);
    $totalWorkingMinutes = $attendances->sum('working_minutes');

    return $this->convertToOvertimeHours($totalWorkingMinutes, $this->getStandardWorkingHours($year, $month));
}

private function getStandardWorkingHours(int $year, int $month): int
{
    // 標準労働時間の計算ロジック
    $workingDays = $this->calculateWorkingDays($year, $month);
    return $workingDays * 8; // 1日8時間
}

private function convertToOvertimeHours(int $actualMinutes, int $standardHours): float
{
    $actualHours = $actualMinutes / 60;
    return max(0, $actualHours - $standardHours);
}
```

### 3. 例外処理

```php
// Good - ビジネス例外を適切にスロー
public function clockIn(int $userId): Attendance
{
    if ($this->hasActiveAttendance($userId)) {
        throw new BusinessException('既に出勤中です。');
    }

    if ($this->isHoliday(today())) {
        throw new BusinessException('本日は休日です。');
    }

    return $this->attendanceRepository->create([
        'user_id' => $userId,
        'started_at' => now(),
        'status' => 'working',
    ]);
}
```

### 4. 依存性注入

```php
// Good - コンストラクタで依存関係を注入
public function __construct(
    private readonly AttendanceRepository $attendanceRepository,
    private readonly UserRepository $userRepository,
    private readonly NotificationService $notificationService
) {}
```

### 5. 型ヒントとDocBlock

```php
/**
 * 月次レポートを生成
 *
 * @param int $userId
 * @param int $year
 * @param int $month
 * @return array{total_days: int, total_hours: float, overtime_hours: float}
 */
public function generateMonthlyReport(int $userId, int $year, int $month): array
{
    // 実装
}
```

## 重要な設計原則

### ⚠️ バリデーションはForm Requestで行う

**Serviceレイヤーでバリデーションを行わないでください。** すべての入力検証はForm Requestクラスで実装します。

#### 理由

1. **責務の分離**: Serviceはビジネスロジックに専念し、入力検証はHTTP層（Form Request）が担当
2. **重複の排除**: ControllerとServiceで二重にバリデーションを行うことを防ぐ
3. **保守性の向上**: バリデーションルールが1箇所に集約され、変更が容易
4. **Laravelの設計思想**: Laravelは入力検証をForm Requestで行う設計

#### ❌ Bad - Serviceでバリデーション

```php
// Bad - Serviceでバリデーション（不要）
public function create(array $data): User
{
    return DB::transaction(function () use ($data): User {
        // ❌ これらのバリデーションは不要
        if (empty($data['email'])) {
            throw new \InvalidArgumentException('メールアドレスは必須です。');
        }

        if ($this->userRepository->existsByEmail($data['email'])) {
            throw new \InvalidArgumentException('メールアドレスは既に使用されています。');
        }

        // ビジネスロジック
        return $this->userRepository->create($data);
    });
}
```

#### ✅ Good - Form Requestでバリデーション

```php
// Form Request - 入力検証はここで実装
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスは必須です。',
            'email.unique' => 'メールアドレスは既に使用されています。',
        ];
    }
}

// Service - ビジネスロジックのみ
public function create(array $data): User
{
    return DB::transaction(function () use ($data): User {
        // バリデーションは既にForm Requestで完了しているため不要

        // ビジネスロジックに専念
        $user = $this->userRepository->create($data);
        $this->assignDefaultRole($user);

        return $user;
    });
}
```

#### Serviceで行うべきこと

Serviceでは入力検証ではなく、**ビジネスルールの検証**を行います。

```php
// ✅ Good - ビジネスルールのチェック
public function clockIn(int $userId): Attendance
{
    // ビジネスルール: 既に出勤中でないか
    if ($this->hasActiveAttendance($userId)) {
        throw new BusinessException('既に出勤中です。先に退勤処理を行ってください。');
    }

    // ビジネスルール: 休日かどうか
    if ($this->isHoliday(today())) {
        throw new BusinessException('本日は休日です。');
    }

    return $this->attendanceRepository->create([
        'user_id' => $userId,
        'started_at' => CarbonImmutable::now(),
        'status' => 'working',
    ]);
}
```

#### まとめ

- ✅ **入力検証**: Form Requestで実装（必須チェック、型チェック、unique制約など）
- ✅ **ビジネスルール**: Serviceで実装（既に出勤中かどうか、休日かどうかなど）
- ❌ **Serviceで入力検証を行わない**

## アンチパターン

### ❌ Serviceレイヤーを経由せずModelを直接操作

```php
// Bad - ControllerでModel直接操作
public function store(Request $request)
{
    $attendance = Attendance::create($request->all());
    return redirect()->route('attendances.show', $attendance->id);
}

// Good - Serviceを経由
public function store(StoreAttendanceRequest $request)
{
    $attendance = $this->attendanceService->createAttendance(
        userId: $request->user()->id,
        data: $request->validated()
    );
    return redirect()->route('attendances.show', $attendance->id);
}
```

### ❌ Service内でHTTPレスポンスを返す

```php
// Bad
public function clockIn(int $userId)
{
    return redirect()->route('attendances.index'); // NG
}

// Good
public function clockIn(int $userId): Attendance
{
    return $this->attendanceRepository->create([...]);
}
```

### ❌ 複数ドメインのロジックを1つのServiceに混在

```php
// Bad - AttendanceServiceにUserのロジックが混在
class AttendanceService
{
    public function createUser(array $data) { } // NG
    public function updateUserProfile(int $userId, array $data) { } // NG
}

// Good - 適切に分離
class UserService
{
    public function createUser(array $data) { }
    public function updateProfile(int $userId, array $data) { }
}
```

## まとめ

- Serviceはビジネスロジックの中心
- トランザクション管理はServiceで行う
- Repositoryへのデータアクセスを委譲
- 適切な例外処理でビジネスルールを強制
- テスタブルな設計を心がける

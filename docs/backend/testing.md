# テスト ガイドライン

## 概要

アプリケーションの品質を保証するためのテストガイドラインです。

## 基本原則

### テストの種類

1. **Unit Test**: 個別のクラス・メソッドのテスト
2. **Feature Test**: エンドツーエンドの機能テスト
3. **Integration Test**: 複数のコンポーネント間の連携テスト

### テストカバレッジ

以下のレイヤーで必ずテストを作成します：

- **Service層**: ビジネスロジックのテスト（重点的に）
- **Repository層**: データアクセスロジックのテスト
- **Controller層**: HTTPリクエスト/レスポンスのテスト
- **Policy層**: 認可ロジックのテスト

## ディレクトリ構造

```
tests/
├── Feature/
│   ├── Admin/
│   │   └── {Resource}ControllerTest.php
│   └── Staff/
│       └── {Resource}ControllerTest.php
├── Unit/
│   ├── Services/
│   │   └── {Domain}ServiceTest.php
│   ├── Repositories/
│   │   └── {Model}RepositoryTest.php
│   └── Policies/
│       └── {Model}PolicyTest.php
├── Pest.php
└── TestCase.php
```

## Unit Test

### Serviceのテスト

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AttendanceStatusEnum;
use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\Attendance;
use App\Repositories\Interfaces\AttendanceRepositoryInterface;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    private AttendanceRepositoryInterface $repository;
    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mockeryでリポジトリをモック
        $this->repository = Mockery::mock(AttendanceRepositoryInterface::class);
        $this->service = new AttendanceService($this->repository);
    }

    /**
     * @test
     */
    public function 出勤記録を作成できる(): void
    {
        // Arrange
        $userId = 1;
        $data = [
            'started_at' => '2024-01-01 09:00:00',
            'ended_at' => '2024-01-01 18:00:00',
        ];

        $expectedAttendance = new Attendance([
            'id' => 1,
            'user_id' => $userId,
            ...$data,
            'status' => AttendanceStatusEnum::PENDING,
        ]);

        $this->repository
            ->shouldReceive('existsOverlapping')
            ->once()
            ->with($userId, $data['started_at'])
            ->andReturn(false);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedAttendance);

        // Act
        $result = $this->service->createAttendance($userId, $data);

        // Assert
        $this->assertSame($expectedAttendance, $result);
    }

    /**
     * @test
     */
    public function 重複する出勤記録がある場合は例外をスローする(): void
    {
        // Arrange
        $userId = 1;
        $data = [
            'started_at' => '2024-01-01 09:00:00',
        ];

        $this->repository
            ->shouldReceive('existsOverlapping')
            ->once()
            ->with($userId, $data['started_at'])
            ->andReturn(true);

        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('既に出勤記録が存在します。');

        // Act
        $this->service->createAttendance($userId, $data);
    }

    /**
     * @test
     */
    public function 出勤記録を承認できる(): void
    {
        // Arrange
        $id = 1;
        $attendance = new Attendance([
            'id' => $id,
            'user_id' => 1,
            'status' => AttendanceStatusEnum::PENDING,
            'started_at' => CarbonImmutable::parse('2024-01-01 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2024-01-01 18:00:00'),
        ]);

        $approvedAttendance = clone $attendance;
        $approvedAttendance->status = AttendanceStatusEnum::APPROVED;

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($attendance);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($id, ['status' => AttendanceStatusEnum::APPROVED])
            ->andReturn($approvedAttendance);

        // Act
        $result = $this->service->approve($id);

        // Assert
        $this->assertEquals(AttendanceStatusEnum::APPROVED, $result->status);
    }

    /**
     * @test
     */
    public function 出勤記録が見つからない場合は例外をスローする(): void
    {
        // Arrange
        $id = 999;

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn(null);

        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('出勤記録が見つかりません。');

        // Act
        $this->service->approve($id);
    }

    /**
     * @test
     */
    public function 承認待ち以外の記録は承認できない(): void
    {
        // Arrange
        $id = 1;
        $attendance = new Attendance([
            'id' => $id,
            'status' => AttendanceStatusEnum::APPROVED, // 既に承認済み
            'ended_at' => CarbonImmutable::now(),
        ]);

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($attendance);

        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('承認待ち以外の記録は承認できません。');

        // Act
        $this->service->approve($id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

### Repositoryのテスト

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AttendanceRepository(new Attendance());
    }

    /**
     * @test
     */
    public function 出勤記録を作成できる(): void
    {
        // Arrange
        $user = User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'started_at' => CarbonImmutable::now(),
            'status' => AttendanceStatusEnum::PENDING,
        ];

        // Act
        $attendance = $this->repository->create($data);

        // Assert
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'status' => AttendanceStatusEnum::PENDING->value,
        ]);
    }

    /**
     * @test
     */
    public function ステータスで出勤記録を検索できる(): void
    {
        // Arrange
        $user = User::factory()->create();
        Attendance::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => AttendanceStatusEnum::PENDING,
        ]);
        Attendance::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => AttendanceStatusEnum::APPROVED,
        ]);

        // Act
        $pending = $this->repository->findByStatus(AttendanceStatusEnum::PENDING);
        $approved = $this->repository->findByStatus(AttendanceStatusEnum::APPROVED);

        // Assert
        $this->assertCount(3, $pending);
        $this->assertCount(2, $approved);
    }

    /**
     * @test
     */
    public function 重複する出勤記録の存在チェックができる(): void
    {
        // Arrange
        $user = User::factory()->create();
        $startedAt = CarbonImmutable::parse('2024-01-01 09:00:00');

        Attendance::factory()->create([
            'user_id' => $user->id,
            'started_at' => $startedAt,
        ]);

        // Act
        $exists = $this->repository->existsOverlapping($user->id, $startedAt->toDateTimeString());

        // Assert
        $this->assertTrue($exists);
    }
}
```

### Policyのテスト

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\AttendanceStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Attendance;
use App\Models\User;
use App\Policies\AttendancePolicy;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    private AttendancePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AttendancePolicy();
    }

    /**
     * @test
     */
    public function 自分の出勤記録は更新できる(): void
    {
        // Arrange
        $user = User::factory()->make(['id' => 1]);
        $attendance = Attendance::factory()->make([
            'user_id' => 1,
            'status' => AttendanceStatusEnum::PENDING,
        ]);

        // Act
        $result = $this->policy->update($user, $attendance);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function 他人の出勤記録は更新できない(): void
    {
        // Arrange
        $user = User::factory()->make(['id' => 1]);
        $attendance = Attendance::factory()->make([
            'user_id' => 2,
            'status' => AttendanceStatusEnum::PENDING,
        ]);

        // Act
        $result = $this->policy->update($user, $attendance);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function 承認済みの出勤記録は更新できない(): void
    {
        // Arrange
        $user = User::factory()->make(['id' => 1]);
        $attendance = Attendance::factory()->make([
            'user_id' => 1,
            'status' => AttendanceStatusEnum::APPROVED,
        ]);

        // Act
        $result = $this->policy->update($user, $attendance);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function 管理者は承認できる(): void
    {
        // Arrange
        $admin = User::factory()->make(['role' => UserRoleEnum::ADMIN]);
        $attendance = Attendance::factory()->make([
            'status' => AttendanceStatusEnum::PENDING,
        ]);

        // Act
        $result = $this->policy->approve($admin, $attendance);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function スタッフは承認できない(): void
    {
        // Arrange
        $staff = User::factory()->make(['role' => UserRoleEnum::STAFF]);
        $attendance = Attendance::factory()->make([
            'status' => AttendanceStatusEnum::PENDING,
        ]);

        // Act
        $result = $this->policy->approve($staff, $attendance);

        // Assert
        $this->assertFalse($result);
    }
}
```

## Feature Test

### Controllerのテスト

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRoleEnum::ADMIN]);
    }

    /**
     * @test
     */
    public function 出勤記録一覧を表示できる(): void
    {
        // Arrange
        Attendance::factory()->count(5)->create();

        // Act
        $response = $this->actingAs($this->admin)
            ->get(route('admin.attendances.index'));

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page
            ->component('Admin/Attendance/Index')
            ->has('attendances', 5)
        );
    }

    /**
     * @test
     */
    public function 出勤記録を作成できる(): void
    {
        // Arrange
        $user = User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'started_at' => '2024-01-01 09:00:00',
            'ended_at' => '2024-01-01 18:00:00',
        ];

        // Act
        $response = $this->actingAs($this->admin)
            ->post(route('admin.attendances.store'), $data);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'status' => AttendanceStatusEnum::PENDING->value,
        ]);
    }

    /**
     * @test
     */
    public function バリデーションエラーの場合は出勤記録を作成できない(): void
    {
        // Arrange
        $data = [
            'started_at' => '', // 必須項目が空
        ];

        // Act
        $response = $this->actingAs($this->admin)
            ->post(route('admin.attendances.store'), $data);

        // Assert
        $response->assertSessionHasErrors(['started_at']);
        $this->assertDatabaseCount('attendances', 0);
    }

    /**
     * @test
     */
    public function 出勤記録を承認できる(): void
    {
        // Arrange
        $attendance = Attendance::factory()->create([
            'status' => AttendanceStatusEnum::PENDING,
            'ended_at' => CarbonImmutable::now(),
        ]);

        // Act
        $response = $this->actingAs($this->admin)
            ->post(route('admin.attendances.approve', $attendance->id));

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => AttendanceStatusEnum::APPROVED->value,
        ]);
    }

    /**
     * @test
     */
    public function スタッフは承認できない(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => UserRoleEnum::STAFF]);
        $attendance = Attendance::factory()->create([
            'status' => AttendanceStatusEnum::PENDING,
        ]);

        // Act
        $response = $this->actingAs($staff)
            ->post(route('admin.attendances.approve', $attendance->id));

        // Assert
        $response->assertStatus(403); // Forbidden
    }

    /**
     * @test
     */
    public function 出勤記録を削除できる(): void
    {
        // Arrange
        $attendance = Attendance::factory()->create([
            'status' => AttendanceStatusEnum::PENDING,
        ]);

        // Act
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.attendances.destroy', $attendance->id));

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);
    }

    /**
     * @test
     */
    public function 承認済みの出勤記録は削除できない(): void
    {
        // Arrange
        $attendance = Attendance::factory()->create([
            'status' => AttendanceStatusEnum::APPROVED,
        ]);

        // Act
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.attendances.destroy', $attendance->id));

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
        ]);
    }
}
```

## Factory

### Modelのファクトリ

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $startedAt = CarbonImmutable::parse($this->faker->dateTimeBetween('-1 month', 'now'));
        $endedAt = $startedAt->addHours(8);

        return [
            'user_id' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'break_minutes' => 60,
            'status' => AttendanceStatusEnum::PENDING,
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * 承認済みの状態
     */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => AttendanceStatusEnum::APPROVED,
        ]);
    }

    /**
     * 却下済みの状態
     */
    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => AttendanceStatusEnum::REJECTED,
        ]);
    }

    /**
     * 退勤時刻なし
     */
    public function withoutEndedAt(): static
    {
        return $this->state(fn(array $attributes) => [
            'ended_at' => null,
        ]);
    }
}
```

### ファクトリの使用例

```php
// 基本的な使用
$attendance = Attendance::factory()->create();

// 承認済みの出勤記録
$approved = Attendance::factory()->approved()->create();

// 複数作成
$attendances = Attendance::factory()->count(10)->create();

// 特定のユーザーの出勤記録
$user = User::factory()->create();
$attendance = Attendance::factory()->create(['user_id' => $user->id]);

// 退勤時刻なし
$ongoing = Attendance::factory()->withoutEndedAt()->create();
```

## テストのベストプラクティス

### AAA パターン

```php
/**
 * @test
 */
public function テストケース名(): void
{
    // Arrange - テストデータの準備
    $user = User::factory()->create();
    $data = [...];

    // Act - テスト対象の実行
    $result = $this->service->someMethod($data);

    // Assert - 結果の検証
    $this->assertEquals($expected, $result);
}
```

### テスト名

日本語で具体的なテスト内容を記述します。

```php
// ✅ Good - 何をテストしているか明確
/**
 * @test
 */
public function 重複する出勤記録がある場合は例外をスローする(): void

// ❌ Bad - 何をテストしているか不明
/**
 * @test
 */
public function testCreate(): void
```

### データベースのリセット

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceRepositoryTest extends TestCase
{
    use RefreshDatabase; // テストごとにDBをリセット

    // ...
}
```

### モックの使用

```php
use Mockery;

// サービスのテストではリポジトリをモック
$repository = Mockery::mock(AttendanceRepositoryInterface::class);
$repository
    ->shouldReceive('find')
    ->once()
    ->with(1)
    ->andReturn($attendance);
```

## テスト実行コマンド

```bash
# 全てのテストを実行
./vendor/bin/sail test

# 特定のテストファイルを実行
./vendor/bin/sail test tests/Unit/Services/AttendanceServiceTest.php

# 特定のテストメソッドを実行
./vendor/bin/sail test --filter=出勤記録を作成できる

# カバレッジレポート生成
./vendor/bin/sail test --coverage
```

## まとめ

- ✅ **Service層を重点的にテスト**
- ✅ **AAA パターンでテストを構造化**
- ✅ **日本語でテスト名を記述**
- ✅ **Mockeryでモックを作成**
- ✅ **Factoryでテストデータを生成**
- ✅ **RefreshDatabaseでDBをリセット**
- ❌ **テスト名を英語や省略形で書かない**
- ❌ **実際のDBやAPIを使用しない**

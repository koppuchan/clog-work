<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * シフト表への表示・非表示（is_shift_hidden）の検証。
 *
 * 管理者などシフトに入らないユーザーを、シフト管理画面の一覧および
 * 人数集計から除外できるようにする。既定値は false のため、
 * 既存ユーザーの表示は変化しない。
 */
class UserShiftHiddenTest extends TestCase
{
    use DatabaseTransactions;

    private UserService $service;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
        $this->company = Company::factory()->create(['company_code' => '900001']);
    }

    private function makeUser(string $name, bool $shiftHidden): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_shift_hidden' => $shiftHidden,
        ]);
        $user->companies()->attach($this->company->id, ['is_primary' => true]);

        return $user;
    }

    private function shiftUserNames(): array
    {
        return $this->service
            ->findByCompanyIdForShift($this->company->id, Carbon::now()->toDateString())
            ->pluck('name')
            ->all();
    }

    /**
     * @test
     */
    public function 既定では全てのユーザーがシフト表に表示される(): void
    {
        // Arrange
        $this->makeUser('表示 太郎', false);
        $this->makeUser('表示 次郎', false);

        // Act
        $names = $this->shiftUserNames();

        // Assert
        $this->assertContains('表示 太郎', $names);
        $this->assertContains('表示 次郎', $names);
    }

    /**
     * @test
     */
    public function 非表示フラグを立てたユーザーはシフト表から除外される(): void
    {
        // Arrange
        $this->makeUser('表示 太郎', false);
        $this->makeUser('管理 花子', true);

        // Act
        $names = $this->shiftUserNames();

        // Assert
        $this->assertContains('表示 太郎', $names);
        $this->assertNotContains('管理 花子', $names);
    }

    /**
     * @test
     */
    public function 非表示のユーザーは人数集計にも含まれない(): void
    {
        // Arrange
        $this->makeUser('表示 太郎', false);
        $this->makeUser('表示 次郎', false);
        $this->makeUser('管理 花子', true);

        // Act
        $count = count($this->shiftUserNames());

        // Assert: シフト画面の人数は同一のコレクションから算出されるため 2 名になる
        $this->assertSame(2, $count);
    }

    /**
     * @test
     */
    public function 新規ユーザーの既定値はfalseである(): void
    {
        // Act
        $user = User::factory()->create();

        // Assert
        $this->assertFalse((bool) $user->fresh()->is_shift_hidden);
    }

    /**
     * @test
     */
    public function フラグは更新して永続化できる(): void
    {
        // Arrange
        $user = $this->makeUser('管理 花子', false);

        // Act
        $user->update(['is_shift_hidden' => true]);

        // Assert
        $this->assertTrue((bool) $user->fresh()->is_shift_hidden);
        $this->assertNotContains('管理 花子', $this->shiftUserNames());
    }
}

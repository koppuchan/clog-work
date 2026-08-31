<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Company;
use App\Models\Department;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\User;
use App\Repositories\ShiftRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/ShiftRepositoryTest.php
 */
class ShiftRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ShiftRepository $repository;

    private Company $company;

    private User $user;

    private ShiftPattern $shiftPattern;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ShiftRepository(new Shift);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create();
        $this->shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '日勤',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);
    }

    public function test_find_by_idで存在するシフトを取得できる(): void
    {
        // Arrange
        $shift = Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
            'note' => 'テストメモ',
        ]);

        // Act
        $result = $this->repository->findById($shift->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertTrue($result->relationLoaded('user'));
        $this->assertTrue($result->relationLoaded('shiftPattern'));
    }

    public function test_find_by_idで存在しない_i_dの場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findById(9999);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_by_user_id_and_date_rangeでユーザーの日付範囲内シフトを取得できる(): void
    {
        // Arrange
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-15',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-04-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);

        // Act
        $result = $this->repository->findByUserIdAndDateRange(
            $this->company->id,
            $this->user->id,
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_by_user_id_and_date_rangeで該当データがない場合は空コレクションを返す(): void
    {
        // Act
        $result = $this->repository->findByUserIdAndDateRange(
            $this->company->id,
            $this->user->id,
            '2026-01-01',
            '2026-01-31'
        );

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_by_company_id_and_date_rangeで会社の日付範囲内シフトを取得できる(): void
    {
        // Arrange
        $user2 = User::factory()->forCompany($this->company->id)->create();
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user2->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);

        // Act
        $result = $this->repository->findByCompanyIdAndDateRange(
            $this->company->id,
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->first()->relationLoaded('user'));
    }

    public function test_find_by_department_id_and_date_rangeで部署の日付範囲内シフトを取得できる(): void
    {
        // Arrange
        $department = Department::query()->create([
            'company_id' => $this->company->id,
            'name' => '営業部',
        ]);
        $this->user->departments()->attach($department->id, ['is_primary' => true]);

        $userOutsideDept = User::factory()->forCompany($this->company->id)->create();

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $userOutsideDept->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);

        // Act
        $result = $this->repository->findByDepartmentIdAndDateRange(
            $this->company->id,
            $department->id,
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($this->user->id, $result->first()->user_id);
    }

    public function test_find_by_department_id_and_date_rangeで部署に所属するユーザーがいない場合は空コレクションを返す(): void
    {
        // Arrange
        $department = Department::query()->create([
            'company_id' => $this->company->id,
            'name' => '空部署',
        ]);

        // Act
        $result = $this->repository->findByDepartmentIdAndDateRange(
            $this->company->id,
            $department->id,
            '2026-03-01',
            '2026-03-31'
        );

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_createで新しいシフトを作成できる(): void
    {
        // Arrange
        $data = [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-10',
            'shift_pattern_id' => $this->shiftPattern->id,
            'note' => '作成テスト',
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals('作成テスト', $result->note);
        $this->assertDatabaseHas('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-10',
        ]);
    }

    public function test_updateで既存シフトを更新できる(): void
    {
        // Arrange
        $shift = Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
            'note' => '旧メモ',
        ]);

        // Act
        $result = $this->repository->update($shift->id, ['note' => '新メモ']);

        // Assert
        $this->assertEquals('新メモ', $result->note);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'note' => '新メモ']);
    }

    public function test_updateで存在しない_i_dの場合は例外がスローされる(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->repository->update(9999, ['note' => 'テスト']);
    }

    public function test_deleteでシフトを削除できる(): void
    {
        // Arrange
        $shift = Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);

        // Act
        $result = $this->repository->delete($shift->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_deleteで存在しない_i_dの場合はfalseを返す(): void
    {
        // Act
        $result = $this->repository->delete(9999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_upsert_manyで複数シフトを一括作成できる(): void
    {
        // Arrange
        $user2 = User::factory()->forCompany($this->company->id)->create();
        $shifts = [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-03-01',
                'shift_pattern_id' => $this->shiftPattern->id,
                'note' => 'シフト1',
            ],
            [
                'user_id' => $user2->id,
                'shift_date' => '2026-03-01',
                'shift_pattern_id' => $this->shiftPattern->id,
                'note' => 'シフト2',
            ],
        ];

        // Act
        $this->repository->upsertMany($this->company->id, $shifts);

        // Assert
        $this->assertDatabaseHas('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
        ]);
        $this->assertDatabaseHas('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $user2->id,
            'shift_date' => '2026-03-01',
        ]);
    }

    public function test_upsert_manyで既存シフトを更新できる(): void
    {
        // Arrange
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
            'note' => '旧メモ',
        ]);

        $newShiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '22:00',
            'end_time' => '07:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '02:00',
            'break_end' => '03:00',
        ]);

        $shifts = [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-03-01',
                'shift_pattern_id' => $newShiftPattern->id,
                'note' => '新メモ',
            ],
        ];

        // Act
        $this->repository->upsertMany($this->company->id, $shifts);

        // Assert
        $this->assertDatabaseHas('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $newShiftPattern->id,
            'note' => '新メモ',
        ]);
    }

    public function test_upsert_manyで空配列の場合は何も実行されない(): void
    {
        // Act
        $this->repository->upsertMany($this->company->id, []);

        // Assert
        $this->assertDatabaseMissing('shifts', ['company_id' => $this->company->id]);
    }

    public function test_upsert_manyでshift_pattern_idがnullの行は既存シフトを削除する(): void
    {
        // Arrange: 既存シフトを作成
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);

        // Act: shift_pattern_id=nullで送信（休みに変更）
        $this->repository->upsertMany($this->company->id, [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-03-01',
                'shift_pattern_id' => null,
            ],
        ]);

        // Assert: シフトが削除されている
        $this->assertDatabaseMissing('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
        ]);
    }

    public function test_upsert_manyでshift_pattern_idがnullの行は既存シフトがなくてもエラーにならない(): void
    {
        // Act: 既存シフトがない状態でshift_pattern_id=nullを送信
        $this->repository->upsertMany($this->company->id, [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-03-01',
                'shift_pattern_id' => null,
            ],
        ]);

        // Assert: エラーにならず、シフトも作成されない
        $this->assertDatabaseMissing('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
        ]);
    }

    public function test_upsert_manyでnullと非nullが混在する場合に正しく処理される(): void
    {
        // Arrange: 2件の既存シフト
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-02',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);

        // Act: 03-01を休みに、03-02はシフト維持、03-03は新規シフト
        $this->repository->upsertMany($this->company->id, [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-03-01',
                'shift_pattern_id' => null,
            ],
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-03-02',
                'shift_pattern_id' => $this->shiftPattern->id,
                'note' => '更新',
            ],
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-03-03',
                'shift_pattern_id' => $this->shiftPattern->id,
                'note' => '新規',
            ],
        ]);

        // Assert
        $this->assertDatabaseMissing('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-01',
        ]);
        $this->assertDatabaseHas('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-02',
            'note' => '更新',
        ]);
        $this->assertDatabaseHas('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-03-03',
            'note' => '新規',
        ]);
    }

    public function test_upsert_manyで大量データ100件を正しく処理できる(): void
    {
        // Arrange: 10ユーザー作成
        $users = [];
        $users[] = $this->user;
        for ($i = 0; $i < 9; $i++) {
            $users[] = User::factory()->forCompany($this->company->id)->create();
        }

        // 各ユーザーに対して既存シフトを5件ずつ作成（計50件）
        foreach ($users as $user) {
            for ($day = 1; $day <= 5; $day++) {
                Shift::query()->create([
                    'company_id' => $this->company->id,
                    'user_id' => $user->id,
                    'shift_date' => sprintf('2026-03-%02d', $day),
                    'shift_pattern_id' => $this->shiftPattern->id,
                ]);
            }
        }

        // 100件のシフトデータ（10ユーザー × 10日分）
        // 奇数日はシフトあり、偶数日は休み（null）
        $shifts = [];
        foreach ($users as $user) {
            for ($day = 1; $day <= 10; $day++) {
                $shifts[] = [
                    'user_id' => $user->id,
                    'shift_date' => sprintf('2026-03-%02d', $day),
                    'shift_pattern_id' => ($day % 2 === 1) ? $this->shiftPattern->id : null,
                    'note' => ($day % 2 === 1) ? "日{$day}" : null,
                ];
            }
        }

        $this->assertCount(100, $shifts);

        // Act
        $this->repository->upsertMany($this->company->id, $shifts);

        // Assert: 各ユーザーの奇数日にシフトがあり、偶数日にはない
        foreach ($users as $user) {
            for ($day = 1; $day <= 10; $day++) {
                $date = sprintf('2026-03-%02d', $day);
                if ($day % 2 === 1) {
                    $this->assertDatabaseHas('shifts', [
                        'company_id' => $this->company->id,
                        'user_id' => $user->id,
                        'shift_date' => $date,
                        'shift_pattern_id' => $this->shiftPattern->id,
                    ]);
                } else {
                    $this->assertDatabaseMissing('shifts', [
                        'company_id' => $this->company->id,
                        'user_id' => $user->id,
                        'shift_date' => $date,
                    ]);
                }
            }
        }

        // 合計件数: 10ユーザー × 5日（奇数日） = 50件
        $totalShifts = Shift::query()
            ->where('company_id', $this->company->id)
            ->count();
        $this->assertEquals(50, $totalShifts);
    }

    public function test_upsert_manyで全件nullの場合は削除のみ実行される(): void
    {
        // Arrange: 3件の既存シフト
        for ($day = 1; $day <= 3; $day++) {
            Shift::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'shift_date' => sprintf('2026-03-%02d', $day),
                'shift_pattern_id' => $this->shiftPattern->id,
            ]);
        }

        // Act: 全件をnull（休み）にする
        $shifts = [];
        for ($day = 1; $day <= 3; $day++) {
            $shifts[] = [
                'user_id' => $this->user->id,
                'shift_date' => sprintf('2026-03-%02d', $day),
                'shift_pattern_id' => null,
            ];
        }
        $this->repository->upsertMany($this->company->id, $shifts);

        // Assert: 全件削除されている
        $count = Shift::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->count();
        $this->assertEquals(0, $count);
    }

    public function test_upsert_manyで他会社のシフトに影響しない(): void
    {
        // Arrange: 別会社のシフト
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->forCompany($otherCompany->id)->create();
        $otherShiftPattern = ShiftPattern::query()->create([
            'company_id' => $otherCompany->id,
            'name' => '他社シフト',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);
        Shift::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'shift_date' => '2026-03-01',
            'shift_pattern_id' => $otherShiftPattern->id,
        ]);

        // Act: 自社のシフト操作（nullで削除）
        $this->repository->upsertMany($this->company->id, [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-03-01',
                'shift_pattern_id' => null,
            ],
        ]);

        // Assert: 他社のシフトは残っている
        $this->assertDatabaseHas('shifts', [
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'shift_date' => '2026-03-01',
        ]);
    }

    public function test_upsert_manyで複数ユーザーのnull削除が一括で処理される(): void
    {
        // Arrange: 3ユーザー分の既存シフト
        $user2 = User::factory()->forCompany($this->company->id)->create();
        $user3 = User::factory()->forCompany($this->company->id)->create();

        foreach ([$this->user, $user2, $user3] as $user) {
            Shift::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'shift_date' => '2026-03-01',
                'shift_pattern_id' => $this->shiftPattern->id,
            ]);
        }

        // Act: 全ユーザーのシフトをnullにする
        $this->repository->upsertMany($this->company->id, [
            ['user_id' => $this->user->id, 'shift_date' => '2026-03-01', 'shift_pattern_id' => null],
            ['user_id' => $user2->id, 'shift_date' => '2026-03-01', 'shift_pattern_id' => null],
            ['user_id' => $user3->id, 'shift_date' => '2026-03-01', 'shift_pattern_id' => null],
        ]);

        // Assert: 全件削除されている
        $count = Shift::query()
            ->where('company_id', $this->company->id)
            ->where('shift_date', '2026-03-01')
            ->count();
        $this->assertEquals(0, $count);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * スタッフ画面のシフト表における非表示フラグの検証。
 *
 * 個人マスタで「シフト表に表示しない」にしたスタッフは、
 * 管理者画面と同じくスタッフ画面の一覧にも出さない。
 */
class StaffShiftHiddenTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private ShiftService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->service = app(ShiftService::class);
    }

    private function createUser(string $name, bool $hidden = false): User
    {
        return User::factory()->forCompany($this->company->id)->create([
            'name' => $name,
            'is_shift_hidden' => $hidden,
            'is_retired' => false,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function visibleNames(int $viewerId): array
    {
        $data = $this->service->getStaffShiftViewData(
            $this->company->id,
            $viewerId,
            '2026-06-01',
            '2026-06-30',
            'company',
        );

        return array_column($data['users'], 'name');
    }

    /**
     * @test
     */
    public function 非表示のスタッフは一覧に出ない(): void
    {
        $viewer = $this->createUser('閲覧 太郎');
        $this->createUser('非表示 花子', true);

        $names = $this->visibleNames($viewer->id);

        $this->assertContains('閲覧 太郎', $names);
        $this->assertNotContains('非表示 花子', $names);
    }

    /**
     * @test
     */
    public function 表示するスタッフはそのまま出る(): void
    {
        $viewer = $this->createUser('閲覧 太郎');
        $this->createUser('通常 次郎');

        $this->assertContains('通常 次郎', $this->visibleNames($viewer->id));
    }

    /**
     * @test
     */
    public function 非表示にされた本人は自分のシフトを確認できる(): void
    {
        // 一覧から消えるだけで、本人の確認まで塞がないようにする
        $viewer = $this->createUser('非表示 本人', true);

        $this->assertContains('非表示 本人', $this->visibleNames($viewer->id));
    }

    /**
     * @test
     */
    public function 非表示のスタッフのシフト予定は渡らない(): void
    {
        $viewer = $this->createUser('閲覧 太郎');
        $hidden = $this->createUser('非表示 花子', true);

        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        foreach ([$viewer, $hidden] as $user) {
            Shift::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'shift_date' => '2026-06-10',
                'shift_pattern_id' => $pattern->id,
            ]);
        }

        $data = $this->service->getStaffShiftViewData(
            $this->company->id,
            $viewer->id,
            '2026-06-01',
            '2026-06-30',
            'company',
        );

        // 閲覧者の予定は渡り、非表示のスタッフの予定は渡らない
        $this->assertArrayHasKey($viewer->id, $data['shifts']['2026-06-10']);
        $this->assertArrayNotHasKey($hidden->id, $data['shifts']['2026-06-10']);
    }
}

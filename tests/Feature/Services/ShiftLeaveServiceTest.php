<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\User;
use App\Services\ShiftLeaveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 承認された休暇のシフト表への反映。
 *
 * 出勤人数の数え方は、全日の休暇が -1人、半日有給が -0.5人、
 * 時間有給がシフト所定労働時間に対する按分（8時間で2時間なら -0.25人）。
 */
class ShiftLeaveServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ShiftLeaveService $service;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ShiftLeaveService::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create(['name' => '休暇 太郎']);
    }

    private function createLeave(LeaveTypeEnum $type, ?int $leaveMinutes, string $date = '2026-06-24'): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $date,
            'leave_type' => $type,
            'leave_minutes' => $leaveMinutes,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(): array
    {
        return $this->service->getLeavesForShift(
            $this->company->id,
            [$this->user->id],
            '2026-06-01',
            '2026-06-30',
        );
    }

    /**
     * @test
     */
    public function 全日の有給は1人分を差し引き有と表示する(): void
    {
        $this->createLeave(LeaveTypeEnum::PAID_LEAVE, null);

        $leaves = $this->fetch();

        $this->assertCount(1, $leaves);
        $this->assertSame('有', $leaves[0]['label']);
        $this->assertSame(1.0, $leaves[0]['deduction']);
        $this->assertTrue($leaves[0]['is_full_day']);
    }

    /**
     * @test
     */
    public function 全日の特別休暇は特と表示する(): void
    {
        $this->createLeave(LeaveTypeEnum::SPECIAL_LEAVE, null);

        $this->assertSame('特', $this->fetch()[0]['label']);
    }

    /**
     * @test
     */
    public function 欠勤はシフト表に出さない(): void
    {
        // シフト表は勤務の予定を見るもので、欠勤で予定が消えると
        // 誰がどの枠に入っていたか分からなくなるため対象外とする
        $this->createLeave(LeaveTypeEnum::ABSENCE, null);

        $this->assertSame([], $this->fetch());
    }

    /**
     * @test
     */
    public function 欠勤は出勤人数から差し引かない(): void
    {
        $this->createLeave(LeaveTypeEnum::ABSENCE, null, '2026-06-22');
        $this->createLeave(LeaveTypeEnum::PAID_LEAVE, null, '2026-06-23');

        $leaves = $this->fetch();

        $this->assertCount(1, $leaves);
        $this->assertSame('有', $leaves[0]['label']);
    }

    /**
     * @test
     */
    public function 半日有給は05人分を差し引き半と表示する(): void
    {
        $this->createLeave(LeaveTypeEnum::PAID_LEAVE, 240);

        $leave = $this->fetch()[0];

        $this->assertSame('半', $leave['label']);
        $this->assertSame(0.5, $leave['deduction']);
        $this->assertFalse($leave['is_full_day']);
    }

    /**
     * @test
     */
    public function 時間有給は所定労働時間に対する按分になる(): void
    {
        // 8時間のシフトで2時間取得 → 0.25人
        $this->createLeave(LeaveTypeEnum::PAID_LEAVE, 120);

        $leave = $this->fetch()[0];

        $this->assertSame('時', $leave['label']);
        $this->assertSame(0.25, $leave['deduction']);
    }

    /**
     * @test
     */
    public function 所定労働時間以上の休暇は全日として扱う(): void
    {
        $this->createLeave(LeaveTypeEnum::PAID_LEAVE, 480);

        $this->assertSame(1.0, $this->fetch()[0]['deduction']);
    }

    /**
     * @test
     */
    public function 休暇のない日は含まれない(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'leave_type' => null,
            'net_work_minutes' => 480,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->assertSame([], $this->fetch());
    }

    /**
     * @test
     */
    public function 休暇の種別ごとに色を分ける(): void
    {
        $this->createLeave(LeaveTypeEnum::PAID_LEAVE, null, '2026-06-22');
        $this->createLeave(LeaveTypeEnum::SPECIAL_LEAVE, null, '2026-06-23');

        $colors = collect($this->fetch())->pluck('background_color')->all();

        // シフトパターンの色と重ならない薄い色を割り当てる
        $this->assertSame(2, count(array_unique($colors)));
    }

    /**
     * @test
     */
    public function 対象ユーザーがいなければ空になる(): void
    {
        $this->createLeave(LeaveTypeEnum::PAID_LEAVE, null);

        $this->assertSame([], $this->service->getLeavesForShift(
            $this->company->id,
            [],
            '2026-06-01',
            '2026-06-30',
        ));
    }
}

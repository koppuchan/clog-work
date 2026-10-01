<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\ShiftPattern;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * シフトを「休み」にした際に、勤務実績画面へ古いシフト時刻が
 * 残ってしまう不具合の回帰テスト（クライアント報告: 「シフト管理で
 * 休日にしたのに、勤務実績に反映されませんでした」）。
 *
 * 原因: daily_work_summaries.scheduled_start_time/end_timeは打刻や
 * 申請承認のタイミングでしか再計算されず、シフトを休みに変更しても
 * （shifts行自体は削除されるが）残っていた。勤務実績画面はシフトが
 * 無い日、この古い予定時刻があればそれを表示してしまう
 * （WorkReportTable.tsx参照）。
 */
class ShiftServiceUpsertSummaryTest extends TestCase
{
    use DatabaseTransactions;

    private ShiftService $service;

    private Company $company;

    private User $user;

    private ShiftPattern $shiftPattern;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ShiftService::class);
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

    public function test_休みにすると打刻の無い勤務実績の古い予定時刻が削除される(): void
    {
        // Arrange: 以前のシフトの予定時刻だけが残った、打刻の無い勤務実績
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-09-21',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'work_start' => null,
            'work_end' => null,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act: 休みに変更（shift_pattern_id=null）
        $this->service->upsertMany($this->company->id, [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-09-21',
                'shift_pattern_id' => null,
                'note' => null,
            ],
        ]);

        // Assert: 古い予定時刻を持つ勤務実績が削除され、画面は「休み」表示になる
        $this->assertDatabaseMissing('daily_work_summaries', ['id' => $summary->id]);
    }

    public function test_休みにしても実打刻のある勤務実績は削除されない(): void
    {
        // Arrange: 実際に出勤していた日の勤務実績（work_startあり）
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-09-21',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'work_start' => '2026-09-21 09:05:00',
            'work_end' => '2026-09-21 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act: 後からシフトを休みに変更
        $this->service->upsertMany($this->company->id, [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-09-21',
                'shift_pattern_id' => null,
                'note' => null,
            ],
        ]);

        // Assert: 実際に働いた記録は消さない
        $this->assertDatabaseHas('daily_work_summaries', ['id' => $summary->id]);
    }

    public function test_シフトを設定した場合は勤務実績に影響しない(): void
    {
        // Arrange
        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-09-21',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'work_start' => null,
            'work_end' => null,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act: 休みではなく通常のシフトを設定
        $this->service->upsertMany($this->company->id, [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-09-21',
                'shift_pattern_id' => $this->shiftPattern->id,
                'note' => null,
            ],
        ]);

        // Assert
        $this->assertDatabaseHas('daily_work_summaries', ['id' => $summary->id]);
        $this->assertDatabaseHas('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-09-21',
            'shift_pattern_id' => $this->shiftPattern->id,
        ]);
    }

    public function test_休みにした日に勤務実績が無くてもエラーにならない(): void
    {
        // Act
        $this->service->upsertMany($this->company->id, [
            [
                'user_id' => $this->user->id,
                'shift_date' => '2026-09-21',
                'shift_pattern_id' => null,
                'note' => null,
            ],
        ]);

        // Assert: シフトも作成されない
        $this->assertDatabaseMissing('shifts', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-09-21',
        ]);
    }
}

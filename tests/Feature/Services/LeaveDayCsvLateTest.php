<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\User;
use App\Services\DailyWorkSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * CSV出力でも承認済み休暇の日に遅刻早退を出さないことの検証。
 *
 * 画面だけを直すと帳票と食い違うため、出力側でも同じ判定を通す。
 */
class LeaveDayCsvLateTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    private DailyWorkSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create(['name' => '有給 太郎', 'is_retired' => false]);
        $this->service = app(DailyWorkSummaryService::class);
    }

    private function createSummary(?LeaveTypeEnum $leaveType): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'leave_type' => $leaveType,
            'leave_minutes' => $leaveType !== null ? 240 : null,
            'late_minutes' => 240,
            'early_leave_minutes' => 30,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);
    }

    private function csvRowForDate(): string
    {
        $csv = $this->service->generateCsv(
            $this->company->id,
            $this->user->id,
            '2026-06-24',
            '2026-06-24',
            $this->user,
        );

        $lines = array_values(array_filter(explode("\n", $csv), fn ($line) => str_contains($line, '6/24(水)')));

        return $lines[0] ?? '';
    }

    /**
     * @test
     */
    public function 半日有給の日は遅刻早退が0になる(): void
    {
        $this->createSummary(LeaveTypeEnum::PAID_LEAVE);

        $columns = str_getcsv($this->csvRowForDate());

        // コード,氏名,日付,勤務区分,シフト開始,シフト終了,シフト休憩入,シフト休憩出,出勤時刻,退勤時刻,休憩入①,休憩出①,休憩入②,休憩出②,労働時間,時間外,休日,深夜,遅刻早退,備考/申請
        $this->assertSame('0:00', $columns[18]);
    }

    /**
     * @test
     */
    public function 休暇のない日はそのまま出力する(): void
    {
        $this->createSummary(null);

        $columns = str_getcsv($this->csvRowForDate());

        // 遅刻240分+早退30分が遅刻早退の1列に合算される
        $this->assertSame('4:30', $columns[18]);
    }
}

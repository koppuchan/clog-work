<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\TimeRecordCorrection;
use App\Models\User;
use App\Services\DailyWorkSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 打刻修正履歴に、修正した管理者の名前を出す対応の検証
 * （クライアント要望: 打刻修正をした場合に、修正した管理者の名前を表示してほしい）。
 */
class CorrectionCorrectedByNameTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $staff;

    private User $admin;

    private DailyWorkSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->staff = User::factory()->forCompany($this->company->id)->create(['name' => '勤務 太郎']);
        $this->admin = User::factory()->forCompany($this->company->id)->create(['name' => '管理 花子']);
        $this->service = app(DailyWorkSummaryService::class);
    }

    private function createAdminCorrection(User $correctedBy): void
    {
        $record = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2026-09-24 20:00:00',
            'rounded_time' => '2026-09-24 20:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecordCorrection::query()->create([
            'time_record_id' => $record->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'before_record_time' => '2026-09-24 20:00:00',
            'before_rounded_time' => '2026-09-24 20:00:00',
            'before_record_source' => RecordSourceEnum::AUTO,
            'after_record_time' => '2026-09-24 20:30:00',
            'after_rounded_time' => '2026-09-24 20:30:00',
            'after_record_source' => RecordSourceEnum::MANUAL,
            'corrected_by' => $correctedBy->id,
        ]);
    }

    /**
     * @test
     */
    public function 管理者による修正には修正した管理者の名前が付く(): void
    {
        $this->createAdminCorrection($this->admin);

        $result = $this->service->getCorrectionsByUserIdAndDateRange(
            $this->staff->id,
            '2026-09-01',
            '2026-09-30',
        );

        $this->assertSame('admin', $result['2026-09-24'][0]['correction_source']);
        $this->assertSame('管理 花子', $result['2026-09-24'][0]['corrected_by_name']);
    }

    /**
     * @test
     */
    public function 修正者が複数いても、それぞれの修正に正しい名前が付く(): void
    {
        $otherAdmin = User::factory()->forCompany($this->company->id)->create(['name' => '別の 管理者']);
        $this->createAdminCorrection($this->admin);
        $this->createAdminCorrection($otherAdmin);

        $result = $this->service->getCorrectionsByUserIdAndDateRange(
            $this->staff->id,
            '2026-09-01',
            '2026-09-30',
        );

        $names = collect($result['2026-09-24'])->pluck('corrected_by_name')->sort()->values()->all();

        $this->assertSame(['別の 管理者', '管理 花子'], collect($names)->sort()->values()->all());
    }
}

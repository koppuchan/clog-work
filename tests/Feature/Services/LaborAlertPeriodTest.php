<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\AlertLevelEnum;
use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\CompanyLaborAlertSetting;
use App\Models\DailyWorkSummary;
use App\Models\User;
use App\Services\LaborAlertService;
use App\Services\PayrollPeriodService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 労務アラートの集計期間の検証。
 *
 * シフト表・勤務実績は締め期間で表示しているため、アラートの集計も
 * 同じ期間に揃える必要がある。暦月で数えると、締日をまたいだ残業が
 * 閾値を超えていてもアラートが出ない。
 */
class LaborAlertPeriodTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 締日20日 → 前月21日〜当月20日が1か月分
        $this->company = Company::factory()->create(['payroll_closing_day' => 20]);
        $this->user = User::factory()->forCompany($this->company->id)->create(['is_retired' => false]);
    }

    private function createSummary(string $date, int $overtimeMinutes, int $holidayMinutes = 0): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $date,
            'overtime_minutes' => $overtimeMinutes,
            'holiday_minutes' => $holidayMinutes,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    private function setThreshold(int $hours): void
    {
        CompanyLaborAlertSetting::query()->create([
            'company_id' => $this->company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION,
            'overtime_threshold_hours' => $hours,
            'overtime_threshold_minutes' => $hours * 60,
        ]);
    }

    /**
     * @test
     */
    public function 締め期間は前月21日から当月20日になる(): void
    {
        [$start, $end] = app(PayrollPeriodService::class)->resolve($this->company->id, 2026, 9);

        $this->assertSame('2026-08-21', $start->format('Y-m-d'));
        $this->assertSame('2026-09-20', $end->format('Y-m-d'));
    }

    /**
     * @test
     */
    public function 締日をまたいだ残業でもアラートが出る(): void
    {
        // 8/25に44時間分。暦月の9月で数えると0時間になってしまう
        $this->setThreshold(43);
        $this->createSummary('2026-08-25', 44 * 60);

        $alerts = app(LaborAlertService::class)->getAlerts($this->company->id, 2026, 9);

        $this->assertCount(1, $alerts);
    }

    /**
     * @test
     */
    public function 閾値に届かなければアラートは出ない(): void
    {
        $this->setThreshold(43);
        $this->createSummary('2026-08-25', 42 * 60);

        $this->assertCount(0, app(LaborAlertService::class)->getAlerts($this->company->id, 2026, 9));
    }

    /**
     * @test
     */
    public function 休日労働も合算して判定する(): void
    {
        // 労務アラートは時間外と休日の合計で判定する
        $this->setThreshold(43);
        $this->createSummary('2026-08-25', 40 * 60, 4 * 60);

        $this->assertCount(1, app(LaborAlertService::class)->getAlerts($this->company->id, 2026, 9));
    }

    /**
     * @test
     */
    public function 締め期間の外は数えない(): void
    {
        // 8/20 は前の期間にあたる
        $this->setThreshold(43);
        $this->createSummary('2026-08-20', 44 * 60);

        $this->assertCount(0, app(LaborAlertService::class)->getAlerts($this->company->id, 2026, 9));
    }

    /**
     * @test
     */
    public function スタッフ本人にもアラートが渡る(): void
    {
        // 管理者だけでなく本人の勤務実績画面にも表示する必要がある
        $this->setThreshold(43);
        $this->createSummary('2026-08-25', 44 * 60);

        $alerts = app(LaborAlertService::class)
            ->getAlertsForUser($this->company->id, $this->user->id, 2026, 9);

        $this->assertCount(1, $alerts);
        $this->assertSame($this->user->id, $alerts->first()['userId']);
    }

    /**
     * @test
     */
    public function 他のスタッフのアラートは渡さない(): void
    {
        $other = User::factory()->forCompany($this->company->id)->create(['is_retired' => false]);

        $this->setThreshold(43);
        $this->createSummary('2026-08-25', 44 * 60);

        $alerts = app(LaborAlertService::class)
            ->getAlertsForUser($this->company->id, $other->id, 2026, 9);

        $this->assertCount(0, $alerts);
    }

    /**
     * @test
     */
    public function 締日が月末なら暦月と同じになる(): void
    {
        $company = Company::factory()->create(['payroll_closing_day' => 31]);

        [$start, $end] = app(PayrollPeriodService::class)->resolve($company->id, 2026, 9);

        $this->assertSame('2026-09-01', $start->format('Y-m-d'));
        $this->assertSame('2026-09-30', $end->format('Y-m-d'));
    }
}

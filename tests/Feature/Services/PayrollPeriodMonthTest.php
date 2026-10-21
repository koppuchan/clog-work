<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Services\PayrollPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 今日が属する月度（締め期間）と、画面で選べる直近の月度の検証。
 *
 * クライアント要望: 労務アラートで、最新の月度（締日15日なら2026年11月
 * ＝10/16〜11/15）のリアルタイムのアラートも表示してほしい。
 */
class PayrollPeriodMonthTest extends TestCase
{
    use DatabaseTransactions;

    private PayrollPeriodService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollPeriodService::class);
    }

    private function companyWithClosingDay(int $closingDay): Company
    {
        return Company::factory()->create(['payroll_closing_day' => $closingDay]);
    }

    /**
     * @test
     */
    public function 締日の翌日以降は次の月の月度になる(): void
    {
        $company = $this->companyWithClosingDay(15);

        $this->assertSame(['year' => 2026, 'month' => 11], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-10-16')));
        $this->assertSame(['year' => 2026, 'month' => 11], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-10-21')));
        $this->assertSame(['year' => 2026, 'month' => 11], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-10-31')));
    }

    /**
     * @test
     */
    public function 締日当日までは当月の月度になる(): void
    {
        $company = $this->companyWithClosingDay(15);

        $this->assertSame(['year' => 2026, 'month' => 10], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-10-15')));
        $this->assertSame(['year' => 2026, 'month' => 10], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-10-01')));
    }

    /**
     * @test
     */
    public function 年末の締日後は翌年1月度になる(): void
    {
        $company = $this->companyWithClosingDay(15);

        $this->assertSame(['year' => 2027, 'month' => 1], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-12-20')));
    }

    /**
     * @test
     */
    public function 月末締めは暦月のままになる(): void
    {
        $company = $this->companyWithClosingDay(31);

        $this->assertSame(['year' => 2026, 'month' => 10], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-10-31')));
        $this->assertSame(['year' => 2026, 'month' => 10], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-10-21')));
    }

    /**
     * @test
     *
     * 2月に30日はないため、締日30日は2月末日（28日）までを当月の月度とする。
     */
    public function 締日が月の日数より大きい月は末日までを当月の月度とする(): void
    {
        $company = $this->companyWithClosingDay(30);

        $this->assertSame(['year' => 2026, 'month' => 2], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-02-28')));
        $this->assertSame(['year' => 2026, 'month' => 4], $this->service->periodMonthContaining($company->id, CarbonImmutable::parse('2026-03-31')));
    }

    /**
     * @test
     */
    public function 直近の月度は進行中の月度から新しい順に12件になる(): void
    {
        $company = $this->companyWithClosingDay(15);

        $periods = $this->service->recentPeriods($company->id, CarbonImmutable::parse('2026-10-21'));

        $this->assertCount(12, $periods);
        $this->assertSame('2026-11', $periods[0]['value']);
        $this->assertSame('2026年11月', $periods[0]['label']);
        $this->assertSame('10/16〜11/15', $periods[0]['range']);
        $this->assertSame('2026-10', $periods[1]['value']);
        $this->assertSame('9/16〜10/15', $periods[1]['range']);
        $this->assertSame('2025-12', $periods[11]['value']);
    }
}

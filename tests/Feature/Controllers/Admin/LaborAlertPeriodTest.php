<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 労務アラートは、今日が属する月度（進行中の締め期間）を最初に表示する。
 *
 * クライアント要望: 現在は2026年10月（2026/9/16-10/15）が最新のため、
 * 2026年11月（2026/10/16-11/15）のリアルタイムのアラートも表示してほしい。
 */
class LaborAlertPeriodTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['payroll_closing_day' => 15]);
        $this->admin = User::factory()->forCompany($this->company->id)->admin()->create(['is_retired' => false]);
        $this->employee = User::factory()->forCompany($this->company->id)->employee()->create(['name' => '残業 太郎', 'is_retired' => false]);

        $this->travelTo('2026-10-21 10:00:00');
    }

    private function overtimeOn(string $date, int $minutes): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'work_date' => $date,
            'overtime_minutes' => $minutes,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    private function openAlerts(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'admin')->get('/admin/alerts'.$query);
    }

    /**
     * @test
     */
    public function 指定が無ければ今日が属する月度が選ばれ_選択肢の先頭になる(): void
    {
        $this->openAlerts()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Alerts')
            ->where('selectedYear', 2026)
            ->where('selectedMonth', 11)
            ->where('periodOptions.0.value', '2026-11')
            ->where('periodOptions.0.range', '10/16〜11/15')
            ->where('periodOptions.1.value', '2026-10')
        );
    }

    /**
     * @test
     */
    public function 進行中の月度のアラートは_締日の翌日からの残業で出る(): void
    {
        // 10/17 の残業が50時間分（注意の既定は45時間）。10/16 以降なので11月度に入る
        $this->overtimeOn('2026-10-17', 3000);

        $this->openAlerts()->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 1)
            ->where('alerts.0.userName', '残業 太郎')
        );
    }

    /**
     * @test
     */
    public function 終わった月度を選ぶと_その期間の残業だけで判定される(): void
    {
        $this->overtimeOn('2026-10-17', 3000);

        // 10月度（9/16〜10/15）には10/17の残業は入らない
        $this->openAlerts('?year=2026&month=10')->assertInertia(fn (Assert $page) => $page
            ->where('selectedMonth', 10)
            ->where('summary.total', 0)
        );
    }

    /**
     * @test
     */
    public function 終わった月度の残業は_進行中の月度には含まれない(): void
    {
        $this->overtimeOn('2026-10-10', 3000);

        $this->openAlerts()->assertInertia(fn (Assert $page) => $page->where('summary.total', 0));
        $this->openAlerts('?year=2026&month=10')->assertInertia(fn (Assert $page) => $page->where('summary.total', 1));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Staff;

use App\Models\Company;
use App\Models\TimeRecordCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 打刻間違いの申請（休憩）の入力チェック。
 *
 * クライアント報告: 打刻が無いのに「休憩漏れ」と表示される。
 * 出勤10:08・退勤15:03の日勤に、出勤より前の10:00を休憩開始として（終了なしで）申請して
 * 承認すると、休憩開始が翌日の打刻として保存され、勤務の打刻がない日に休憩だけが残っていた。
 */
class ClockErrorBreakRequestTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->staff = User::factory()->forCompany($company->id)->employee()->create(['is_retired' => false]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides): TestResponse
    {
        return $this->actingAs($this->staff, 'staff')->post('/staff/requests', $overrides + [
            'target_date' => '2026-10-23',
            'type' => 'clock-error',
            'reason' => '税務署直行',
            'start_time' => '10:08',
            'end_time' => '15:03',
        ]);
    }

    private function requestCount(): int
    {
        return TimeRecordCorrectionRequest::query()->where('user_id', $this->staff->id)->count();
    }

    /**
     * @test
     */
    public function 休憩開始だけの申請は弾かれ_終了を入力するよう案内する(): void
    {
        $response = $this->submit(['break_start_time' => '12:00']);

        $response->assertSessionHasErrors('break_end_time');
        $this->assertStringContainsString('セットで', session('errors')->first('break_end_time'));
        $this->assertSame(0, $this->requestCount());
    }

    /**
     * @test
     */
    public function 休憩終了だけの申請も弾かれる(): void
    {
        $this->submit(['break_end_time' => '13:00'])->assertSessionHasErrors('break_start_time');
        $this->assertSame(0, $this->requestCount());
    }

    /**
     * @test
     *
     * 出勤より前の休憩は、日勤では勤務時間の範囲外として、画面に理由を出して弾く。
     */
    public function 勤務時間の範囲外の休憩は理由つきで弾かれる(): void
    {
        $response = $this->submit(['break_start_time' => '10:00', 'break_end_time' => '12:00']);

        $response->assertSessionHasErrors('break_start_time');
        $this->assertStringContainsString('勤務時間の範囲外', session('errors')->first('break_start_time'));
        $this->assertSame(0, $this->requestCount());
    }

    /**
     * @test
     */
    public function 勤務時間内で開始と終了が揃った休憩は申請できる(): void
    {
        $response = $this->submit(['break_start_time' => '12:00', 'break_end_time' => '13:00']);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $this->requestCount());
    }

    /**
     * @test
     *
     * 休憩を取っていない場合は、両方を空欄にして申請できる（これまでどおり）。
     */
    public function 休憩を空欄にした申請はできる(): void
    {
        $this->submit([])->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $this->requestCount());
    }
}

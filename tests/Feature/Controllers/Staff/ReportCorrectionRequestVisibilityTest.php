<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Staff;

use App\Enums\RequestStatusEnum;
use App\Models\Company;
use App\Models\User;
use App\Services\TimeRecordCorrectionRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * スタッフの勤務実績画面に、自分の打刻修正申請（打刻間違い）の状態が
 * 表示されるかの検証。
 *
 * 打刻修正申請は通常の申請（有給等）とは別テーブル（time_record_correction_
 * requests）で管理されており、管理者の申請管理画面ではマージして表示して
 * いたが、スタッフ自身の勤務実績画面ではこれまで取得すらしていなかった。
 * そのため申請しても「申請中」等の状態が一切表示されず、休憩などの修正が
 * 承認待ちのまま反映されていないことにも気づけなかった
 * （クライアント報告: 「申請中」表示が出ない／2箇所申請したが休憩が
 * 反映されない）。
 */
class ReportCorrectionRequestVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        $this->staff = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create(['is_retired' => false]);
    }

    /**
     * @test
     */
    public function 打刻修正申請が勤務実績画面のrequestsに含まれる(): void
    {
        // Arrange: 休憩のみの打刻修正申請（追加申請を想定）
        app(TimeRecordCorrectionRequestService::class)->createClockErrorRequest(
            $this->company->id,
            $this->staff->id,
            [
                'target_date' => '2026-09-01',
                'reason' => '休憩の打刻漏れ',
                'start_time' => null,
                'end_time' => null,
                'break_start_time' => '12:00',
                'break_end_time' => '13:00',
            ]
        );

        // Act
        $response = $this->actingAs($this->staff, 'staff')->get('/staff/reports?month=2026-09');

        // Assert: 申請中の打刻間違いとして画面に渡る
        $response->assertInertia(fn (Assert $page) => $page
            ->has('requests.2026-09-01', 1)
            ->where('requests.2026-09-01.0.type.code', 'clock-error')
            ->where('requests.2026-09-01.0.type.label', '打刻間違い')
            ->where('requests.2026-09-01.0.status.value', RequestStatusEnum::PENDING->value)
        );
    }

    /**
     * @test
     *
     * 同じ日に通常申請（有給等）と打刻修正申請の両方がある場合、
     * どちらも欠けずに画面へ渡る。
     */
    public function 同じ日の通常申請と打刻修正申請が両方とも含まれる(): void
    {
        // Arrange
        $this->actingAs($this->staff, 'staff')->post('/staff/requests', [
            'target_date' => '2026-09-01',
            'type' => 'paid-leave',
            'reason' => '私用のため',
        ]);

        app(TimeRecordCorrectionRequestService::class)->createClockErrorRequest(
            $this->company->id,
            $this->staff->id,
            [
                'target_date' => '2026-09-01',
                'reason' => '出退勤の打刻間違い',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'break_start_time' => null,
                'break_end_time' => null,
            ]
        );

        // Act
        $response = $this->actingAs($this->staff, 'staff')->get('/staff/reports?month=2026-09');

        // Assert: 通常申請1件 + 打刻修正申請1件の計2件が同じ日に渡る
        $response->assertInertia(fn (Assert $page) => $page
            ->has('requests.2026-09-01', 2)
        );
    }

    /**
     * @test
     *
     * 対象期間外の打刻修正申請は含まれない。
     */
    public function 対象期間外の打刻修正申請は含まれない(): void
    {
        // Arrange: 表示月(9月)の範囲外(8月)の打刻修正申請
        app(TimeRecordCorrectionRequestService::class)->createClockErrorRequest(
            $this->company->id,
            $this->staff->id,
            [
                'target_date' => '2026-08-15',
                'reason' => '範囲外のテスト',
                'start_time' => '09:00',
                'end_time' => null,
                'break_start_time' => null,
                'break_end_time' => null,
            ]
        );

        // Act
        $response = $this->actingAs($this->staff, 'staff')->get('/staff/reports?month=2026-09');

        // Assert
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('requests.2026-08-15')
        );
    }
}

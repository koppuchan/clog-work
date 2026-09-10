<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use App\Services\TimeRecordCorrectionRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 管理者の勤務実績画面に、承認済みの打刻修正申請（打刻間違い）が
 * approvedRequests に含まれるかの検証。
 *
 * 休憩のみの打刻修正申請のように、既存の打刻レコードが無く新規追加になる
 * ケースは time_record_corrections（修正履歴）には載らないため、これまで
 * 管理者画面では承認しても「打刻修正」バッジも申請バッジも一切表示されず、
 * 承認した内容が反映されたか確認できなかった
 * （クライアント報告: 休憩入と休憩出の打刻修正を承認したが打刻修正にならなかった）。
 */
class ReportCorrectionRequestVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create(['is_retired' => false]);

        $this->staff = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create(['is_retired' => false]);
    }

    /**
     * @test
     *
     * 休憩のみの打刻修正申請（既存打刻が無いため新規追加になるケース）を
     * 承認すると、打刻修正履歴には載らなくても approvedRequests には
     * 「打刻間違い」として含まれる。
     */
    public function 承認済みの打刻修正申請がapproved_requestsに含まれる(): void
    {
        // Arrange
        $service = app(TimeRecordCorrectionRequestService::class);
        $request = $service->createClockErrorRequest(
            $this->company->id,
            $this->staff->id,
            [
                'target_date' => '2026-09-09',
                'reason' => '休憩の打刻漏れ',
                'start_time' => null,
                'end_time' => null,
                'break_start_time' => '14:06',
                'break_end_time' => '15:05',
            ]
        );
        $service->approveCorrectionRequest($request->id, $this->admin->id);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get(
            '/admin/reports?user_id='.$this->staff->id
            .'&start_date=2026-09-01&end_date=2026-09-30'
        );

        // Assert: 打刻修正履歴（timeRecordCorrections）には現れなくても、
        // approvedRequestsには「打刻間違い」として現れる
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports')
            ->has('approvedRequests', 1)
            ->where('approvedRequests.0.type.code', 'clock-error')
            ->where('approvedRequests.0.type.label', '打刻間違い')
            ->where('approvedRequests.0.target_date', '2026-09-09')
            ->missing('timeRecordCorrections.2026-09-09')
        );
    }

    /**
     * @test
     *
     * 未承認（申請中）の打刻修正申請はapprovedRequestsに含まれない。
     */
    public function 未承認の打刻修正申請はapproved_requestsに含まれない(): void
    {
        // Arrange
        app(TimeRecordCorrectionRequestService::class)->createClockErrorRequest(
            $this->company->id,
            $this->staff->id,
            [
                'target_date' => '2026-09-09',
                'reason' => '休憩の打刻漏れ',
                'start_time' => null,
                'end_time' => null,
                'break_start_time' => '14:06',
                'break_end_time' => '15:05',
            ]
        );

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get(
            '/admin/reports?user_id='.$this->staff->id
            .'&start_date=2026-09-01&end_date=2026-09-30'
        );

        // Assert
        $response->assertInertia(fn (Assert $page) => $page
            ->has('approvedRequests', 0)
        );
    }
}

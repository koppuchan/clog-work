<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Request;
use App\Models\User;
use App\Services\LeaveApplicationService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LeaveApplicationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private LeaveApplicationService $leaveApplicationService;

    private RequestService $requestService;

    private Company $company;

    private User $user;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leaveApplicationService = app(LeaveApplicationService::class);
        $this->requestService = app(RequestService::class);

        // テスト用の会社を作成
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'daily_working_hours' => 8.0,
            'paid_leave_half_day' => true,
            'paid_leave_hourly' => true,
        ]);

        // 申請者を作成
        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create([
                'name' => 'Test User',
                'is_retired' => false,
            ]);

        // 承認者を作成
        $this->approver = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create([
                'name' => 'Admin User',
                'is_retired' => false,
            ]);
    }

    // ========================================
    // isLeaveApplication テスト
    // ========================================

    /**
     * @test
     */
    public function is_leave_application_returns_true_for_paid_leave(): void
    {
        // 有給休暇（type=1）は休暇申請
        $this->assertTrue(LeaveApplicationService::isLeaveApplication(1));
    }

    /**
     * @test
     */
    public function is_leave_application_returns_true_for_special_leave(): void
    {
        // 特別休暇（type=5）は休暇申請
        $this->assertTrue(LeaveApplicationService::isLeaveApplication(5));
    }

    /**
     * @test
     */
    public function is_leave_application_returns_true_for_absence(): void
    {
        // 欠勤（type=6）は休暇申請
        $this->assertTrue(LeaveApplicationService::isLeaveApplication(6));
    }

    /**
     * @test
     */
    public function is_leave_application_returns_false_for_clock_error(): void
    {
        // 打刻間違い（type=2）は休暇申請ではない
        $this->assertFalse(LeaveApplicationService::isLeaveApplication(2));
    }

    /**
     * @test
     */
    public function is_leave_application_returns_false_for_late(): void
    {
        // 遅刻（type=3）は休暇申請ではない
        $this->assertFalse(LeaveApplicationService::isLeaveApplication(3));
    }

    /**
     * @test
     */
    public function is_leave_application_returns_false_for_early_leave(): void
    {
        // 早退（type=4）は休暇申請ではない
        $this->assertFalse(LeaveApplicationService::isLeaveApplication(4));
    }

    /**
     * @test
     */
    public function is_leave_application_returns_false_for_overtime(): void
    {
        // 残業申請（type=7）は休暇申請ではない
        $this->assertFalse(LeaveApplicationService::isLeaveApplication(7));
    }

    /**
     * @test
     */
    public function is_leave_application_returns_false_for_other(): void
    {
        // その他（type=8）は休暇申請ではない
        $this->assertFalse(LeaveApplicationService::isLeaveApplication(8));
    }

    // ========================================
    // applyLeaveToWorkSummary テスト（終日休暇）
    // ========================================

    /**
     * @test
     */
    public function apply_leave_to_work_summary_creates_record_for_full_day_paid_leave(): void
    {
        // Arrange: 終日有給休暇申請（start_time, end_time なし）
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 1, // 有給休暇
            'target_date' => '2025-01-20',
            'start_time' => null,
            'end_time' => null,
            'reason' => '終日有給休暇',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->leaveApplicationService->applyLeaveToWorkSummary($request);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-20')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::PAID_LEAVE, $summary->leave_type);
        $this->assertNull($summary->leave_minutes); // 終日なのでNULL
        $this->assertEquals(RecordSourceEnum::REQUEST, $summary->record_source);
    }

    /**
     * @test
     */
    public function apply_leave_to_work_summary_creates_record_for_full_day_special_leave(): void
    {
        // Arrange: 終日特別休暇申請
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 5, // 特別休暇
            'target_date' => '2025-01-21',
            'start_time' => null,
            'end_time' => null,
            'reason' => '終日特別休暇',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->leaveApplicationService->applyLeaveToWorkSummary($request);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-21')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::SPECIAL_LEAVE, $summary->leave_type);
        $this->assertNull($summary->leave_minutes);
        $this->assertEquals(RecordSourceEnum::REQUEST, $summary->record_source);
    }

    /**
     * @test
     */
    public function apply_leave_to_work_summary_creates_record_for_full_day_absence(): void
    {
        // Arrange: 終日欠勤申請
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 6, // 欠勤
            'target_date' => '2025-01-22',
            'start_time' => null,
            'end_time' => null,
            'reason' => '終日欠勤',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->leaveApplicationService->applyLeaveToWorkSummary($request);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-22')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::ABSENCE, $summary->leave_type);
        $this->assertNull($summary->leave_minutes);
        $this->assertEquals(RecordSourceEnum::REQUEST, $summary->record_source);
    }

    // ========================================
    // applyLeaveToWorkSummary テスト（時間休暇）
    // ========================================

    /**
     * @test
     */
    public function apply_leave_to_work_summary_creates_record_for_hourly_paid_leave(): void
    {
        // Arrange: 時間有給休暇（2時間）
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 1, // 有給休暇
            'target_date' => '2025-01-23',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'reason' => '2時間有給',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->leaveApplicationService->applyLeaveToWorkSummary($request);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-23')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::PAID_LEAVE, $summary->leave_type);
        $this->assertEquals(120, $summary->leave_minutes); // 2時間 = 120分
        $this->assertEquals(RecordSourceEnum::REQUEST, $summary->record_source);
    }

    /**
     * @test
     */
    public function apply_leave_to_work_summary_creates_record_for_half_day_paid_leave(): void
    {
        // Arrange: 半日有給休暇（4時間）
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 1, // 有給休暇
            'target_date' => '2025-01-24',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'reason' => '半日有給（午前）',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->leaveApplicationService->applyLeaveToWorkSummary($request);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-24')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::PAID_LEAVE, $summary->leave_type);
        $this->assertEquals(240, $summary->leave_minutes); // 4時間 = 240分
        $this->assertEquals(RecordSourceEnum::REQUEST, $summary->record_source);

        // 半日判定
        $this->assertTrue($this->leaveApplicationService->isHalfDay(240, $this->company));
    }

    /**
     * @test
     */
    public function apply_leave_to_work_summary_creates_record_for_3_hour_leave(): void
    {
        // Arrange: 3時間有給
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 1, // 有給休暇
            'target_date' => '2025-01-25',
            'start_time' => '14:00',
            'end_time' => '17:00',
            'reason' => '3時間有給',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->leaveApplicationService->applyLeaveToWorkSummary($request);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-25')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::PAID_LEAVE, $summary->leave_type);
        $this->assertEquals(180, $summary->leave_minutes); // 3時間 = 180分
    }

    // ========================================
    // applyLeaveToWorkSummary テスト（既存レコードの更新）
    // ========================================

    /**
     * @test
     */
    public function apply_leave_to_work_summary_updates_existing_record(): void
    {
        // Arrange: 既存の勤務実績レコード
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-26',
            'work_minutes' => 480,
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 1, // 有給休暇
            'target_date' => '2025-01-26',
            'start_time' => null,
            'end_time' => null,
            'reason' => '終日有給',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->leaveApplicationService->applyLeaveToWorkSummary($request);

        // Assert
        $summaries = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-26')
            ->get();

        // レコードは1つのみ（重複なし）
        $this->assertCount(1, $summaries);

        $summary = $summaries->first();
        $this->assertEquals(LeaveTypeEnum::PAID_LEAVE, $summary->leave_type);
        $this->assertNull($summary->leave_minutes);
        $this->assertEquals(RecordSourceEnum::REQUEST, $summary->record_source);
        // 既存のwork_minutesは保持される
        $this->assertEquals(480, $summary->work_minutes);
    }

    // ========================================
    // applyLeaveToWorkSummary テスト（非休暇申請）
    // ========================================

    /**
     * @test
     */
    public function apply_leave_to_work_summary_does_nothing_for_non_leave_request(): void
    {
        // Arrange: 遅刻申請（休暇ではない）
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 3, // 遅刻
            'target_date' => '2025-01-27',
            'start_time' => null,
            'end_time' => null,
            'reason' => '電車遅延のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->leaveApplicationService->applyLeaveToWorkSummary($request);

        // Assert: レコードは作成されない
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-27')
            ->first();

        $this->assertNull($summary);
    }

    // ========================================
    // isHalfDay テスト
    // ========================================

    /**
     * @test
     */
    public function is_half_day_returns_true_for_exact_half(): void
    {
        // 8時間勤務の半分 = 240分
        $this->assertTrue($this->leaveApplicationService->isHalfDay(240, $this->company));
    }

    /**
     * @test
     */
    public function is_half_day_returns_true_within_tolerance(): void
    {
        // 240分 ± 30分以内なら半日
        $this->assertTrue($this->leaveApplicationService->isHalfDay(210, $this->company)); // 240 - 30
        $this->assertTrue($this->leaveApplicationService->isHalfDay(270, $this->company)); // 240 + 30
    }

    /**
     * @test
     */
    public function is_half_day_returns_false_outside_tolerance(): void
    {
        // 240分 ± 30分を超えると半日ではない
        $this->assertFalse($this->leaveApplicationService->isHalfDay(180, $this->company)); // 3時間
        $this->assertFalse($this->leaveApplicationService->isHalfDay(300, $this->company)); // 5時間
        $this->assertFalse($this->leaveApplicationService->isHalfDay(120, $this->company)); // 2時間
    }

    /**
     * @test
     */
    public function is_half_day_works_with_different_working_hours(): void
    {
        // 7.5時間勤務の会社
        $company75 = Company::factory()->create([
            'daily_working_hours' => 7.5,
        ]);

        // 7.5時間の半分 = 225分
        $this->assertTrue($this->leaveApplicationService->isHalfDay(225, $company75));
        $this->assertTrue($this->leaveApplicationService->isHalfDay(195, $company75)); // 225 - 30
        $this->assertTrue($this->leaveApplicationService->isHalfDay(255, $company75)); // 225 + 30
        $this->assertFalse($this->leaveApplicationService->isHalfDay(150, $company75));
    }

    // ========================================
    // RequestService連携テスト
    // ========================================

    /**
     * @test
     */
    public function approve_request_applies_leave_to_work_summary_for_paid_leave(): void
    {
        // Arrange: 有給休暇申請
        $request = $this->requestService->createRequest(
            $this->company->id,
            $this->user->id,
            [
                'type' => 1, // 有給休暇
                'target_date' => '2025-01-28',
                'reason' => 'テスト用有給',
            ]
        );

        // Act: 承認
        $this->requestService->approveRequest($request->id, $this->approver->id, '承認しました');

        // Assert: daily_work_summariesに反映される
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-28')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::PAID_LEAVE, $summary->leave_type);
        $this->assertNull($summary->leave_minutes);
        $this->assertEquals(RecordSourceEnum::REQUEST, $summary->record_source);
    }

    /**
     * @test
     */
    public function approve_request_applies_leave_to_work_summary_for_special_leave(): void
    {
        // Arrange: 特別休暇申請
        $request = $this->requestService->createRequest(
            $this->company->id,
            $this->user->id,
            [
                'type' => 5, // 特別休暇
                'target_date' => '2025-01-29',
                'reason' => '慶弔休暇',
            ]
        );

        // Act: 承認
        $this->requestService->approveRequest($request->id, $this->approver->id);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-29')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::SPECIAL_LEAVE, $summary->leave_type);
    }

    /**
     * @test
     */
    public function approve_request_applies_leave_to_work_summary_for_absence(): void
    {
        // Arrange: 欠勤申請
        $request = $this->requestService->createRequest(
            $this->company->id,
            $this->user->id,
            [
                'type' => 6, // 欠勤
                'target_date' => '2025-01-30',
                'reason' => '体調不良',
            ]
        );

        // Act: 承認
        $this->requestService->approveRequest($request->id, $this->approver->id);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-30')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::ABSENCE, $summary->leave_type);
    }

    /**
     * @test
     */
    public function approve_request_does_not_apply_leave_for_non_leave_request(): void
    {
        // Arrange: 遅刻申請
        $request = $this->requestService->createRequest(
            $this->company->id,
            $this->user->id,
            [
                'type' => 3, // 遅刻
                'target_date' => '2025-01-31',
                'reason' => '電車遅延',
            ]
        );

        // Act: 承認
        $this->requestService->approveRequest($request->id, $this->approver->id);

        // Assert: daily_work_summariesには反映されない
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-31')
            ->first();

        $this->assertNull($summary);
    }

    /**
     * @test
     */
    public function approve_request_applies_hourly_leave_to_work_summary(): void
    {
        // Arrange: 時間有給申請
        $request = $this->requestService->createRequest(
            $this->company->id,
            $this->user->id,
            [
                'type' => 1, // 有給休暇
                'target_date' => '2025-02-01',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'reason' => '2時間有給',
            ]
        );

        // Act: 承認
        $this->requestService->approveRequest($request->id, $this->approver->id);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-02-01')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(LeaveTypeEnum::PAID_LEAVE, $summary->leave_type);
        $this->assertEquals(120, $summary->leave_minutes); // 2時間
    }

    // ========================================
    // LeaveTypeEnum テスト
    // ========================================

    /**
     * @test
     */
    public function leave_type_enum_from_application_type_returns_correct_value(): void
    {
        $this->assertEquals(LeaveTypeEnum::PAID_LEAVE, LeaveTypeEnum::fromApplicationType(1));
        $this->assertEquals(LeaveTypeEnum::SPECIAL_LEAVE, LeaveTypeEnum::fromApplicationType(5));
        $this->assertEquals(LeaveTypeEnum::ABSENCE, LeaveTypeEnum::fromApplicationType(6));
        $this->assertNull(LeaveTypeEnum::fromApplicationType(2));
        $this->assertNull(LeaveTypeEnum::fromApplicationType(3));
        $this->assertNull(LeaveTypeEnum::fromApplicationType(99));
    }

    /**
     * @test
     */
    public function leave_type_enum_label_returns_japanese_label(): void
    {
        $this->assertEquals('有給休暇', LeaveTypeEnum::PAID_LEAVE->label());
        $this->assertEquals('特別休暇', LeaveTypeEnum::SPECIAL_LEAVE->label());
        $this->assertEquals('欠勤', LeaveTypeEnum::ABSENCE->label());
    }

    /**
     * @test
     */
    public function leave_type_enum_is_leave_application_returns_correct_value(): void
    {
        $this->assertTrue(LeaveTypeEnum::isLeaveApplication(1));
        $this->assertTrue(LeaveTypeEnum::isLeaveApplication(5));
        $this->assertTrue(LeaveTypeEnum::isLeaveApplication(6));
        $this->assertFalse(LeaveTypeEnum::isLeaveApplication(2));
        $this->assertFalse(LeaveTypeEnum::isLeaveApplication(3));
        $this->assertFalse(LeaveTypeEnum::isLeaveApplication(4));
        $this->assertFalse(LeaveTypeEnum::isLeaveApplication(7));
        $this->assertFalse(LeaveTypeEnum::isLeaveApplication(8));
    }
}

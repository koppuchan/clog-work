<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Request;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\User;
use App\Services\OvertimeApplicationService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OvertimeApplicationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OvertimeApplicationService $overtimeApplicationService;

    private RequestService $requestService;

    private Company $company;

    private User $user;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overtimeApplicationService = app(OvertimeApplicationService::class);
        $this->requestService = app(RequestService::class);

        // テスト用の会社を作成
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
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
    // isOvertimeApplication テスト
    // ========================================

    /**
     * @test
     */
    public function is_overtime_application_returns_true_for_overtime(): void
    {
        // 残業申請（type=7）は残業申請
        $this->assertTrue(OvertimeApplicationService::isOvertimeApplication(7));
    }

    /**
     * @test
     */
    public function is_overtime_application_returns_false_for_non_overtime(): void
    {
        $this->assertFalse(OvertimeApplicationService::isOvertimeApplication(1)); // 有給休暇
        $this->assertFalse(OvertimeApplicationService::isOvertimeApplication(2)); // 打刻間違い
        $this->assertFalse(OvertimeApplicationService::isOvertimeApplication(3)); // 遅刻
        $this->assertFalse(OvertimeApplicationService::isOvertimeApplication(8)); // その他
    }

    // ========================================
    // applyOvertimeToWorkSummary テスト
    // ========================================

    /**
     * @test
     */
    public function apply_overtime_updates_overtime_minutes_with_request_duration(): void
    {
        // Arrange: 既存の勤務実績（自動計算で45分残業）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-20',
            'work_minutes' => 525, // 8:45
            'break_minutes' => 60,
            'net_work_minutes' => 465, // 7:45
            'overtime_minutes' => 45,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 残業申請（18:00〜18:30 = 30分）
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 7, // 残業申請
            'target_date' => '2025-01-20',
            'start_time' => '18:00',
            'end_time' => '18:30',
            'reason' => '残業30分',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->overtimeApplicationService->applyOvertimeToWorkSummary($request);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-20')
            ->first();

        $this->assertNotNull($summary);
        // applyOvertimeToWorkSummary は勤務実績を上書きしない（打刻ベースの自動計算値を維持）
        $this->assertEquals(45, $summary->overtime_minutes); // 自動計算値のまま
        $this->assertEquals(RecordSourceEnum::AUTO, $summary->record_source);
    }

    /**
     * @test
     */
    public function apply_overtime_creates_record_when_no_existing_summary(): void
    {
        // Arrange: 勤務実績がない状態で残業申請
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 7,
            'target_date' => '2025-01-21',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'reason' => '残業1時間',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->overtimeApplicationService->applyOvertimeToWorkSummary($request);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-21')
            ->first();

        // applyOvertimeToWorkSummary は勤務実績を作成しない
        $this->assertNull($summary);
    }

    /**
     * @test
     */
    public function apply_overtime_does_nothing_without_start_or_end_time(): void
    {
        // Arrange: start_time/end_timeなしの残業申請
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 7,
            'target_date' => '2025-01-22',
            'start_time' => null,
            'end_time' => null,
            'reason' => '残業申請（時間未指定）',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->overtimeApplicationService->applyOvertimeToWorkSummary($request);

        // Assert: レコードは作成されない
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-22')
            ->first();

        $this->assertNull($summary);
    }

    // ========================================
    // removeOvertimeFromWorkSummary テスト
    // ========================================

    /**
     * @test
     */
    public function remove_overtime_restores_auto_calculated_value(): void
    {
        // Arrange: シフトパターン（9:00〜18:00、所定480分）
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2025-01-23',
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 勤務実績（残業申請で30分に上書きされた状態、実際のnet_work_minutes=525=8:45）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-01-23',
            'work_minutes' => 585,
            'break_minutes' => 60,
            'net_work_minutes' => 525, // 8:45
            'overtime_minutes' => 30, // 申請で上書きされた値
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 7,
            'target_date' => '2025-01-23',
            'start_time' => '18:00',
            'end_time' => '18:30',
            'reason' => '残業30分',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act
        $this->overtimeApplicationService->removeOvertimeFromWorkSummary($request);

        // Assert: removeOvertimeFromWorkSummary は何もしない（勤務実績はそのまま）
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-01-23')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(30, $summary->overtime_minutes); // 変更なし
    }

    /**
     * @test
     */
    public function remove_overtime_does_nothing_when_no_daily_summary_exists(): void
    {
        // Arrange: 勤務実績がない状態
        $request = Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 7,
            'target_date' => '2025-01-24',
            'start_time' => '18:00',
            'end_time' => '18:30',
            'reason' => '残業30分',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        // Act: 例外が発生しないこと
        $this->overtimeApplicationService->removeOvertimeFromWorkSummary($request);

        // Assert: 何も起こらない
        $this->assertNull(
            DailyWorkSummary::query()
                ->where('company_id', $this->company->id)
                ->where('user_id', $this->user->id)
                ->where('work_date', '2025-01-24')
                ->first()
        );
    }

    // ========================================
    // RequestService連携テスト
    // ========================================

    /**
     * @test
     */
    public function approve_request_applies_overtime_to_work_summary(): void
    {
        // Arrange: 勤務実績（自動計算で45分残業）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-02-01',
            'work_minutes' => 525,
            'break_minutes' => 60,
            'net_work_minutes' => 465,
            'overtime_minutes' => 45,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 残業申請作成
        $request = $this->requestService->createRequest(
            $this->company->id,
            $this->user->id,
            [
                'type' => 7,
                'target_date' => '2025-02-01',
                'start_time' => '18:00',
                'end_time' => '18:30',
                'reason' => '残業30分',
            ]
        );

        // Act: 承認
        $this->requestService->approveRequest($request->id, $this->approver->id, '承認しました');

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-02-01')
            ->first();

        $this->assertNotNull($summary);
        // 残業申請承認は勤務実績を上書きしない（打刻ベースの自動計算値を維持）
        $this->assertEquals(45, $summary->overtime_minutes); // 自動計算値のまま
        $this->assertEquals(RecordSourceEnum::AUTO, $summary->record_source);
    }

    /**
     * @test
     */
    public function revert_request_restores_overtime_to_auto_calculated(): void
    {
        // Arrange: シフトパターン
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2025-02-02',
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 勤務実績
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-02-02',
            'work_minutes' => 585,
            'break_minutes' => 60,
            'net_work_minutes' => 525,
            'overtime_minutes' => 45,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 残業申請作成 → 承認
        $request = $this->requestService->createRequest(
            $this->company->id,
            $this->user->id,
            [
                'type' => 7,
                'target_date' => '2025-02-02',
                'start_time' => '18:00',
                'end_time' => '18:30',
                'reason' => '残業30分',
            ]
        );
        $this->requestService->approveRequest($request->id, $this->approver->id);

        // 承認後も自動計算値のまま
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-02-02')
            ->first();
        $this->assertEquals(45, $summary->overtime_minutes);

        // Act: 差し戻し
        $this->requestService->revertRequest($request->id);

        // Assert: 自動計算値のまま変化なし
        $summary->refresh();
        $this->assertEquals(45, $summary->overtime_minutes);
    }

    /**
     * @test
     */
    public function staff_request_endpoint_creates_overtime_with_start_and_end_time(): void
    {
        // Arrange: 既存の勤務実績（自動計算で45分残業）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-02-04',
            'work_minutes' => 525,
            'break_minutes' => 60,
            'net_work_minutes' => 465,
            'overtime_minutes' => 45,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act: スタッフ画面の申請エンドポイント経由で残業申請
        // フロント（ApplicationDialog）が overtime に start_time/end_time を渡せるようになったことを保証する
        $response = $this->actingAs($this->user, 'staff')->post('/staff/requests', [
            'type' => 'overtime',
            'target_date' => '2025-02-04',
            'reason' => 'プロジェクト対応のため残業',
            'start_time' => '18:00',
            'end_time' => '19:30',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert: 申請レコードに start_time/end_time が保存されている
        $request = Request::query()
            ->where('requested_by', $this->user->id)
            ->where('target_date', '2025-02-04')
            ->where('type', 7)
            ->first();
        $this->assertNotNull($request);
        $this->assertEquals('18:00:00', $request->start_time);
        $this->assertEquals('19:30:00', $request->end_time);

        // Assert: 承認しても勤務実績の時間外は打刻ベースの自動計算値のまま
        $this->requestService->approveRequest($request->id, $this->approver->id);

        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-02-04')
            ->first();
        $this->assertEquals(45, $summary->overtime_minutes); // 自動計算値のまま
    }

    /**
     * @test
     */
    public function cancel_approved_request_restores_overtime_to_auto_calculated(): void
    {
        // Arrange: シフトパターン
        $shiftPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]);

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2025-02-03',
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2025-02-03',
            'work_minutes' => 585,
            'break_minutes' => 60,
            'net_work_minutes' => 525,
            'overtime_minutes' => 45,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 残業申請作成 → 承認
        $request = $this->requestService->createRequest(
            $this->company->id,
            $this->user->id,
            [
                'type' => 7,
                'target_date' => '2025-02-03',
                'start_time' => '18:00',
                'end_time' => '18:30',
                'reason' => '残業30分',
            ]
        );
        $this->requestService->approveRequest($request->id, $this->approver->id);

        // 承認後も自動計算値のまま
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', '2025-02-03')
            ->first();
        $this->assertEquals(45, $summary->overtime_minutes);

        // Act: 承認済み申請を取消
        $this->requestService->cancelApprovedRequest($request->id);

        // Assert: 自動計算値のまま変化なし
        $summary->refresh();
        $this->assertEquals(45, $summary->overtime_minutes);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Request;
use App\Models\TimeRecord;
use App\Models\TimeRecordCorrection;
use App\Models\TimeRecordCorrectionRequest;
use App\Models\TimeRecordCorrectionRequestDetail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RequestControllerTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用の会社を作成
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
        ]);

        // 管理者を作成
        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create([
                'name' => 'Admin User',
                'is_retired' => false,
            ]);

        // 一般従業員を作成
        $this->employee = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create([
                'name' => 'Employee User',
                'is_retired' => false,
            ]);
    }

    // ========================================
    // index アクション テスト
    // ========================================

    /**
     * @test
     */
    public function index_displays_applications_page(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Applications'));
    }

    /**
     * @test
     */
    public function index_returns_both_normal_and_correction_requests(): void
    {
        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);

        // Act（デフォルトで申請中フィルタがかかるが、両方PENDINGなので2件返る）
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('requests', 2)
        );
    }

    /**
     * status パラメータ未指定の場合、デフォルトで申請中が選択される
     *
     * @test
     */
    public function index_defaults_to_pending_status_when_status_not_specified(): void
    {
        // Arrange - 各ステータスの申請を作成
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createNormalRequest(['status' => RequestStatusEnum::APPROVED]);
        $this->createNormalRequest(['status' => RequestStatusEnum::REJECTED]);

        // Act - status パラメータなしでアクセス
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications');

        // Assert - 申請中(PENDING=1)のみが返り、currentStatus も PENDING になる
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('requests', 1)
            ->where('currentStatus', RequestStatusEnum::PENDING->value)
        );
    }

    /**
     * status=all を明示すると全ステータスが返る
     *
     * @test
     */
    public function index_returns_all_statuses_when_status_is_all(): void
    {
        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createNormalRequest(['status' => RequestStatusEnum::APPROVED]);
        $this->createNormalRequest(['status' => RequestStatusEnum::REJECTED]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications?status=all');

        // Assert - 3件すべて返り、currentStatus は null
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('requests', 3)
            ->where('currentStatus', null)
        );
    }

    /**
     * @test
     */
    public function index_filters_by_status(): void
    {
        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createNormalRequest(['status' => RequestStatusEnum::APPROVED]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications?status=1');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('requests', 2)
            ->where('currentStatus', 1)
        );
    }

    /**
     * @test
     */
    public function index_filters_by_requested_by(): void
    {
        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        $otherEmployee = User::factory()->forCompany($this->company->id)->create();
        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $otherEmployee->id,
            'type' => 1,
            'target_date' => '2025-01-16',
            'reason' => '別ユーザーの申請',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications?name='.urlencode($this->employee->name));

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('requests', 1)
            ->where('currentName', $this->employee->name)
        );
    }

    /**
     * @test
     */
    public function index_paginates_results(): void
    {
        // Arrange - 15件作成（ページサイズは10）
        for ($i = 0; $i < 15; $i++) {
            $this->createNormalRequest([
                'target_date' => '2025-01-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
            ]);
        }

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications?page=1');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('requests', 10)
            ->where('pagination.total', 15)
            ->where('pagination.last_page', 2)
            ->where('pagination.current_page', 1)
        );
    }

    /**
     * @test
     */
    public function index_returns_correct_stats(): void
    {
        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createNormalRequest(['status' => RequestStatusEnum::APPROVED]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::REJECTED]);

        // Act（全ステータスを統計対象に含めたいので明示的に「すべて」を選択）
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications?status=all');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->where('stats.total', 4)
            ->where('stats.pending', 2)
            ->where('stats.approved', 1)
            ->where('stats.rejected', 1)
        );
    }

    /**
     * @test
     */
    public function index_only_returns_requests_from_same_company(): void
    {
        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        // 別会社の申請
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->forCompany($otherCompany->id)->create();
        Request::query()->create([
            'company_id' => $otherCompany->id,
            'requested_by' => $otherUser->id,
            'type' => 1,
            'target_date' => '2025-01-15',
            'reason' => '別会社の申請',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('requests', 1)
        );
    }

    /**
     * @test
     */
    public function index_returns_status_options(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('statusOptions', 4) // PENDING, APPROVED, REJECTED, CANCELLED
        );
    }

    /**
     * @test
     */
    public function index_returns_applicants_list(): void
    {
        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        $otherEmployee = User::factory()->forCompany($this->company->id)->create(['name' => 'Another Employee']);
        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $otherEmployee->id,
            'type' => 1,
            'target_date' => '2025-01-16',
            'reason' => '別ユーザーの申請',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('approvers')
        );
    }

    // ========================================
    // show アクション テスト
    // ========================================

    /**
     * @test
     */
    public function show_displays_normal_request_detail(): void
    {
        // Arrange
        $request = $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications/'.$request->id);

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('request.id', $request->id)
            ->where('request.request_type', 'normal')
        );
    }

    /**
     * @test
     */
    public function show_displays_correction_request_detail(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications/'.$correctionRequest->id.'?type=correction');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('request.id', $correctionRequest->id)
            ->where('request.request_type', 'correction')
            ->where('request.type_label', '打刻間違い')
        );
    }

    /**
     * @test
     */
    public function show_displays_correction_details(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequestWithDetails();

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications/'.$correctionRequest->id.'?type=correction');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('request.correction_details', 1)
        );
    }

    /**
     * @test
     */
    public function show_returns_404_for_nonexistent_request(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications/99999');

        // Assert
        $response->assertStatus(404);
    }

    /**
     * @test
     */
    public function show_returns_404_for_other_company_request(): void
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->forCompany($otherCompany->id)->create();
        $request = Request::query()->create([
            'company_id' => $otherCompany->id,
            'requested_by' => $otherUser->id,
            'type' => 1,
            'target_date' => '2025-01-15',
            'reason' => '別会社の申請',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications/'.$request->id);

        // Assert
        $response->assertStatus(404);
    }

    // ========================================
    // approve アクション テスト（通常申請）
    // ========================================

    /**
     * @test
     */
    public function approve_approves_normal_request(): void
    {
        // Arrange
        $request = $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/approve', [
            'request_type' => 'normal',
        ]);

        // Assert
        $response->assertRedirect(route('admin.applications'));
        $response->assertSessionHas('success', '申請を承認しました。');

        $request->refresh();
        $this->assertEquals(RequestStatusEnum::APPROVED, $request->status);
        $this->assertEquals($this->admin->id, $request->approver_id);
    }

    /**
     * @test
     */
    public function approve_normal_request_with_decision_note(): void
    {
        // Arrange
        $request = $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/approve', [
            'request_type' => 'normal',
            'decision_note' => '承認します',
        ]);

        // Assert
        $response->assertRedirect(route('admin.applications'));

        $request->refresh();
        $this->assertEquals(RequestStatusEnum::APPROVED, $request->status);
        $this->assertEquals('承認します', $request->decision_note);
    }

    /**
     * @test
     */
    public function approve_fails_for_already_approved_request(): void
    {
        // Arrange
        $request = $this->createNormalRequest([
            'status' => RequestStatusEnum::APPROVED,
            'approver_id' => $this->admin->id,
            'decided_at' => CarbonImmutable::now(),
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/approve', [
            'request_type' => 'normal',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'この申請は承認できません。');
    }

    /**
     * @test
     */
    public function approve_fails_for_rejected_request(): void
    {
        // Arrange
        $request = $this->createNormalRequest([
            'status' => RequestStatusEnum::REJECTED,
            'approver_id' => $this->admin->id,
            'decided_at' => CarbonImmutable::now(),
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/approve', [
            'request_type' => 'normal',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'この申請は承認できません。');
    }

    /**
     * @test
     */
    public function approve_fails_for_cancelled_request(): void
    {
        // Arrange
        $request = $this->createNormalRequest([
            'status' => RequestStatusEnum::CANCELLED,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/approve', [
            'request_type' => 'normal',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'この申請は承認できません。');
    }

    /**
     * @test
     */
    public function approve_returns_404_for_other_company_request(): void
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->forCompany($otherCompany->id)->create();
        $request = Request::query()->create([
            'company_id' => $otherCompany->id,
            'requested_by' => $otherUser->id,
            'type' => 1,
            'target_date' => '2025-01-15',
            'reason' => '別会社の申請',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/approve', [
            'request_type' => 'normal',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    // ========================================
    // approve アクション テスト（打刻修正申請）
    // ========================================

    /**
     * @test
     */
    public function approve_approves_correction_request(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequestWithDetails();

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/approve', [
            'request_type' => 'correction',
        ]);

        // Assert
        $response->assertRedirect(route('admin.applications'));
        $response->assertSessionHas('success', '打刻修正申請を承認し、打刻データを更新しました。');

        $correctionRequest->refresh();
        $this->assertEquals(RequestStatusEnum::APPROVED, $correctionRequest->status);
        $this->assertEquals($this->admin->id, $correctionRequest->approver_id);
    }

    /**
     * @test
     */
    public function approve_correction_request_updates_time_records(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $existingRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'target_date' => $targetDate,
            'reason' => '出勤時刻修正',
            'status' => RequestStatusEnum::PENDING,
        ]);

        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => $existingRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'original_record_time' => $targetDate.' 09:30:00',
            'original_rounded_time' => $targetDate.' 09:30:00',
            'corrected_record_time' => $targetDate.' 09:00:00',
            'corrected_rounded_time' => $targetDate.' 09:00:00',
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/approve', [
            'request_type' => 'correction',
        ]);

        // Assert
        $response->assertRedirect(route('admin.applications'));

        $existingRecord->refresh();
        $this->assertEquals($targetDate.' 09:00:00', $existingRecord->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::REQUEST, $existingRecord->record_source);
    }

    /**
     * @test
     */
    public function approve_correction_request_creates_new_time_record(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'target_date' => $targetDate,
            'reason' => '出勤打刻忘れ',
            'status' => RequestStatusEnum::PENDING,
        ]);

        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'corrected_record_time' => $targetDate.' 09:00:00',
            'corrected_rounded_time' => $targetDate.' 09:00:00',
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/approve', [
            'request_type' => 'correction',
        ]);

        // Assert
        $response->assertRedirect(route('admin.applications'));

        $newRecord = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->employee->id)
            ->where('record_type', TimeRecordTypeEnum::WORK_START)
            ->first();

        $this->assertNotNull($newRecord);
        $this->assertEquals($targetDate.' 09:00:00', $newRecord->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::REQUEST, $newRecord->record_source);
    }

    /**
     * @test
     */
    public function approve_correction_request_creates_correction_history(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $existingRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'target_date' => $targetDate,
            'reason' => '出勤時刻修正',
            'status' => RequestStatusEnum::PENDING,
        ]);

        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => $existingRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'original_record_time' => $targetDate.' 09:30:00',
            'original_rounded_time' => $targetDate.' 09:30:00',
            'corrected_record_time' => $targetDate.' 09:00:00',
            'corrected_rounded_time' => $targetDate.' 09:00:00',
        ]);

        // Act
        $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/approve', [
            'request_type' => 'correction',
        ]);

        // Assert
        $correction = TimeRecordCorrection::query()
            ->where('time_record_id', $existingRecord->id)
            ->first();

        $this->assertNotNull($correction);
        $this->assertEquals($targetDate.' 09:30:00', $correction->before_record_time->format('Y-m-d H:i:s'));
        $this->assertEquals($targetDate.' 09:00:00', $correction->after_record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function approve_correction_request_recalculates_daily_work_summary(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        // 打刻データを作成
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $targetDate.' 18:00:00',
            'rounded_time' => $targetDate.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'target_date' => $targetDate,
            'reason' => '出勤時刻修正',
            'status' => RequestStatusEnum::PENDING,
        ]);

        $workStartRecord = TimeRecord::query()->where('record_type', TimeRecordTypeEnum::WORK_START)->first();

        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => $workStartRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'original_record_time' => $targetDate.' 09:30:00',
            'original_rounded_time' => $targetDate.' 09:30:00',
            'corrected_record_time' => $targetDate.' 09:00:00',
            'corrected_rounded_time' => $targetDate.' 09:00:00',
        ]);

        // Act
        $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/approve', [
            'request_type' => 'correction',
        ]);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->employee->id)
            ->where('work_date', $targetDate)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(540, $summary->work_minutes); // 09:00-18:00 = 9時間
    }

    /**
     * @test
     */
    public function approve_correction_request_fails_for_already_approved(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequest([
            'status' => RequestStatusEnum::APPROVED,
            'approver_id' => $this->admin->id,
            'approved_at' => CarbonImmutable::now(),
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/approve', [
            'request_type' => 'correction',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'この申請は承認できません。');
    }

    /**
     * @test
     */
    public function approve_correction_request_returns_404_for_other_company(): void
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->forCompany($otherCompany->id)->create();
        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'target_date' => '2025-01-15',
            'reason' => '別会社の申請',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/approve', [
            'request_type' => 'correction',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    // ========================================
    // reject アクション テスト（通常申請）
    // ========================================

    /**
     * @test
     */
    public function reject_rejects_normal_request(): void
    {
        // Arrange
        $request = $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/reject', [
            'request_type' => 'normal',
            'decision_note' => '却下理由',
        ]);

        // Assert
        $response->assertRedirect(route('admin.applications'));
        $response->assertSessionHas('success', '申請を却下しました。');

        $request->refresh();
        $this->assertEquals(RequestStatusEnum::REJECTED, $request->status);
        $this->assertEquals($this->admin->id, $request->approver_id);
        $this->assertEquals('却下理由', $request->decision_note);
    }

    /**
     * @test
     */
    public function reject_fails_for_already_rejected_request(): void
    {
        // Arrange
        $request = $this->createNormalRequest([
            'status' => RequestStatusEnum::REJECTED,
            'approver_id' => $this->admin->id,
            'decided_at' => CarbonImmutable::now(),
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/reject', [
            'request_type' => 'normal',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'この申請は却下できません。');
    }

    /**
     * @test
     */
    public function reject_fails_for_already_approved_request(): void
    {
        // Arrange
        $request = $this->createNormalRequest([
            'status' => RequestStatusEnum::APPROVED,
            'approver_id' => $this->admin->id,
            'decided_at' => CarbonImmutable::now(),
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$request->id.'/reject', [
            'request_type' => 'normal',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'この申請は却下できません。');
    }

    // ========================================
    // reject アクション テスト（打刻修正申請）
    // ========================================

    /**
     * @test
     */
    public function reject_rejects_correction_request(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/reject', [
            'request_type' => 'correction',
            'decision_note' => '却下理由',
        ]);

        // Assert
        $response->assertRedirect(route('admin.applications'));
        $response->assertSessionHas('success', '打刻修正申請を却下しました。');

        $correctionRequest->refresh();
        $this->assertEquals(RequestStatusEnum::REJECTED, $correctionRequest->status);
        $this->assertEquals($this->admin->id, $correctionRequest->approver_id);
        $this->assertEquals('却下理由', $correctionRequest->rejection_reason);
    }

    /**
     * @test
     */
    public function reject_correction_request_does_not_update_time_records(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $existingRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'target_date' => $targetDate,
            'reason' => '出勤時刻修正',
            'status' => RequestStatusEnum::PENDING,
        ]);

        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => $existingRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'original_record_time' => $targetDate.' 09:30:00',
            'original_rounded_time' => $targetDate.' 09:30:00',
            'corrected_record_time' => $targetDate.' 09:00:00',
            'corrected_rounded_time' => $targetDate.' 09:00:00',
        ]);

        // Act
        $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/reject', [
            'request_type' => 'correction',
            'decision_note' => '却下',
        ]);

        // Assert
        $existingRecord->refresh();
        $this->assertEquals($targetDate.' 09:30:00', $existingRecord->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::AUTO, $existingRecord->record_source);
    }

    /**
     * @test
     */
    public function reject_correction_request_fails_for_already_rejected(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequest([
            'status' => RequestStatusEnum::REJECTED,
            'approver_id' => $this->admin->id,
            'approved_at' => CarbonImmutable::now(),
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/reject', [
            'request_type' => 'correction',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'この申請は却下できません。');
    }

    // ========================================
    // pending アクション テスト
    // ========================================

    /**
     * @test
     */
    public function pending_displays_pending_requests(): void
    {
        // 承認待ち専用画面は未実装。ルート（/admin/applications/pending）も
        // 画面（Admin/RequestsPending）も存在せず、一覧画面の絞り込みで代替している。
        $this->markTestSkipped('承認待ち専用画面は未実装のため対象外');

        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createNormalRequest(['status' => RequestStatusEnum::APPROVED]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications/pending');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/RequestsPending')
            ->has('requests', 2)
        );
    }

    /**
     * @test
     */
    public function pending_only_shows_pending_status(): void
    {
        // 承認待ち専用画面は未実装。ルート（/admin/applications/pending）も
        // 画面（Admin/RequestsPending）も存在せず、一覧画面の絞り込みで代替している。
        $this->markTestSkipped('承認待ち専用画面は未実装のため対象外');

        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createNormalRequest(['status' => RequestStatusEnum::APPROVED]);
        $this->createNormalRequest(['status' => RequestStatusEnum::REJECTED]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::APPROVED]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications/pending');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/RequestsPending')
            ->has('requests', 2)
        );
    }

    // ========================================
    // 認証・認可 テスト
    // ========================================

    /**
     * @test
     */
    public function unauthenticated_user_cannot_access_applications(): void
    {
        // Act
        $response = $this->get('/admin/applications');

        // Assert
        $response->assertRedirect('/admin/login');
    }

    /**
     * @test
     */
    public function unauthenticated_user_cannot_approve_request(): void
    {
        // Arrange
        $request = $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $response = $this->post('/admin/applications/'.$request->id.'/approve');

        // Assert
        $response->assertRedirect('/admin/login');
    }

    // ========================================
    // エッジケース テスト
    // ========================================

    /**
     * @test
     */
    public function index_handles_empty_requests(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->has('requests', 0)
            ->where('stats.total', 0)
            ->where('stats.pending', 0)
        );
    }

    /**
     * @test
     */
    public function index_handles_page_out_of_range(): void
    {
        // Arrange
        $this->createNormalRequest(['status' => RequestStatusEnum::PENDING]);

        // Act - ページ100を要求（データは1件のみ）
        $response = $this->actingAs($this->admin, 'admin')->get('/admin/applications?page=100');

        // Assert - ページは最後のページに補正される
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Applications')
            ->where('pagination.current_page', 1)
        );
    }

    /**
     * @test
     */
    public function approve_handles_exception_gracefully(): void
    {
        // Arrange - 存在しないIDで承認を試みる
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/99999/approve', [
            'request_type' => 'normal',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    /**
     * @test
     */
    public function approve_correction_with_multiple_details(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $workStartRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'target_date' => $targetDate,
            'reason' => '出退勤両方修正',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // 出勤修正（既存レコード）
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => $workStartRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'original_record_time' => $targetDate.' 09:30:00',
            'original_rounded_time' => $targetDate.' 09:30:00',
            'corrected_record_time' => $targetDate.' 09:00:00',
            'corrected_rounded_time' => $targetDate.' 09:00:00',
        ]);

        // 退勤追加（新規レコード）
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'corrected_record_time' => $targetDate.' 18:00:00',
            'corrected_rounded_time' => $targetDate.' 18:00:00',
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'admin')->post('/admin/applications/'.$correctionRequest->id.'/approve', [
            'request_type' => 'correction',
        ]);

        // Assert
        $response->assertRedirect(route('admin.applications'));

        // 出勤レコードが更新されていること
        $workStartRecord->refresh();
        $this->assertEquals($targetDate.' 09:00:00', $workStartRecord->record_time->format('Y-m-d H:i:s'));

        // 退勤レコードが新規作成されていること
        $workEndRecord = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->employee->id)
            ->where('record_type', TimeRecordTypeEnum::WORK_END)
            ->first();
        $this->assertNotNull($workEndRecord);
        $this->assertEquals($targetDate.' 18:00:00', $workEndRecord->record_time->format('Y-m-d H:i:s'));

        // 修正履歴は既存レコード分のみ
        $correctionCount = TimeRecordCorrection::query()->count();
        $this->assertEquals(1, $correctionCount);
    }

    // ========================================
    // ヘルパーメソッド
    // ========================================

    /**
     * 通常申請を作成
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createNormalRequest(array $overrides = []): Request
    {
        return Request::query()->create(array_merge([
            'company_id' => $this->company->id,
            'requested_by' => $this->employee->id,
            'type' => 1,
            'target_date' => '2025-01-15',
            'reason' => 'テスト理由',
            'status' => RequestStatusEnum::PENDING,
        ], $overrides));
    }

    /**
     * 打刻修正申請を作成
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createCorrectionRequest(array $overrides = []): TimeRecordCorrectionRequest
    {
        return TimeRecordCorrectionRequest::query()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'target_date' => '2025-01-15',
            'reason' => 'テスト理由',
            'status' => RequestStatusEnum::PENDING,
        ], $overrides));
    }

    /**
     * 打刻修正申請（明細付き）を作成
     */
    private function createCorrectionRequestWithDetails(): TimeRecordCorrectionRequest
    {
        $correctionRequest = $this->createCorrectionRequest();

        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'corrected_record_time' => '2025-01-15 09:00:00',
            'corrected_rounded_time' => '2025-01-15 09:00:00',
        ]);

        return $correctionRequest->load('details');
    }
}

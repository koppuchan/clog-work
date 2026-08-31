<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\TimeRecordCorrection;
use App\Models\TimeRecordCorrectionRequest;
use App\Models\TimeRecordCorrectionRequestDetail;
use App\Models\User;
use App\Services\TimeRecordCorrectionRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TimeRecordCorrectionRequestServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TimeRecordCorrectionRequestService $service;

    private Company $company;

    private User $user;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TimeRecordCorrectionRequestService::class);

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
    // findById テスト
    // ========================================

    /**
     * @test
     */
    public function find_by_id_returns_correction_request_when_exists(): void
    {
        // Arrange
        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'target_date' => '2025-01-15',
            'reason' => 'テスト理由',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $result = $this->service->findById($correctionRequest->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($correctionRequest->id, $result->id);
        $this->assertEquals('テスト理由', $result->reason);
    }

    /**
     * @test
     */
    public function find_by_id_returns_null_when_not_exists(): void
    {
        // Act
        $result = $this->service->findById(99999);

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // getCorrectionRequestsByCompanyId テスト
    // ========================================

    /**
     * @test
     */
    public function get_correction_requests_by_company_id_returns_all_requests_for_company(): void
    {
        // Arrange
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::APPROVED]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::REJECTED]);

        // 別会社のリクエスト（取得されないはず）
        $otherCompany = Company::factory()->create();
        TimeRecordCorrectionRequest::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $this->user->id,
            'target_date' => '2025-01-15',
            'reason' => '別会社',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $result = $this->service->getCorrectionRequestsByCompanyId($this->company->id);

        // Assert
        $this->assertCount(3, $result);
    }

    /**
     * @test
     */
    public function get_correction_requests_by_company_id_filters_by_status_when_provided(): void
    {
        // Arrange
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::APPROVED]);

        // Act
        $result = $this->service->getCorrectionRequestsByCompanyId(
            $this->company->id,
            RequestStatusEnum::PENDING->value
        );

        // Assert
        $this->assertCount(2, $result);
        $result->each(fn ($r) => $this->assertEquals(RequestStatusEnum::PENDING, $r->status));
    }

    /**
     * @test
     */
    public function get_correction_requests_by_company_id_returns_empty_when_no_requests(): void
    {
        // Act
        $result = $this->service->getCorrectionRequestsByCompanyId($this->company->id);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // getCorrectionRequestsByUserId テスト
    // ========================================

    /**
     * @test
     */
    public function get_correction_requests_by_user_id_returns_requests_for_specific_user(): void
    {
        // Arrange
        $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);
        $this->createCorrectionRequest(['status' => RequestStatusEnum::APPROVED]);

        // 別ユーザーのリクエスト
        $otherUser = User::factory()->forCompany($this->company->id)->create();
        TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $otherUser->id,
            'target_date' => '2025-01-15',
            'reason' => '別ユーザー',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // Act
        $result = $this->service->getCorrectionRequestsByUserId(
            $this->company->id,
            $this->user->id
        );

        // Assert
        $this->assertCount(2, $result);
        $result->each(fn ($r) => $this->assertEquals($this->user->id, $r->user_id));
    }

    // ========================================
    // createCorrectionRequest テスト
    // ========================================

    /**
     * @test
     */
    public function create_correction_request_creates_new_request(): void
    {
        // Arrange
        $data = [
            'target_date' => '2025-01-15',
            'reason' => '出勤打刻を忘れました',
        ];

        // Act
        $result = $this->service->createCorrectionRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        // Assert
        $this->assertInstanceOf(TimeRecordCorrectionRequest::class, $result);
        $this->assertEquals($this->company->id, $result->company_id);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals('2025-01-15', $result->target_date->format('Y-m-d'));
        $this->assertEquals('出勤打刻を忘れました', $result->reason);
        $this->assertEquals(RequestStatusEnum::PENDING, $result->status);

        $this->assertDatabaseHas('time_record_correction_requests', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'target_date' => '2025-01-15',
        ]);
    }

    // ========================================
    // approveCorrectionRequest テスト（既存レコード更新）
    // ========================================

    /**
     * @test
     */
    public function approve_correction_request_updates_existing_time_record(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        // 既存の打刻レコードを作成
        $existingRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 打刻修正申請を作成
        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            $existingRecord,
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $result = $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $this->assertEquals(RequestStatusEnum::APPROVED, $result->status);
        $this->assertEquals($this->approver->id, $result->approver_id);
        $this->assertNotNull($result->approved_at);

        // 打刻レコードが更新されていること
        $existingRecord->refresh();
        $this->assertEquals($targetDate.' 09:00:00', $existingRecord->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::REQUEST, $existingRecord->record_source);
    }

    /**
     * @test
     */
    public function approve_correction_request_creates_correction_history_for_existing_record(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $existingRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            $existingRecord,
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $correction = TimeRecordCorrection::query()
            ->where('time_record_id', $existingRecord->id)
            ->first();

        $this->assertNotNull($correction);
        $this->assertEquals($targetDate.' 09:30:00', $correction->before_record_time->format('Y-m-d H:i:s'));
        $this->assertEquals($targetDate.' 09:00:00', $correction->after_record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::AUTO, $correction->before_record_source);
        $this->assertEquals(RecordSourceEnum::REQUEST, $correction->after_record_source);
        $this->assertEquals($this->approver->id, $correction->corrected_by);
    }

    // ========================================
    // approveCorrectionRequest テスト（新規レコード作成）
    // ========================================

    /**
     * @test
     */
    public function approve_correction_request_creates_new_time_record_when_not_exists(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        // 打刻修正申請を作成（既存レコードなし）
        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            null, // 既存レコードなし
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $result = $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $this->assertEquals(RequestStatusEnum::APPROVED, $result->status);

        // 新規打刻レコードが作成されていること
        $newRecord = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::WORK_START)
            ->first();

        $this->assertNotNull($newRecord);
        $this->assertEquals($targetDate.' 09:00:00', $newRecord->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::REQUEST, $newRecord->record_source);
    }

    /**
     * @test
     */
    public function approve_correction_request_does_not_create_correction_history_for_new_record(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            null,
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        // 新規作成の場合は修正履歴が作成されないこと
        $correctionCount = TimeRecordCorrection::query()->count();
        $this->assertEquals(0, $correctionCount);
    }

    /**
     * @test
     */
    public function approve_correction_request_updates_detail_with_new_time_record_id(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            null,
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        $detail = $correctionRequest->details->first();
        $this->assertNull($detail->time_record_id);

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $detail->refresh();
        $this->assertNotNull($detail->time_record_id);
    }

    // ========================================
    // approveCorrectionRequest テスト（複数明細）
    // ========================================

    /**
     * @test
     */
    public function approve_correction_request_handles_multiple_details(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        // 既存の打刻レコード（出勤）
        $workStartRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 打刻修正申請を作成
        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'target_date' => $targetDate,
            'reason' => '出退勤両方修正',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // 出勤時刻の修正明細（既存レコードあり）
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => $workStartRecord->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'original_record_time' => $targetDate.' 09:30:00',
            'original_rounded_time' => $targetDate.' 09:30:00',
            'corrected_record_time' => $targetDate.' 09:00:00',
            'corrected_rounded_time' => $targetDate.' 09:00:00',
        ]);

        // 退勤時刻の修正明細（新規作成）
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'original_record_time' => null,
            'original_rounded_time' => null,
            'corrected_record_time' => $targetDate.' 18:00:00',
            'corrected_rounded_time' => $targetDate.' 18:00:00',
        ]);

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        // 出勤レコードが更新されていること
        $workStartRecord->refresh();
        $this->assertEquals($targetDate.' 09:00:00', $workStartRecord->record_time->format('Y-m-d H:i:s'));

        // 退勤レコードが新規作成されていること
        $workEndRecord = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::WORK_END)
            ->first();
        $this->assertNotNull($workEndRecord);
        $this->assertEquals($targetDate.' 18:00:00', $workEndRecord->record_time->format('Y-m-d H:i:s'));

        // 修正履歴は既存レコード分のみ
        $correctionCount = TimeRecordCorrection::query()->count();
        $this->assertEquals(1, $correctionCount);
    }

    // ========================================
    // approveCorrectionRequest テスト（勤務実績再計算）
    // ========================================

    /**
     * @test
     */
    public function approve_correction_request_recalculates_daily_work_summary(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        // 既存の打刻レコード
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $targetDate.' 18:00:00',
            'rounded_time' => $targetDate.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 打刻修正申請を作成（出勤時刻を09:00に修正）
        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            TimeRecord::query()->where('record_type', TimeRecordTypeEnum::WORK_START)->first(),
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $targetDate)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(540, $summary->work_minutes); // 09:00-18:00 = 9時間 = 540分
    }

    /**
     * @test
     */
    public function approve_correction_request_updates_existing_daily_work_summary(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        // 既存の勤務実績（古いデータ）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $targetDate,
            'work_start' => $targetDate.' 09:30:00',
            'work_end' => $targetDate.' 18:00:00',
            'work_minutes' => 510, // 8時間30分
            'break_minutes' => 0,
            'net_work_minutes' => 510,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 打刻レコードを作成
        $workStartRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $targetDate.' 18:00:00',
            'rounded_time' => $targetDate.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 打刻修正申請を作成
        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            $workStartRecord,
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $targetDate)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(540, $summary->work_minutes); // 更新後: 9時間
        $this->assertEquals($targetDate.' 09:00:00', $summary->work_start->format('Y-m-d H:i:s'));
    }

    // ========================================
    // rejectCorrectionRequest テスト
    // ========================================

    /**
     * @test
     */
    public function reject_correction_request_updates_status_to_rejected(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $result = $this->service->rejectCorrectionRequest(
            $correctionRequest->id,
            $this->approver->id,
            '理由が不明確です'
        );

        // Assert
        $this->assertEquals(RequestStatusEnum::REJECTED, $result->status);
        $this->assertEquals($this->approver->id, $result->approver_id);
        $this->assertNotNull($result->approved_at);
        $this->assertEquals('理由が不明確です', $result->rejection_reason);
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
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            $existingRecord,
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $this->service->rejectCorrectionRequest(
            $correctionRequest->id,
            $this->approver->id,
            '却下理由'
        );

        // Assert
        $existingRecord->refresh();
        $this->assertEquals($targetDate.' 09:30:00', $existingRecord->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::AUTO, $existingRecord->record_source);
    }

    // ========================================
    // cancelCorrectionRequest テスト
    // ========================================

    /**
     * @test
     */
    public function cancel_correction_request_updates_status_to_cancelled(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);

        // Act
        $result = $this->service->cancelCorrectionRequest($correctionRequest->id);

        // Assert
        $this->assertEquals(RequestStatusEnum::CANCELLED, $result->status);
    }

    // ========================================
    // createClockErrorRequest テスト
    // ========================================

    /**
     * @test
     */
    public function create_clock_error_request_creates_request_with_work_start_detail(): void
    {
        // Arrange
        $data = [
            'target_date' => '2025-01-15',
            'reason' => '出勤打刻を忘れました',
            'start_time' => '09:00',
        ];

        // Act
        $result = $this->service->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        // Assert
        $this->assertInstanceOf(TimeRecordCorrectionRequest::class, $result);
        $this->assertEquals(RequestStatusEnum::PENDING, $result->status);
        $this->assertCount(1, $result->details);

        $detail = $result->details->first();
        $this->assertEquals(TimeRecordTypeEnum::WORK_START, $detail->record_type);
        $this->assertEquals('2025-01-15 09:00:00', $detail->corrected_record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function create_clock_error_request_creates_request_with_work_end_detail(): void
    {
        // Arrange
        $data = [
            'target_date' => '2025-01-15',
            'reason' => '退勤打刻を忘れました',
            'end_time' => '18:00',
        ];

        // Act
        $result = $this->service->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        // Assert
        $this->assertCount(1, $result->details);

        $detail = $result->details->first();
        $this->assertEquals(TimeRecordTypeEnum::WORK_END, $detail->record_type);
        $this->assertEquals('2025-01-15 18:00:00', $detail->corrected_record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function create_clock_error_request_creates_cross_day_work_end_when_end_time_before_start_time(): void
    {
        // Arrange
        $data = [
            'target_date' => '2025-01-15',
            'reason' => '夜勤の退勤打刻忘れ',
            'start_time' => '22:00',
            'end_time' => '06:00', // 翌日
        ];

        // Act
        $result = $this->service->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        // Assert
        $this->assertCount(2, $result->details);

        $workEndDetail = $result->details->where('record_type', TimeRecordTypeEnum::WORK_END_NEXT_DAY)->first();
        $this->assertNotNull($workEndDetail);
        $this->assertEquals('2025-01-16 06:00:00', $workEndDetail->corrected_record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function create_clock_error_request_creates_request_with_break_end_detail(): void
    {
        // Arrange
        $data = [
            'target_date' => '2025-01-15',
            'reason' => '休憩終了打刻を忘れました',
            'break_end_time' => '13:00',
        ];

        // Act
        $result = $this->service->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        // Assert
        $this->assertCount(1, $result->details);

        $detail = $result->details->first();
        $this->assertEquals(TimeRecordTypeEnum::BREAK_END, $detail->record_type);
        $this->assertEquals('2025-01-15 13:00:00', $detail->corrected_record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function create_clock_error_request_links_to_existing_time_record(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        // 既存の打刻レコード
        $existingRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $data = [
            'target_date' => $targetDate,
            'reason' => '出勤時刻を修正したい',
            'start_time' => '09:00',
        ];

        // Act
        $result = $this->service->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        // Assert
        $detail = $result->details->first();
        $this->assertEquals($existingRecord->id, $detail->time_record_id);
        $this->assertEquals($targetDate.' 09:30:00', $detail->original_record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function create_clock_error_request_creates_all_details_at_once(): void
    {
        // Arrange
        $data = [
            'target_date' => '2025-01-15',
            'reason' => '全ての打刻を忘れました',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_end_time' => '13:00',
        ];

        // Act
        $result = $this->service->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        // Assert
        $this->assertCount(3, $result->details);

        $recordTypes = $result->details->pluck('record_type')->toArray();
        $this->assertContains(TimeRecordTypeEnum::WORK_START, $recordTypes);
        $this->assertContains(TimeRecordTypeEnum::WORK_END, $recordTypes);
        $this->assertContains(TimeRecordTypeEnum::BREAK_END, $recordTypes);
    }

    // ========================================
    // エッジケース・異常系テスト
    // ========================================

    /**
     * @test
     */
    public function approve_correction_request_handles_already_approved_request(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequest([
            'status' => RequestStatusEnum::APPROVED,
            'approver_id' => $this->approver->id,
            'approved_at' => CarbonImmutable::now(),
        ]);

        // 明細を追加
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'corrected_record_time' => '2025-01-15 09:00:00',
            'corrected_rounded_time' => '2025-01-15 09:00:00',
        ]);

        // Act - 既に承認済みでも例外は発生しない（べき等性）
        $result = $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $this->assertEquals(RequestStatusEnum::APPROVED, $result->status);
    }

    /**
     * @test
     */
    public function approve_correction_request_handles_empty_details(): void
    {
        // Arrange
        $correctionRequest = $this->createCorrectionRequest(['status' => RequestStatusEnum::PENDING]);
        // 明細なし

        // Act
        $result = $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $this->assertEquals(RequestStatusEnum::APPROVED, $result->status);
    }

    /**
     * @test
     */
    public function approve_correction_request_preserves_original_record_data_in_history(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        $existingRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:45:23',
            'rounded_time' => $targetDate.' 09:45:00',
            'record_source' => RecordSourceEnum::MANUAL,
            'note' => '手動入力',
        ]);

        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            $existingRecord,
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $correction = TimeRecordCorrection::query()
            ->where('time_record_id', $existingRecord->id)
            ->first();

        $this->assertEquals($targetDate.' 09:45:23', $correction->before_record_time->format('Y-m-d H:i:s'));
        $this->assertEquals($targetDate.' 09:45:00', $correction->before_rounded_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::MANUAL, $correction->before_record_source);
    }

    // ========================================
    // 打刻修正承認後の休憩フォールバック制御テスト
    // ========================================

    /**
     * @test
     *
     * WORK_STARTのみ修正する申請を承認した後、シフトパターンの休憩がフォールバックで計上されないこと
     */
    public function approve_correction_request_does_not_insert_phantom_break_from_shift_pattern(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        // シフトパターン（休憩60分設定）
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
            'shift_date' => $targetDate,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 既存の打刻レコード（AUTO、休憩なし）
        $workStartRecord = TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:30:00',
            'rounded_time' => $targetDate.' 09:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $targetDate.' 18:00:00',
            'rounded_time' => $targetDate.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // WORK_STARTのみ修正する申請を作成
        $correctionRequest = $this->createCorrectionRequestWithDetail(
            $targetDate,
            $workStartRecord,
            TimeRecordTypeEnum::WORK_START,
            '09:00:00'
        );

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $targetDate)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->break_minutes, '修正後に休憩打刻がなければ休憩0');
        $this->assertEquals(540, $summary->work_minutes, '09:00-18:00 = 540分');
        $this->assertEquals(540, $summary->net_work_minutes, '休憩0なので実働=勤務時間');
    }

    /**
     * @test
     *
     * 休憩開始・終了を含む修正申請を承認した場合、その休憩時間が正しく反映されること
     */
    public function approve_correction_request_with_break_records_calculates_correct_break_minutes(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

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
            'shift_date' => $targetDate,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        // 既存打刻なし → 全て新規作成される修正申請
        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'target_date' => $targetDate,
            'reason' => '全打刻忘れ',
            'status' => RequestStatusEnum::PENDING,
        ]);

        // WORK_START
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'original_record_time' => null,
            'original_rounded_time' => null,
            'corrected_record_time' => $targetDate.' 09:00:00',
            'corrected_rounded_time' => $targetDate.' 09:00:00',
        ]);

        // BREAK_START
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'original_record_time' => null,
            'original_rounded_time' => null,
            'corrected_record_time' => $targetDate.' 12:00:00',
            'corrected_rounded_time' => $targetDate.' 12:00:00',
        ]);

        // BREAK_END
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'original_record_time' => null,
            'original_rounded_time' => null,
            'corrected_record_time' => $targetDate.' 12:45:00',
            'corrected_rounded_time' => $targetDate.' 12:45:00',
        ]);

        // WORK_END
        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => null,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'original_record_time' => null,
            'original_rounded_time' => null,
            'corrected_record_time' => $targetDate.' 18:00:00',
            'corrected_rounded_time' => $targetDate.' 18:00:00',
        ]);

        $correctionRequest->load('details');

        // Act
        $this->service->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // Assert
        $summary = DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $targetDate)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(45, $summary->break_minutes, '12:00-12:45 = 45分の休憩');
        $this->assertEquals(540, $summary->work_minutes, '09:00-18:00 = 540分');
        $this->assertEquals(495, $summary->net_work_minutes, '540 - 45 = 495分');
    }

    // ========================================
    // ヘルパーメソッド
    // ========================================

    /**
     * 打刻修正申請を作成
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createCorrectionRequest(array $overrides = []): TimeRecordCorrectionRequest
    {
        return TimeRecordCorrectionRequest::query()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'target_date' => '2025-01-15',
            'reason' => 'テスト理由',
            'status' => RequestStatusEnum::PENDING,
        ], $overrides));
    }

    /**
     * 打刻修正申請と明細を作成
     */
    private function createCorrectionRequestWithDetail(
        string $targetDate,
        ?TimeRecord $existingRecord,
        TimeRecordTypeEnum $recordType,
        string $correctedTime
    ): TimeRecordCorrectionRequest {
        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'target_date' => $targetDate,
            'reason' => 'テスト修正',
            'status' => RequestStatusEnum::PENDING,
        ]);

        TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => $existingRecord?->id,
            'record_type' => $recordType,
            'original_record_time' => $existingRecord?->record_time,
            'original_rounded_time' => $existingRecord?->rounded_time,
            'corrected_record_time' => $targetDate.' '.$correctedTime,
            'corrected_rounded_time' => $targetDate.' '.$correctedTime,
        ]);

        return $correctionRequest->load('details');
    }
}

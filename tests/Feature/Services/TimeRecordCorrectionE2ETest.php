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
use App\Models\User;
use App\Services\DailyWorkSummaryService;
use App\Services\TimeRecordCorrectionRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 打刻修正の総合テスト（E2E）
 *
 * 申請→承認→DailyWorkSummary再計算、取消・差し戻し、管理者直接修正、
 * 日跨ぎ夜勤、複数休憩ペア、record_source遷移など、打刻修正に関わる
 * 一連のフローを横断的にテストする。
 */
class TimeRecordCorrectionE2ETest extends TestCase
{
    use DatabaseTransactions;

    private TimeRecordCorrectionRequestService $correctionService;

    private DailyWorkSummaryService $summaryService;

    private Company $company;

    private User $user;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->correctionService = app(TimeRecordCorrectionRequestService::class);
        $this->summaryService = app(DailyWorkSummaryService::class);

        $this->company = Company::factory()->create([
            'name' => 'E2E Test Company',
            'is_closed_on_holidays' => false,
        ]);

        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create([
                'name' => 'E2E Test User',
                'is_retired' => false,
            ]);

        $this->approver = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create([
                'name' => 'E2E Admin User',
                'is_retired' => false,
            ]);
    }

    // ========================================
    // 申請→承認→再計算 E2Eフロー
    // ========================================

    /**
     * @test
     *
     * 出勤・退勤・休憩開始・休憩終了を全て新規作成する申請のE2E
     */
    public function e2e_approve_all_new_records_creates_correct_summary(): void
    {
        $targetDate = '2025-01-15';

        $data = [
            'target_date' => $targetDate,
            'reason' => '全打刻を忘れました',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
        ];

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        $this->assertCount(4, $correctionRequest->details);

        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // 打刻レコードが4件作成され、全てrecord_source = REQUEST
        $timeRecords = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->get();

        $this->assertEquals(4, $timeRecords->count());
        $timeRecords->each(function ($record) {
            $this->assertEquals(RecordSourceEnum::REQUEST, $record->record_source, "record_type={$record->record_type->value}がREQUESTであるべき");
        });

        // DailyWorkSummaryが正しく計算されている
        $summary = $this->getSummary($targetDate);

        $this->assertNotNull($summary);
        $this->assertEquals(540, $summary->work_minutes, '09:00-18:00 = 540分');
        $this->assertEquals(60, $summary->break_minutes, '12:00-13:00 = 60分');
        $this->assertEquals(480, $summary->net_work_minutes, '540 - 60 = 480分');
    }

    /**
     * @test
     *
     * 既存打刻を修正する申請のE2E: record_sourceがAUTO→REQUESTに遷移
     */
    public function e2e_approve_existing_records_transitions_record_source_to_request(): void
    {
        $targetDate = '2025-01-15';

        $this->createWorkRecords($targetDate, '09:30', '17:30');

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '時刻修正', 'start_time' => '09:00', 'end_time' => '18:00']
        );

        // 既存レコードにリンクされている
        $correctionRequest->details->each(function ($detail) {
            $this->assertNotNull($detail->time_record_id);
        });

        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // record_sourceがREQUESTに遷移
        $workStart = $this->getTimeRecord(TimeRecordTypeEnum::WORK_START);
        $this->assertEquals(RecordSourceEnum::REQUEST, $workStart->record_source);
        $this->assertEquals($targetDate.' 09:00:00', $workStart->record_time->format('Y-m-d H:i:s'));

        // 修正履歴が記録されている
        $correction = TimeRecordCorrection::query()
            ->where('time_record_id', $workStart->id)
            ->first();
        $this->assertNotNull($correction);
        $this->assertEquals($targetDate.' 09:30:00', $correction->before_record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::AUTO, $correction->before_record_source);
        $this->assertEquals(RecordSourceEnum::REQUEST, $correction->after_record_source);
    }

    /**
     * @test
     *
     * 日跨ぎ夜勤の修正申請E2E
     */
    public function e2e_approve_cross_day_shift_correction(): void
    {
        $targetDate = '2025-01-15';

        $data = [
            'target_date' => $targetDate,
            'reason' => '夜勤打刻忘れ',
            'start_time' => '22:00',
            'end_time' => '06:00',
        ];

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            $data
        );

        $endDetail = $correctionRequest->details
            ->where('record_type', TimeRecordTypeEnum::WORK_END_NEXT_DAY)
            ->first();
        $this->assertNotNull($endDetail, 'WORK_END_NEXT_DAYが作成される');
        $this->assertEquals('2025-01-16 06:00:00', $endDetail->corrected_record_time->format('Y-m-d H:i:s'));

        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        $summary = $this->getSummary($targetDate);
        $this->assertNotNull($summary);
        $this->assertTrue($summary->is_cross_day);
        $this->assertEquals(480, $summary->work_minutes, '22:00-06:00 = 480分');
    }

    // ========================================
    // 取消・差し戻しフロー
    // ========================================

    /**
     * @test
     *
     * 承認済み申請の取消E2E: 打刻データが元に戻りDailyWorkSummaryが再計算される
     */
    public function e2e_cancel_approved_request_restores_original_data(): void
    {
        $targetDate = '2025-01-15';
        $this->createWorkRecords($targetDate, '09:30', '18:00');

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '出勤修正', 'start_time' => '09:00']
        );
        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        // 取消
        $this->correctionService->cancelCorrectionRequest($correctionRequest->id);

        $workStart = $this->getTimeRecord(TimeRecordTypeEnum::WORK_START);
        $this->assertEquals($targetDate.' 09:30:00', $workStart->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::AUTO, $workStart->record_source, '取消後はAUTOに戻る');

        // 修正履歴が削除されている
        $corrections = TimeRecordCorrection::query()
            ->where('time_record_id', $workStart->id)
            ->count();
        $this->assertEquals(0, $corrections);

        $summary = $this->getSummary($targetDate);
        $this->assertNotNull($summary);
        $this->assertEquals(510, $summary->work_minutes, '09:30-18:00 = 510分に戻る');
    }

    /**
     * @test
     *
     * 承認済み申請の差し戻しE2E
     */
    public function e2e_revert_approved_request_restores_data_and_sets_pending(): void
    {
        $targetDate = '2025-01-15';
        $this->createWorkRecords($targetDate, '09:30', '18:00');

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '出勤修正', 'start_time' => '09:00']
        );
        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        $result = $this->correctionService->revertCorrectionRequest($correctionRequest->id);

        $this->assertEquals(RequestStatusEnum::PENDING, $result->status);
        $this->assertNull($result->approver_id);
        $this->assertNull($result->approved_at);

        $workStart = $this->getTimeRecord(TimeRecordTypeEnum::WORK_START);
        $this->assertEquals($targetDate.' 09:30:00', $workStart->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::AUTO, $workStart->record_source);
    }

    /**
     * @test
     *
     * 差し戻し→再承認E2E
     */
    public function e2e_revert_then_reapprove_applies_correction_again(): void
    {
        $targetDate = '2025-01-15';
        $this->createWorkRecords($targetDate, '09:30', '18:00');

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '出勤修正', 'start_time' => '09:00']
        );

        // 承認 → 差し戻し → 再承認
        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);
        $this->correctionService->revertCorrectionRequest($correctionRequest->id);
        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        $workStart = $this->getTimeRecord(TimeRecordTypeEnum::WORK_START);
        $this->assertEquals($targetDate.' 09:00:00', $workStart->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordSourceEnum::REQUEST, $workStart->record_source);

        $summary = $this->getSummary($targetDate);
        $this->assertEquals(540, $summary->work_minutes, '09:00-18:00 = 540分');
    }

    /**
     * @test
     *
     * 却下後の再申請E2E
     */
    public function e2e_reject_then_new_request_and_approve(): void
    {
        $targetDate = '2025-01-15';
        $this->createWorkRecords($targetDate, '09:30', '18:00');

        // 1回目: 却下
        $request1 = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '間違えた', 'start_time' => '08:00']
        );
        $this->correctionService->rejectCorrectionRequest($request1->id, $this->approver->id, '不適切');

        // 打刻は変更されていない
        $workStart = $this->getTimeRecord(TimeRecordTypeEnum::WORK_START);
        $this->assertEquals($targetDate.' 09:30:00', $workStart->record_time->format('Y-m-d H:i:s'));

        // 2回目: 承認
        $request2 = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '正しくは09:00', 'start_time' => '09:00']
        );
        $this->correctionService->approveCorrectionRequest($request2->id, $this->approver->id);

        $workStart->refresh();
        $this->assertEquals($targetDate.' 09:00:00', $workStart->record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     *
     * 取消E2E(新規作成レコード): 承認で作成されたレコードが取消で削除される
     */
    public function e2e_cancel_removes_newly_created_records(): void
    {
        $targetDate = '2025-01-15';

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '打刻忘れ', 'start_time' => '09:00']
        );

        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);
        $this->assertEquals(1, $this->countTimeRecords(TimeRecordTypeEnum::WORK_START));

        $this->correctionService->cancelCorrectionRequest($correctionRequest->id);
        $this->assertEquals(0, $this->countTimeRecords(TimeRecordTypeEnum::WORK_START), '新規レコードが削除される');

        $correctionRequest->refresh();
        $correctionRequest->load('details');
        $this->assertNull($correctionRequest->details->first()->time_record_id, 'time_record_idがnullに戻る');
    }

    // ========================================
    // 連続承認・複合シナリオ
    // ========================================

    /**
     * @test
     *
     * 同じ日に2つの申請を順に承認（出勤修正 + 休憩追加）
     */
    public function e2e_sequential_approvals_on_same_date(): void
    {
        $targetDate = '2025-01-15';
        $this->createWorkRecords($targetDate, '09:30', '18:00');

        // 1回目: 出勤修正
        $request1 = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '出勤修正', 'start_time' => '09:00']
        );
        $this->correctionService->approveCorrectionRequest($request1->id, $this->approver->id);

        // 2回目: 休憩追加
        $request2 = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            [
                'target_date' => $targetDate,
                'reason' => '休憩追加',
                'break_start_time' => '12:00',
                'break_end_time' => '13:00',
            ]
        );
        $this->correctionService->approveCorrectionRequest($request2->id, $this->approver->id);

        $summary = $this->getSummary($targetDate);
        $this->assertNotNull($summary);
        $this->assertEquals($targetDate.' 09:00:00', $summary->work_start->format('Y-m-d H:i:s'), '1回目の修正が保持される');
        $this->assertEquals(540, $summary->work_minutes);
        $this->assertEquals(60, $summary->break_minutes);
        $this->assertEquals(480, $summary->net_work_minutes);
    }

    /**
     * @test
     *
     * 既存MANUAL打刻を申請で修正: MANUAL→REQUESTに遷移
     */
    public function e2e_approve_correction_on_manual_record_transitions_to_request(): void
    {
        $targetDate = '2025-01-15';
        $this->createWorkRecords($targetDate, '09:30', '18:00', RecordSourceEnum::MANUAL);

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '管理者入力が間違い', 'start_time' => '09:00']
        );
        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        $workStart = $this->getTimeRecord(TimeRecordTypeEnum::WORK_START);
        $this->assertEquals(RecordSourceEnum::REQUEST, $workStart->record_source);

        $correction = TimeRecordCorrection::query()
            ->where('time_record_id', $workStart->id)
            ->first();
        $this->assertEquals(RecordSourceEnum::MANUAL, $correction->before_record_source);
        $this->assertEquals(RecordSourceEnum::REQUEST, $correction->after_record_source);
    }

    // ========================================
    // 管理者直接修正のE2E
    // ========================================

    /**
     * @test
     *
     * 管理者直接修正: 既存休憩2ペア→0ペアに削減
     */
    public function e2e_admin_update_removes_all_break_pairs(): void
    {
        $workDate = '2025-01-15';

        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => $workDate.' 09:00:00',
            'work_end' => $workDate.' 18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 90,
            'net_work_minutes' => 450,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->createWorkRecords($workDate, '09:00', '18:00');

        // 休憩2ペア作成
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $workDate.' 12:00:00',
            'rounded_time' => $workDate.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $workDate.' 13:00:00',
            'rounded_time' => $workDate.' 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $workDate.' 15:00:00',
            'rounded_time' => $workDate.' 15:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $workDate.' 15:30:00',
            'rounded_time' => $workDate.' 15:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 休憩なしで更新
        $result = $this->summaryService->updateWorkTimes($summary->id, '09:00', '18:00', [], null);

        $this->assertEquals(0, $result->break_minutes);
        $this->assertEquals(540, $result->net_work_minutes);

        // BREAKレコードが全て削除されている
        $breakCount = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->whereIn('record_type', [TimeRecordTypeEnum::BREAK_START, TimeRecordTypeEnum::BREAK_END])
            ->count();
        $this->assertEquals(0, $breakCount);
    }

    /**
     * @test
     *
     * 管理者直接修正: 休憩0ペア→2ペアに追加
     */
    public function e2e_admin_update_adds_multiple_break_pairs(): void
    {
        $workDate = '2025-01-15';

        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => $workDate.' 09:00:00',
            'work_end' => $workDate.' 18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 0,
            'net_work_minutes' => 540,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->createWorkRecords($workDate, '09:00', '18:00');

        // 休憩2ペアを追加
        $result = $this->summaryService->updateWorkTimes(
            $summary->id,
            '09:00',
            '18:00',
            [
                ['start' => '12:00', 'end' => '13:00'],
                ['start' => '15:00', 'end' => '15:30'],
            ],
            $this->approver->id
        );

        $this->assertEquals(90, $result->break_minutes, '60 + 30 = 90分');
        $this->assertEquals(450, $result->net_work_minutes, '540 - 90 = 450分');

        // BREAKレコードが4件（2ペア）作成されている
        $breakStarts = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('record_type', TimeRecordTypeEnum::BREAK_START)
            ->orderBy('record_time')
            ->get();
        $this->assertCount(2, $breakStarts);
        $this->assertEquals($workDate.' 12:00:00', $breakStarts[0]->record_time->format('Y-m-d H:i:s'));
        $this->assertEquals($workDate.' 15:00:00', $breakStarts[1]->record_time->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     *
     * 管理者直接修正: 休憩2ペア→1ペアに削減
     */
    public function e2e_admin_update_reduces_break_pairs(): void
    {
        $workDate = '2025-01-15';

        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => $workDate.' 09:00:00',
            'work_end' => $workDate.' 18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 90,
            'net_work_minutes' => 450,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->createWorkRecords($workDate, '09:00', '18:00');

        // 既存2ペア
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $workDate.' 12:00:00',
            'rounded_time' => $workDate.' 12:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $workDate.' 13:00:00',
            'rounded_time' => $workDate.' 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_START,
            'record_time' => $workDate.' 15:00:00',
            'rounded_time' => $workDate.' 15:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::BREAK_END,
            'record_time' => $workDate.' 15:30:00',
            'rounded_time' => $workDate.' 15:30:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // 1ペアに削減
        $result = $this->summaryService->updateWorkTimes(
            $summary->id,
            '09:00',
            '18:00',
            [['start' => '12:00', 'end' => '13:00']],
            null
        );

        $this->assertEquals(60, $result->break_minutes);

        $breakCount = TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->whereIn('record_type', [TimeRecordTypeEnum::BREAK_START, TimeRecordTypeEnum::BREAK_END])
            ->count();
        $this->assertEquals(2, $breakCount, '1ペア = 2レコード');
    }

    /**
     * @test
     *
     * 管理者直接修正 + record_source確認: 更新後のWORK_STARTがMANUALになること
     */
    public function e2e_admin_update_sets_record_source_to_manual(): void
    {
        $workDate = '2025-01-15';

        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => $workDate.' 09:30:00',
            'work_end' => $workDate.' 18:00:00',
            'work_minutes' => 510,
            'break_minutes' => 0,
            'net_work_minutes' => 510,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->createWorkRecords($workDate, '09:30', '18:00');

        $this->summaryService->updateWorkTimes($summary->id, '09:00', '18:00', [], $this->approver->id);

        $workStart = $this->getTimeRecord(TimeRecordTypeEnum::WORK_START);
        $this->assertEquals(RecordSourceEnum::MANUAL, $workStart->record_source);
        $this->assertEquals($workDate.' 09:00:00', $workStart->record_time->format('Y-m-d H:i:s'));
    }

    // ========================================
    // シフトパターンとの相互作用
    // ========================================

    /**
     * @test
     *
     * シフト設定ありの状態で出勤のみ修正→休憩フォールバックしない
     */
    public function e2e_approve_work_start_only_with_shift_does_not_add_phantom_break(): void
    {
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

        $this->createWorkRecords($targetDate, '09:30', '18:00');

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => $targetDate, 'reason' => '出勤修正', 'start_time' => '09:00']
        );
        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        $summary = $this->getSummary($targetDate);
        $this->assertEquals(0, $summary->break_minutes, '休憩打刻がないので0（フォールバックしない）');
        $this->assertEquals(540, $summary->net_work_minutes);
    }

    /**
     * @test
     *
     * シフト設定ありで休憩込みの修正→休憩打刻から正しく計算
     */
    public function e2e_approve_with_break_and_shift_uses_actual_break_records(): void
    {
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

        $correctionRequest = $this->correctionService->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            [
                'target_date' => $targetDate,
                'reason' => '全打刻',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'break_start_time' => '12:00',
                'break_end_time' => '12:30',
            ]
        );
        $this->correctionService->approveCorrectionRequest($correctionRequest->id, $this->approver->id);

        $summary = $this->getSummary($targetDate);
        $this->assertEquals(30, $summary->break_minutes, '12:00-12:30 = 30分（シフトの60分ではない）');
        $this->assertEquals(510, $summary->net_work_minutes, '540 - 30 = 510分');
    }

    /**
     * @test
     *
     * 管理者直接修正でbreakPeriods=[] + シフト設定あり → フォールバックしない
     */
    public function e2e_admin_empty_break_with_shift_does_not_fallback(): void
    {
        $workDate = '2025-01-15';

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
            'shift_date' => $workDate,
            'shift_pattern_id' => $shiftPattern->id,
        ]);

        $summary = DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $workDate,
            'work_start' => $workDate.' 09:00:00',
            'work_end' => $workDate.' 18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 60,
            'net_work_minutes' => 480,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->createWorkRecords($workDate, '09:00', '18:00');

        $result = $this->summaryService->updateWorkTimes($summary->id, '09:00', '18:00', [], null);

        $this->assertEquals(0, $result->break_minutes, 'シフト60分にフォールバックしない');
    }

    // ========================================
    // ヘルパーメソッド
    // ========================================

    private function createWorkRecords(
        string $date,
        string $startTime,
        string $endTime,
        RecordSourceEnum $source = RecordSourceEnum::AUTO
    ): void {
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $date.' '.$startTime.':00',
            'rounded_time' => $date.' '.$startTime.':00',
            'record_source' => $source,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $date.' '.$endTime.':00',
            'rounded_time' => $date.' '.$endTime.':00',
            'record_source' => $source,
        ]);
    }

    private function getSummary(string $date): ?DailyWorkSummary
    {
        return DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $date)
            ->first();
    }

    private function getTimeRecord(TimeRecordTypeEnum $type): TimeRecord
    {
        return TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('record_type', $type)
            ->first();
    }

    private function countTimeRecords(TimeRecordTypeEnum $type): int
    {
        return TimeRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('record_type', $type)
            ->count();
    }
}

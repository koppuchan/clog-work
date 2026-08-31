<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\TimeRecordCorrection;
use App\Models\TimeRecordCorrectionRequest;
use App\Models\TimeRecordCorrectionRequestDetail;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\CorrectionRequestDetailRepositoryInterface;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\TimeRecordCorrectionRepositoryInterface;
use App\Repositories\Contracts\TimeRecordCorrectionRequestRepositoryInterface;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 打刻修正申請サービス
 *
 * 従業員からの打刻修正申請に関するビジネスロジックを提供
 */
class TimeRecordCorrectionRequestService
{
    public function __construct(
        private readonly TimeRecordCorrectionRequestRepositoryInterface $correctionRequestRepository,
        private readonly CorrectionRequestDetailRepositoryInterface $correctionRequestDetailRepository,
        private readonly TimeRecordRepositoryInterface $timeRecordRepository,
        private readonly TimeRecordCorrectionRepositoryInterface $timeRecordCorrectionRepository,
        private readonly DailyWorkSummaryBatchService $dailyWorkSummaryBatchService,
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TimeRoundingService $timeRoundingService
    ) {}

    /**
     * IDで打刻修正申請を取得
     *
     * @param  int  $id  打刻修正申請ID
     */
    public function findById(int $id): ?TimeRecordCorrectionRequest
    {
        return $this->correctionRequestRepository->findById($id);
    }

    /**
     * 会社の打刻修正申請一覧を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $status  ステータスフィルター（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function getCorrectionRequestsByCompanyId(int $companyId, ?int $status = null): Collection
    {
        return $this->correctionRequestRepository->findByCompanyIdAndStatus($companyId, $status);
    }

    /**
     * ユーザーの打刻修正申請一覧を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  int|null  $status  ステータスフィルター（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function getCorrectionRequestsByUserId(int $companyId, int $userId, ?int $status = null): Collection
    {
        return $this->correctionRequestRepository->findByUserId($companyId, $userId, $status);
    }

    /**
     * 承認待ちの打刻修正申請一覧を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $approverId  承認者ID
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function getPendingCorrectionRequestsByApproverId(int $companyId, int $approverId): Collection
    {
        return $this->correctionRequestRepository->findByApproverId($companyId, $approverId, RequestStatusEnum::PENDING->value);
    }

    /**
     * 対象日の打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function getCorrectionRequestsByTargetDate(int $companyId, string $targetDate): Collection
    {
        return $this->correctionRequestRepository->findByTargetDate($companyId, $targetDate);
    }

    /**
     * 日付範囲の打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  int|null  $status  ステータスフィルター（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function getCorrectionRequestsByDateRange(int $companyId, string $startDate, string $endDate, ?int $status = null): Collection
    {
        return $this->correctionRequestRepository->findByDateRange($companyId, $startDate, $endDate, $status);
    }

    /**
     * 打刻修正申請を作成
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  申請者ID
     * @param  array<string, mixed>  $data  打刻修正申請データ
     */
    public function createCorrectionRequest(int $companyId, int $userId, array $data): TimeRecordCorrectionRequest
    {
        $requestData = array_merge($data, [
            'company_id' => $companyId,
            'user_id' => $userId,
            'status' => $data['status'] ?? RequestStatusEnum::PENDING->value,
        ]);

        return DB::transaction(function () use ($requestData) {
            return $this->correctionRequestRepository->create($requestData);
        });
    }

    /**
     * 打刻修正申請を更新
     *
     * @param  int  $id  打刻修正申請ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function updateCorrectionRequest(int $id, array $data): TimeRecordCorrectionRequest
    {
        return DB::transaction(function () use ($id, $data) {
            return $this->correctionRequestRepository->update($id, $data);
        });
    }

    /**
     * 打刻修正申請を削除
     *
     * @param  int  $id  打刻修正申請ID
     */
    public function deleteCorrectionRequest(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->correctionRequestRepository->delete($id);
        });
    }

    /**
     * 打刻修正申請を承認
     *
     * 承認時に以下の処理を行う：
     * 1. 申請ステータスを承認済みに更新
     * 2. time_recordsを更新または新規作成（UPSERT）
     * 3. time_record_correctionsに修正履歴を記録（バルクインサート）
     *
     * @param  int  $id  打刻修正申請ID
     * @param  int  $approverId  承認者ID
     */
    public function approveCorrectionRequest(int $id, int $approverId): TimeRecordCorrectionRequest
    {
        return DB::transaction(function () use ($id, $approverId) {
            // 申請を承認
            $correctionRequest = $this->correctionRequestRepository->approve($id, $approverId);

            // 明細を取得
            $correctionRequest->load('details');

            // 打刻データを反映し履歴を記録
            $this->applyCorrections($correctionRequest, $approverId);

            // 勤務実績を再計算
            $this->recalculateDailyWorkSummary($correctionRequest);

            return $correctionRequest;
        });
    }

    /**
     * 勤務実績を再計算
     *
     * @param  TimeRecordCorrectionRequest  $correctionRequest  打刻修正申請
     */
    private function recalculateDailyWorkSummary(TimeRecordCorrectionRequest $correctionRequest): void
    {
        $company = $this->companyRepository->findById($correctionRequest->company_id);
        $user = $this->userRepository->findById($correctionRequest->user_id);

        if ($company && $user) {
            $targetDate = CarbonImmutable::parse($correctionRequest->target_date);
            $dateString = $targetDate->format('Y-m-d');

            // aggregateByUserはrecord_sourceがAUTO以外の場合スキップするため、
            // 申請承認時は強制的にAUTOにリセットして再計算を実行する
            $existingSummary = $this->dailyWorkSummaryRepository->findByUserIdAndDate(
                $company->id,
                $user->id,
                $dateString
            );

            if ($existingSummary && $existingSummary->record_source !== RecordSourceEnum::AUTO) {
                $this->dailyWorkSummaryRepository->update($existingSummary->id, [
                    'record_source' => RecordSourceEnum::AUTO,
                ]);
            }

            $this->dailyWorkSummaryBatchService->aggregateByUser($company, $user, $targetDate);
        }
    }

    /**
     * 打刻修正を一括適用
     *
     * @param  TimeRecordCorrectionRequest  $correctionRequest  打刻修正申請
     * @param  int  $approverId  承認者ID
     */
    private function applyCorrections(TimeRecordCorrectionRequest $correctionRequest, int $approverId): void
    {
        $now = CarbonImmutable::now();

        // 既存レコードのIDを収集
        $existingRecordIds = $correctionRequest->details
            ->pluck('time_record_id')
            ->filter()
            ->toArray();

        // 既存レコードを一括取得
        $existingRecords = $existingRecordIds !== []
            ? $this->timeRecordRepository->findByIds($existingRecordIds)->keyBy('id')
            : collect();

        // 更新対象と新規作成対象を分類
        $detailsWithExistingRecord = $correctionRequest->details->filter(
            fn (TimeRecordCorrectionRequestDetail $detail) => $detail->time_record_id !== null && $existingRecords->has($detail->time_record_id)
        );

        $detailsWithoutRecord = $correctionRequest->details->filter(
            fn (TimeRecordCorrectionRequestDetail $detail) => $detail->time_record_id === null || ! $existingRecords->has($detail->time_record_id)
        );

        // 既存レコードをupsertで一括更新
        $updateRecords = $detailsWithExistingRecord->map(
            fn (TimeRecordCorrectionRequestDetail $detail) => [
                'id' => $detail->time_record_id,
                'company_id' => $correctionRequest->company_id,
                'user_id' => $correctionRequest->user_id,
                'record_type' => $detail->record_type->value,
                'record_time' => $detail->corrected_record_time,
                'rounded_time' => $detail->corrected_rounded_time,
                'record_source' => RecordSourceEnum::REQUEST->value,
                'note' => '申請による修正（申請ID: '.$correctionRequest->id.'）',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        )->toArray();

        if ($updateRecords !== []) {
            $this->timeRecordRepository->upsertMany(
                $updateRecords,
                ['id'],
                ['record_time', 'rounded_time', 'record_source', 'note', 'updated_at']
            );
        }

        // 既存レコード更新の履歴データを作成
        $updateHistories = $detailsWithExistingRecord->map(function (TimeRecordCorrectionRequestDetail $detail) use (
            $existingRecords,
            $correctionRequest,
            $approverId,
            $now
        ) {
            $existingRecord = $existingRecords->get($detail->time_record_id);

            return [
                'correction_request_detail_id' => $detail->id,
                'time_record_id' => $detail->time_record_id,
                'record_type' => $existingRecord->record_type->value,
                'before_record_time' => $existingRecord->record_time,
                'before_rounded_time' => $existingRecord->rounded_time,
                'before_record_source' => $existingRecord->record_source->value,
                'after_record_time' => $detail->corrected_record_time,
                'after_rounded_time' => $detail->corrected_rounded_time,
                'after_record_source' => RecordSourceEnum::REQUEST->value,
                'corrected_by' => $approverId,
                'correction_note' => $correctionRequest->reason,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->toArray();

        // 新規レコードを作成（1件ずつ作成してIDを取得し、明細を更新）
        // 新規作成は「修正」ではなく「追加」なので修正履歴には含めない
        $detailsWithoutRecord->each(function (TimeRecordCorrectionRequestDetail $detail) use ($correctionRequest) {
            $newRecord = $this->timeRecordRepository->create([
                'company_id' => $correctionRequest->company_id,
                'user_id' => $correctionRequest->user_id,
                'record_type' => $detail->record_type->value,
                'record_time' => $detail->corrected_record_time,
                'rounded_time' => $detail->corrected_rounded_time,
                'record_source' => RecordSourceEnum::REQUEST->value,
                'note' => '申請による新規登録（申請ID: '.$correctionRequest->id.'）',
            ]);

            // 明細にtime_record_idを更新
            $detail->update(['time_record_id' => $newRecord->id]);
        });

        // 修正履歴をバルクインサート（既存レコードの更新のみ）
        if ($updateHistories !== []) {
            $this->timeRecordCorrectionRepository->insertMany($updateHistories);
        }

        // 打刻修正の明細に休憩が含まれていない場合、既存の休憩レコードを削除する
        // （休憩時刻を空にして申請 = 休憩を削除する意図）
        $targetDate = $correctionRequest->target_date->format('Y-m-d');
        $correctedBreakTypes = $correctionRequest->details
            ->pluck('record_type')
            ->map(fn ($type) => $type->value)
            ->toArray();

        $breakTypesToDelete = [];
        if (! in_array(TimeRecordTypeEnum::BREAK_START->value, $correctedBreakTypes, true)) {
            $breakTypesToDelete[] = TimeRecordTypeEnum::BREAK_START->value;
        }
        if (! in_array(TimeRecordTypeEnum::BREAK_END->value, $correctedBreakTypes, true)) {
            $breakTypesToDelete[] = TimeRecordTypeEnum::BREAK_END->value;
        }

        if ($breakTypesToDelete !== []) {
            $this->timeRecordRepository->deleteByUserDateAndTypes(
                $correctionRequest->company_id,
                $correctionRequest->user_id,
                $targetDate,
                $breakTypesToDelete
            );
        }
    }

    /**
     * 打刻修正申請を却下
     *
     * @param  int  $id  打刻修正申請ID
     * @param  int  $approverId  承認者ID
     * @param  string  $rejectionReason  却下理由
     */
    public function rejectCorrectionRequest(int $id, int $approverId, string $rejectionReason): TimeRecordCorrectionRequest
    {
        return DB::transaction(function () use ($id, $approverId, $rejectionReason) {
            $correctionRequest = $this->correctionRequestRepository->reject($id, $approverId, $rejectionReason);

            return $correctionRequest;
        });
    }

    /**
     * 打刻修正申請を取り消し
     *
     * @param  int  $id  打刻修正申請ID
     */
    public function cancelCorrectionRequest(int $id): TimeRecordCorrectionRequest
    {
        return DB::transaction(function () use ($id) {
            $correctionRequest = $this->correctionRequestRepository->findById($id);
            $wasApproved = $correctionRequest->status === RequestStatusEnum::APPROVED;

            // 承認済みの場合は打刻データを元に戻す
            if ($wasApproved) {
                $this->restoreTimeRecords($correctionRequest);
            }

            // ステータスを取消に更新
            $correctionRequest = $this->correctionRequestRepository->update($id, [
                'status' => RequestStatusEnum::CANCELLED->value,
            ]);

            // 承認済みだった場合は勤務実績を再計算
            if ($wasApproved) {
                $this->recalculateDailyWorkSummary($correctionRequest);
            }

            return $correctionRequest;
        });
    }

    /**
     * 承認済み打刻修正申請を差し戻し（申請待ちに戻す）
     *
     * 承認時に適用された打刻データを元に戻し、修正履歴を削除する
     *
     * @param  int  $id  打刻修正申請ID
     */
    public function revertCorrectionRequest(int $id): TimeRecordCorrectionRequest
    {
        return DB::transaction(function () use ($id) {
            $correctionRequest = $this->correctionRequestRepository->findById($id);

            // 打刻データを元に戻す
            $this->restoreTimeRecords($correctionRequest);

            // ステータスを申請待ちに戻す
            $correctionRequest = $this->correctionRequestRepository->update($id, [
                'status' => RequestStatusEnum::PENDING->value,
                'approver_id' => null,
                'approved_at' => null,
                'rejection_reason' => null,
            ]);

            // 勤務実績を再計算
            $this->recalculateDailyWorkSummary($correctionRequest);

            return $correctionRequest;
        });
    }

    /**
     * 承認時に適用された打刻データを元に戻す
     */
    private function restoreTimeRecords(TimeRecordCorrectionRequest $correctionRequest): void
    {
        $correctionRequest->load('details');

        foreach ($correctionRequest->details as $detail) {
            $correction = TimeRecordCorrection::query()
                ->where('correction_request_detail_id', $detail->id)
                ->first();

            if ($correction && $correction->time_record_id) {
                // 元の値に戻す（打刻レコードが存在する場合のみ）
                $existingRecord = $this->timeRecordRepository->findById($correction->time_record_id);
                if ($existingRecord) {
                    $recordSource = $correction->before_record_source?->value
                        ?? RecordSourceEnum::AUTO->value;

                    $this->timeRecordRepository->update($correction->time_record_id, [
                        'record_time' => $correction->before_record_time,
                        'rounded_time' => $correction->before_rounded_time,
                        'record_source' => $recordSource,
                    ]);
                }

                // 修正履歴を削除
                $correction->delete();
            } elseif ($detail->time_record_id && ! $correction) {
                // 新規作成された打刻レコードの場合、削除してtime_record_idをnullに戻す
                $existingRecord = $this->timeRecordRepository->findById($detail->time_record_id);
                if ($existingRecord) {
                    $this->timeRecordRepository->delete($detail->time_record_id);
                }
                $detail->update(['time_record_id' => null]);
            }
        }
    }

    /**
     * 打刻間違い申請を作成（ヘッダーと明細を一括で作成）
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  array<string, mixed>  $data  申請データ（target_date, reason, start_time, end_time, break_end_time）
     */
    public function createClockErrorRequest(int $companyId, int $userId, array $data): TimeRecordCorrectionRequest
    {
        return DB::transaction(function () use ($companyId, $userId, $data) {
            $targetDate = $data['target_date'];

            // 既存の打刻データを検索
            $existingRecords = $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $targetDate);

            // 打刻修正申請ヘッダーを作成
            $correctionRequest = $this->correctionRequestRepository->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'target_date' => $targetDate,
                'reason' => $data['reason'],
                'status' => RequestStatusEnum::PENDING->value,
            ]);

            // 明細を作成（出勤時刻）
            if (! empty($data['start_time'])) {
                $existingWorkStart = $existingRecords->first(fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_START);
                $correctedStart = CarbonImmutable::parse("{$targetDate} {$data['start_time']}");

                $this->correctionRequestDetailRepository->create([
                    'correction_request_id' => $correctionRequest->id,
                    'time_record_id' => $existingWorkStart?->id,
                    'record_type' => TimeRecordTypeEnum::WORK_START,
                    'original_record_time' => $existingWorkStart?->record_time,
                    'original_rounded_time' => $existingWorkStart?->rounded_time,
                    'corrected_record_time' => $correctedStart,
                    'corrected_rounded_time' => $this->timeRoundingService->roundTime($companyId, $correctedStart, TimeRecordTypeEnum::WORK_START),
                ]);
            }

            // 明細を作成（退勤時刻）
            if (! empty($data['end_time'])) {
                // 既存の退勤打刻を検索（通常終了または日付越え終了）
                $existingWorkEnd = $existingRecords->first(
                    fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_END || $r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
                );

                $endDateTime = CarbonImmutable::parse("{$targetDate} {$data['end_time']}");

                // 終了時刻が開始時刻より前の場合は翌日とみなす
                $recordType = TimeRecordTypeEnum::WORK_END;
                if (! empty($data['start_time']) && $data['end_time'] < $data['start_time']) {
                    $endDateTime = $endDateTime->addDay();
                    $recordType = TimeRecordTypeEnum::WORK_END_NEXT_DAY;
                }

                $this->correctionRequestDetailRepository->create([
                    'correction_request_id' => $correctionRequest->id,
                    'time_record_id' => $existingWorkEnd?->id,
                    'record_type' => $recordType,
                    'original_record_time' => $existingWorkEnd?->record_time,
                    'original_rounded_time' => $existingWorkEnd?->rounded_time,
                    'corrected_record_time' => $endDateTime,
                    'corrected_rounded_time' => $this->timeRoundingService->roundTime($companyId, $endDateTime, $recordType),
                ]);
            }

            // 夜勤の休憩翌日補正用: 出勤時刻を基準にする
            $workStartForBreak = null;
            if (! empty($data['start_time'])) {
                $workStartForBreak = CarbonImmutable::parse("{$targetDate} {$data['start_time']}");
            } else {
                $existingWorkStart = $existingRecords->first(fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_START);
                if ($existingWorkStart) {
                    $workStartForBreak = CarbonImmutable::parse($existingWorkStart->record_time);
                }
            }

            // 明細を作成（休憩開始時刻）
            if (! empty($data['break_start_time'])) {
                $existingBreakStart = $existingRecords->first(fn ($r) => $r->record_type === TimeRecordTypeEnum::BREAK_START);
                $correctedBreakStart = CarbonImmutable::parse("{$targetDate} {$data['break_start_time']}");

                // 夜勤: 休憩開始が出勤時刻より前なら翌日に補正
                if ($workStartForBreak && $correctedBreakStart->lt($workStartForBreak)) {
                    $correctedBreakStart = $correctedBreakStart->addDay();
                }

                $this->correctionRequestDetailRepository->create([
                    'correction_request_id' => $correctionRequest->id,
                    'time_record_id' => $existingBreakStart?->id,
                    'record_type' => TimeRecordTypeEnum::BREAK_START,
                    'original_record_time' => $existingBreakStart?->record_time,
                    'original_rounded_time' => $existingBreakStart?->rounded_time,
                    'corrected_record_time' => $correctedBreakStart,
                    'corrected_rounded_time' => $this->timeRoundingService->roundTime($companyId, $correctedBreakStart, TimeRecordTypeEnum::BREAK_START),
                ]);
            }

            // 明細を作成（休憩終了時刻）
            if (! empty($data['break_end_time'])) {
                $existingBreakEnd = $existingRecords->first(fn ($r) => $r->record_type === TimeRecordTypeEnum::BREAK_END);
                $correctedBreakEnd = CarbonImmutable::parse("{$targetDate} {$data['break_end_time']}");

                // 夜勤: 休憩終了が出勤時刻より前なら翌日に補正
                if ($workStartForBreak && $correctedBreakEnd->lt($workStartForBreak)) {
                    $correctedBreakEnd = $correctedBreakEnd->addDay();
                }

                // 休憩終了 <= 休憩開始の場合はさらに+1日（日跨ぎ休憩）
                if (! empty($data['break_start_time']) && $data['break_end_time'] <= $data['break_start_time']) {
                    $correctedBreakEnd = $correctedBreakEnd->addDay();
                }

                $this->correctionRequestDetailRepository->create([
                    'correction_request_id' => $correctionRequest->id,
                    'time_record_id' => $existingBreakEnd?->id,
                    'record_type' => TimeRecordTypeEnum::BREAK_END,
                    'original_record_time' => $existingBreakEnd?->record_time,
                    'original_rounded_time' => $existingBreakEnd?->rounded_time,
                    'corrected_record_time' => $correctedBreakEnd,
                    'corrected_rounded_time' => $this->timeRoundingService->roundTime($companyId, $correctedBreakEnd, TimeRecordTypeEnum::BREAK_END),
                ]);
            }

            return $correctionRequest->load('details');
        });
    }
}

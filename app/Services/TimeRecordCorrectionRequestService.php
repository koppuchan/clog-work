<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Exceptions\BusinessException;
use App\Models\TimeRecord;
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
        private readonly TimeRoundingService $timeRoundingService,
        private readonly PreviousNightTimeRecords $previousNightTimeRecords
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

        // 既存レコードと同じ時刻（実質的な変更なし）の明細は、修正扱いにしない。
        // 打刻時刻の変更が無いのに時間数の再計算だけ行った場合、打刻修正の
        // 履歴・色分け表示が出てしまうのを防ぐため（比較は分単位で行う。
        // 画面には秒を持たせておらず、実打刻の秒数と一致しないだけで
        // 「変更あり」と誤判定してしまうため）。
        $detailsWithExistingRecord = $detailsWithExistingRecord->filter(function (TimeRecordCorrectionRequestDetail $detail) use ($existingRecords) {
            $existingRecord = $existingRecords->get($detail->time_record_id);

            return $existingRecord->record_time->format('Y-m-d H:i') !== $detail->corrected_record_time->format('Y-m-d H:i');
        });

        $detailsWithoutRecord = $correctionRequest->details->filter(
            fn (TimeRecordCorrectionRequestDetail $detail) => $detail->time_record_id === null || ! $existingRecords->has($detail->time_record_id)
        );

        // 承認時点で申請内容と食い違う打刻が既にできていないか確認する。
        // 申請作成から承認までの間（数日〜数週間空くこともある）に、管理者の
        // 手動編集や別の申請の承認で打刻が変わっていることがある。気づかず
        // そのまま適用すると、既にある休憩と別にもう一組休憩を新規作成して
        // 二重計上したり、他の人が入れた新しい値を古い申請内容で上書きして
        // しまう（クライアント報告: 休憩打刻修正の承認で休憩が重複して計上された）。
        $this->assertNoStaleConflicts($correctionRequest, $detailsWithExistingRecord, $detailsWithoutRecord, $existingRecords);
        $this->assertBreakDetailsArePaired($correctionRequest);

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
            // record_typeも更新対象に含める。退勤の日跨ぎ判定(WORK_END/
            // WORK_END_NEXT_DAY)は修正後の時刻から自動的に決まるため、
            // 例えば日付越えだった退勤を同日中の時刻に修正した場合、
            // 種別もWORK_ENDへ戻す必要がある。ここに含めないと時刻だけ
            // 更新されて種別が古いまま残り、日跨ぎ判定に依存する表示
            // （「(翌)」表示等）や集計が実際の時刻と食い違ってしまう。
            $this->timeRecordRepository->upsertMany(
                $updateRecords,
                ['id'],
                ['record_type', 'record_time', 'rounded_time', 'record_source', 'note', 'updated_at']
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
     * 休憩の明細が、開始と終了のセットになっているか確認する
     *
     * 片方だけを承認すると、もう片方の既存の休憩の打刻が消えたり、休憩の開始だけが残って
     * 「休憩漏れ」になったりする。この確認が入る前に出された申請を承認するときの保護
     * （画面・申請時にも同じ確認をしている）。
     *
     * @throws BusinessException 休憩の開始と終了のどちらか一方しかない場合
     */
    private function assertBreakDetailsArePaired(TimeRecordCorrectionRequest $correctionRequest): void
    {
        $types = $correctionRequest->details->pluck('record_type');
        $hasBreakStart = $types->contains(TimeRecordTypeEnum::BREAK_START);
        $hasBreakEnd = $types->contains(TimeRecordTypeEnum::BREAK_END);

        if ($hasBreakStart !== $hasBreakEnd) {
            throw new BusinessException('休憩の開始と終了のどちらか一方しか申請されていないため、承認できません。承認すると、休憩の打刻が欠けたままになります。却下して、休憩の開始と終了をセットで申請し直してもらってください。');
        }
    }

    /**
     * 承認時点の打刻が、申請作成時点の内容と食い違っていないか確認する
     *
     * 2種類の食い違いを検知する。
     * 1. 既存レコードを修正する明細: 申請作成時に記録した original_record_time と、
     *    承認時点の実際の打刻時刻が一致しない場合（申請後に他の手段で打刻が
     *    変わっている）。そのまま上書きすると、その変更を古い申請内容で
     *    無かったことにしてしまう。
     * 2. 新規作成する明細（申請時点で未打刻だった項目）: 承認時点で既に
     *    同じ種別の打刻が存在する場合（申請後に他の手段で打刻が追加されている）。
     *    そのまま新規作成すると、同じ休憩などが二重にできてしまう。
     *
     * @param  Collection<int, TimeRecordCorrectionRequestDetail>  $detailsWithExistingRecord  既存レコードを更新する明細（無変更を除く）
     * @param  Collection<int, TimeRecordCorrectionRequestDetail>  $detailsWithoutRecord  新規作成する明細
     * @param  \Illuminate\Support\Collection<int, TimeRecord>  $existingRecords  この申請の明細が参照する既存レコード（id => レコード）。
     *                                                                            明細が1件も既存レコードを参照しない場合は素の Support\Collection（空）になるため、その基底型で受ける
     *
     * @throws BusinessException 承認時点の打刻が申請内容と食い違う場合
     */
    private function assertNoStaleConflicts(
        TimeRecordCorrectionRequest $correctionRequest,
        Collection $detailsWithExistingRecord,
        Collection $detailsWithoutRecord,
        \Illuminate\Support\Collection $existingRecords
    ): void {
        foreach ($detailsWithExistingRecord as $detail) {
            $existingRecord = $existingRecords->get($detail->time_record_id);

            if ($existingRecord === null || $detail->original_record_time === null) {
                continue;
            }

            if ($existingRecord->record_time->format('Y-m-d H:i') !== $detail->original_record_time->format('Y-m-d H:i')) {
                throw new BusinessException(sprintf(
                    '承認できませんでした。%sの打刻が申請後に%sへ変更されているため、内容を確認のうえ申請し直してください。',
                    $detail->record_type->label(),
                    $existingRecord->record_time->format('H:i')
                ));
            }
        }

        if ($detailsWithoutRecord->isEmpty()) {
            return;
        }

        $targetDate = $correctionRequest->target_date->format('Y-m-d');
        $nextDate = CarbonImmutable::parse($targetDate)->addDay()->format('Y-m-d');
        $linkedRecordIds = $existingRecords->keys()->all();

        $currentRecords = $this->timeRecordRepository
            ->findByUserIdAndDate($correctionRequest->company_id, $correctionRequest->user_id, $targetDate)
            ->merge($this->timeRecordRepository->findByUserIdAndDate($correctionRequest->company_id, $correctionRequest->user_id, $nextDate))
            ->reject(fn (TimeRecord $r) => in_array($r->id, $linkedRecordIds, true));

        foreach ($detailsWithoutRecord as $detail) {
            $conflict = $currentRecords->first(fn (TimeRecord $r) => $r->record_type === $detail->record_type);

            if ($conflict !== null) {
                throw new BusinessException(sprintf(
                    '承認できませんでした。申請後に%sの打刻（%s）が別途追加されているため、内容を確認のうえ申請し直してください。',
                    $detail->record_type->label(),
                    $conflict->record_time->format('H:i')
                ));
            }
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
            //
            // 対象日の日付には前夜の日付越え勤務の退勤・休憩が混在することがある。
            // これを対象日の打刻として扱うと、修正対象に前日の打刻を選んでしまい、
            // 承認しても対象日の退勤・休憩が変わらず前日のものだけが書き換わるため除く。
            $existingRecords = $this->previousNightTimeRecords->excludeFrom(
                $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $targetDate)
            );

            // 日跨ぎ夜勤の場合、退勤（WORK_END_NEXT_DAY）とその前の休憩は
            // 翌日の日付でレコードされているため、対象日だけの検索では
            // 見つからない。見つからないと time_record_id が null のまま
            // 「新規追加」として扱われ、承認時に既存レコードを更新せず
            // 別の新しいレコードを作成してしまう。結果、古い（未修正の）
            // 翌日レコードが残ったままになり、承認しても勤務実績の表示が
            // 変わらないように見えてしまう（クライアント報告: 打刻修正を
            // 承認しても反映されなかった）。DailyWorkSummaryBatchService
            // と同じ考え方で、翌日の日付越え退勤・休憩レコードをマージする。
            $nextDateString = CarbonImmutable::parse($targetDate)->addDay()->format('Y-m-d');
            $nextDayRecords = $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $nextDateString);
            $nextDayEndRecords = $nextDayRecords->filter(
                fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
            );
            if ($nextDayEndRecords->isNotEmpty()) {
                $existingRecords = $existingRecords->merge($nextDayEndRecords);

                // WORK_END_NEXT_DAY 以前の翌日休憩レコードのみマージ
                $crossDayEndTime = $nextDayEndRecords->first()->record_time;
                $nextDayBreakRecords = $nextDayRecords->filter(
                    fn ($r) => ($r->record_type === TimeRecordTypeEnum::BREAK_START
                            || $r->record_type === TimeRecordTypeEnum::BREAK_END)
                        && $r->record_time->lte($crossDayEndTime)
                );
                if ($nextDayBreakRecords->isNotEmpty()) {
                    $existingRecords = $existingRecords->merge($nextDayBreakRecords);
                }
            }

            $this->assertBreakRequestIsConsistent($data, $existingRecords);

            // 打刻修正申請ヘッダーを作成
            $correctionRequest = $this->correctionRequestRepository->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'target_date' => $targetDate,
                'reason' => $data['reason'],
                'status' => RequestStatusEnum::PENDING->value,
            ]);

            $existingWorkStart = $existingRecords->first(fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_START);

            // 夜勤の翌日判定（退勤・休憩）の基準にする出勤時刻。
            // 出勤時刻が今回の申請に含まれない場合（出勤は合っていて退勤だけ
            // 間違っていた場合など）は、既存の出勤打刻を基準にする。ここを
            // 申請内のstart_timeだけで判定すると、出勤は合っていて退勤だけ
            // 間違っていた（画面の案内どおりstart_timeを空欄で送る）ケースで
            // 日付越え判定が働かず、既存の出勤打刻より前の時刻とみなされて
            // 集計から漏れてしまう。
            $referenceStartTime = $data['start_time'] ?? null;
            if (empty($referenceStartTime) && $existingWorkStart) {
                $referenceStartTime = $existingWorkStart->record_time->format('H:i');
            }

            // 明細を作成（出勤時刻）
            if (! empty($data['start_time'])) {
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
                // 複数ある場合は、集計・編集画面の表示と同じく最後のものを対象にする
                $existingWorkEnd = $existingRecords
                    ->filter(fn ($r) => $r->record_type->isWorkEnd())
                    ->last();

                $endDateTime = CarbonImmutable::parse("{$targetDate} {$data['end_time']}");

                // 終了時刻が出勤時刻（referenceStartTime）より前の場合は翌日とみなす
                $recordType = TimeRecordTypeEnum::WORK_END;
                if (! empty($referenceStartTime) && $data['end_time'] < $referenceStartTime) {
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
            $workStartForBreak = ! empty($referenceStartTime)
                ? CarbonImmutable::parse("{$targetDate} {$referenceStartTime}")
                : null;

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

    /**
     * 休憩の申請内容に矛盾がないか確認する
     *
     * 休憩は開始と終了をセットで申請する。片方だけだと、承認したときに、もう片方の
     * 既存の休憩の打刻が消えたり、対応する打刻のない休憩だけが残ったりする。
     * また、勤務時間が分かるときは、休憩が勤務時間の範囲に収まっていることを確認する。
     * 範囲外の休憩開始は、夜勤の休憩とみなされて翌日の打刻として保存されてしまい、
     * 勤務の打刻がない日に休憩だけが残ってしまう（クライアント報告: 打刻が無いのに
     * 休憩漏れの表示が出る）。日勤の勤務で、出勤より前の時刻を休憩として申請した場合などが該当する。
     *
     * @param  array<string, mixed>  $data  申請データ
     * @param  \Illuminate\Support\Collection<int, TimeRecord>  $existingRecords  対象日の既存の打刻
     *
     * @throws BusinessException 休憩の申請内容に矛盾がある場合
     */
    private function assertBreakRequestIsConsistent(array $data, \Illuminate\Support\Collection $existingRecords): void
    {
        $breakStart = $data['break_start_time'] ?? null;
        $breakEnd = $data['break_end_time'] ?? null;

        if (empty($breakStart) && empty($breakEnd)) {
            return;
        }

        if (empty($breakStart) || empty($breakEnd)) {
            throw new BusinessException('休憩は開始時刻と終了時刻をセットで入力してください。休憩を取っていない場合は、両方を空欄にしてください。');
        }

        // 勤務時間は、今回の申請にあればそれを、なければ既存の打刻を使う
        $workStart = ! empty($data['start_time'])
            ? $data['start_time']
            : $existingRecords->first(fn ($r) => $r->record_type === TimeRecordTypeEnum::WORK_START)?->record_time->format('H:i');
        $workEnd = ! empty($data['end_time'])
            ? $data['end_time']
            : $existingRecords->filter(fn ($r) => $r->record_type->isWorkEnd())->last()?->record_time->format('H:i');

        if ($workStart === null || $workEnd === null) {
            return;
        }

        if (! $this->isBreakWithinWork($workStart, $workEnd, $breakStart, $breakEnd)) {
            throw new BusinessException(sprintf(
                '休憩時間が勤務時間の範囲外です。勤務時間内（出勤 %s／退勤 %s）で入力してください。',
                $workStart,
                $workEnd
            ));
        }
    }

    /**
     * 休憩が勤務時間の範囲に収まっているか（勤務終了が開始以前なら、日跨ぎの勤務として扱う）
     *
     * 管理画面の勤務実績の編集（ValidatesBreakPeriods）と同じ基準。
     */
    private function isBreakWithinWork(string $workStart, string $workEnd, string $breakStart, string $breakEnd): bool
    {
        $toMinutes = function (string $time): int {
            [$hours, $minutes] = array_map('intval', explode(':', $time));

            return $hours * 60 + $minutes;
        };

        $workStartMinutes = $toMinutes($workStart);
        $workEndMinutes = $toMinutes($workEnd);
        if ($workEndMinutes <= $workStartMinutes) {
            $workEndMinutes += 24 * 60;
        }

        $breakStartMinutes = $toMinutes($breakStart);
        $breakEndMinutes = $toMinutes($breakEnd);

        // 開始と終了が同じ時刻の休憩は、24時間の休憩として扱わず、不正とする
        if ($breakStartMinutes === $breakEndMinutes) {
            return false;
        }

        // 勤務開始より前の時刻は、日跨ぎ勤務における翌日分とみなす
        if ($breakStartMinutes < $workStartMinutes) {
            $breakStartMinutes += 24 * 60;
        }
        if ($breakEndMinutes <= $breakStartMinutes) {
            $breakEndMinutes += 24 * 60;
        }

        return $breakStartMinutes >= $workStartMinutes && $breakEndMinutes <= $workEndMinutes;
    }
}

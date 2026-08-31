<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RequestStatusEnum;
use App\Models\ApplicationType;
use App\Models\Request;
use App\Repositories\Contracts\ApplicationTypeRepositoryInterface;
use App\Repositories\Contracts\RequestRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 申請サービス
 *
 * 休暇、遅刻、早退、欠席などの各種申請に関するビジネスロジックを提供
 */
class RequestService
{
    public function __construct(
        private readonly RequestRepositoryInterface $requestRepository,
        private readonly ApplicationTypeRepositoryInterface $applicationTypeRepository,
        private readonly LeaveApplicationService $leaveApplicationService
    ) {}

    /**
     * IDで申請を取得
     *
     * @param  int  $id  申請ID
     */
    public function findById(int $id): ?Request
    {
        return $this->requestRepository->findById($id);
    }

    /**
     * 会社の申請一覧を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $status  ステータスフィルター（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function getRequestsByCompanyId(int $companyId, ?int $status = null): Collection
    {
        return $this->requestRepository->findByCompanyIdAndStatus($companyId, $status);
    }

    /**
     * ユーザーの申請一覧を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  int|null  $status  ステータスフィルター（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function getRequestsByUserId(int $companyId, int $userId, ?int $status = null): Collection
    {
        return $this->requestRepository->findByRequestedBy($companyId, $userId, $status);
    }

    /**
     * 承認待ちの申請一覧を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $approverId  承認者ID
     * @return Collection<int, Request>
     */
    public function getPendingRequestsByApproverId(int $companyId, int $approverId): Collection
    {
        return $this->requestRepository->findByApproverId($companyId, $approverId, RequestStatusEnum::PENDING->value);
    }

    /**
     * 対象日の申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @return Collection<int, Request>
     */
    public function getRequestsByTargetDate(int $companyId, string $targetDate): Collection
    {
        return $this->requestRepository->findByTargetDate($companyId, $targetDate);
    }

    /**
     * 日付範囲の申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  int|null  $status  ステータスフィルター（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function getRequestsByDateRange(int $companyId, string $startDate, string $endDate, ?int $status = null): Collection
    {
        return $this->requestRepository->findByDateRange($companyId, $startDate, $endDate, $status);
    }

    /**
     * ユーザーIDと日付範囲の申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  int|null  $status  ステータスフィルター（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function getRequestsByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate, ?int $status = null): Collection
    {
        return $this->requestRepository->findByUserIdAndDateRange($companyId, $userId, $startDate, $endDate, $status);
    }

    /**
     * 同一日・同一タイプの申請が既に存在するか確認
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @param  int  $type  申請タイプID
     */
    public function existsDuplicateRequest(int $companyId, int $userId, string $targetDate, int $type): bool
    {
        return $this->requestRepository->existsByUserIdAndDateAndType($companyId, $userId, $targetDate, $type);
    }

    /**
     * 申請を作成
     *
     * @param  int  $companyId  会社ID
     * @param  int  $requestedBy  申請者ID
     * @param  array<string, mixed>  $data  申請データ
     *
     * @throws \RuntimeException 同一日・同一タイプの申請が既に存在する場合
     */
    public function createRequest(int $companyId, int $requestedBy, array $data): Request
    {
        // 同一日・同一タイプの重複チェック
        if ($this->requestRepository->existsByUserIdAndDateAndType(
            $companyId,
            $requestedBy,
            $data['target_date'],
            $data['type']
        )) {
            throw new \RuntimeException('同じ日に同じタイプの申請が既に存在します。');
        }

        $requestData = array_merge($data, [
            'company_id' => $companyId,
            'requested_by' => $requestedBy,
            'status' => $data['status'] ?? RequestStatusEnum::PENDING->value,
        ]);

        return DB::transaction(function () use ($requestData) {
            return $this->requestRepository->create($requestData);
        });
    }

    /**
     * 申請を更新
     *
     * @param  int  $id  申請ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function updateRequest(int $id, array $data): Request
    {
        return DB::transaction(function () use ($id, $data) {
            return $this->requestRepository->update($id, $data);
        });
    }

    /**
     * 申請を削除
     *
     * @param  int  $id  申請ID
     */
    public function deleteRequest(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->requestRepository->delete($id);
        });
    }

    /**
     * 申請を承認
     *
     * @param  int  $id  申請ID
     * @param  int  $approverId  承認者ID
     * @param  string|null  $decisionNote  承認コメント
     */
    public function approveRequest(int $id, int $approverId, ?string $decisionNote = null): Request
    {
        return DB::transaction(function () use ($id, $approverId, $decisionNote) {
            $request = $this->requestRepository->approve($id, $approverId, $decisionNote);

            // 休暇申請の場合は勤務実績に反映
            if (LeaveApplicationService::isLeaveApplication($request->type)) {
                $this->leaveApplicationService->applyLeaveToWorkSummary($request);
            }

            // 残業申請は承認のみ行い、勤務実績の時間外は打刻ベースの自動計算値を維持する

            return $request;
        });
    }

    /**
     * 申請を却下
     *
     * @param  int  $id  申請ID
     * @param  int  $approverId  承認者ID
     * @param  string|null  $decisionNote  却下理由
     */
    public function rejectRequest(int $id, int $approverId, ?string $decisionNote = null): Request
    {
        return DB::transaction(function () use ($id, $approverId, $decisionNote) {
            $request = $this->requestRepository->reject($id, $approverId, $decisionNote);

            return $request;
        });
    }

    /**
     * 申請を取り消し（申請中の申請）
     *
     * @param  int  $id  申請ID
     */
    public function cancelRequest(int $id): Request
    {
        return DB::transaction(function () use ($id) {
            return $this->requestRepository->update($id, [
                'status' => RequestStatusEnum::CANCELLED->value,
            ]);
        });
    }

    /**
     * 承認済み申請を差し戻し（申請中に戻す）
     *
     * @param  int  $id  申請ID
     */
    public function revertRequest(int $id): Request
    {
        return DB::transaction(function () use ($id) {
            $request = $this->requestRepository->findById($id);

            // 休暇申請の場合は勤務実績の反映を取り消す
            if (LeaveApplicationService::isLeaveApplication($request->type)) {
                $this->leaveApplicationService->removeLeaveFromWorkSummary($request);
            }

            return $this->requestRepository->update($id, [
                'status' => RequestStatusEnum::PENDING->value,
                'approver_id' => null,
                'decided_at' => null,
                'decision_note' => null,
            ]);
        });
    }

    /**
     * 承認済み申請を取り消し
     *
     * @param  int  $id  申請ID
     */
    public function cancelApprovedRequest(int $id): Request
    {
        return DB::transaction(function () use ($id) {
            $request = $this->requestRepository->findById($id);

            // 休暇申請の場合は勤務実績の反映を取り消す
            if (LeaveApplicationService::isLeaveApplication($request->type)) {
                $this->leaveApplicationService->removeLeaveFromWorkSummary($request);
            }

            return $this->requestRepository->update($id, [
                'status' => RequestStatusEnum::CANCELLED->value,
            ]);
        });
    }

    /**
     * 有効な申請タイプ一覧を取得
     *
     * @return Collection<int, ApplicationType>
     */
    public function getAllActiveApplicationTypes(): Collection
    {
        return $this->applicationTypeRepository->findAllActive();
    }

    /**
     * 申請タイプコードからIDを取得
     *
     * @param  string  $code  申請タイプコード（例: 'clock-error', 'paid-leave'）
     */
    public function getApplicationTypeByCode(string $code): ?ApplicationType
    {
        return $this->applicationTypeRepository->findByCode($code);
    }
}

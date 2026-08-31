<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TimeRecordCorrectionRequest;
use App\Repositories\Contracts\TimeRecordCorrectionRequestRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * 打刻修正申請リポジトリ実装
 */
class TimeRecordCorrectionRequestRepository implements TimeRecordCorrectionRequestRepositoryInterface
{
    public function __construct(
        private readonly TimeRecordCorrectionRequest $correctionRequest
    ) {}

    /**
     * IDで打刻修正申請を取得
     *
     * @param  int  $id  打刻修正申請ID
     */
    public function findById(int $id): ?TimeRecordCorrectionRequest
    {
        return $this->correctionRequest->query()
            ->with(['company', 'user', 'approver', 'details'])
            ->find($id);
    }

    /**
     * 会社IDとステータスで打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByCompanyIdAndStatus(int $companyId, ?int $status = null): Collection
    {
        $query = $this->correctionRequest->query()
            ->where('company_id', $companyId)
            ->with(['company', 'user', 'approver', 'details']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * ユーザーIDで打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByUserId(int $companyId, int $userId, ?int $status = null): Collection
    {
        $query = $this->correctionRequest->query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->with(['company', 'user', 'approver', 'details']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 承認者IDで打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $approverId  承認者ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByApproverId(int $companyId, int $approverId, ?int $status = null): Collection
    {
        $query = $this->correctionRequest->query()
            ->where('company_id', $companyId)
            ->where('approver_id', $approverId)
            ->with(['company', 'user', 'approver', 'details']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 対象日で打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByTargetDate(int $companyId, string $targetDate): Collection
    {
        return $this->correctionRequest->query()
            ->where('company_id', $companyId)
            ->where('target_date', $targetDate)
            ->with(['company', 'user', 'approver', 'details'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 日付範囲で打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByDateRange(int $companyId, string $startDate, string $endDate, ?int $status = null): Collection
    {
        $query = $this->correctionRequest->query()
            ->where('company_id', $companyId)
            ->whereBetween('target_date', [$startDate, $endDate])
            ->with(['company', 'user', 'approver', 'details']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 打刻修正申請を作成
     *
     * @param  array<string, mixed>  $data  打刻修正申請データ
     */
    public function create(array $data): TimeRecordCorrectionRequest
    {
        return $this->correctionRequest->query()->create($data);
    }

    /**
     * 打刻修正申請を更新
     *
     * @param  int  $id  打刻修正申請ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): TimeRecordCorrectionRequest
    {
        $correctionRequest = $this->findById($id);
        if (! $correctionRequest) {
            throw new \RuntimeException("TimeRecordCorrectionRequest with ID {$id} not found");
        }

        $correctionRequest->update($data);

        return $correctionRequest->fresh();
    }

    /**
     * 打刻修正申請を削除
     *
     * @param  int  $id  打刻修正申請ID
     */
    public function delete(int $id): bool
    {
        $correctionRequest = $this->findById($id);
        if (! $correctionRequest) {
            return false;
        }

        return $correctionRequest->delete();
    }

    /**
     * 打刻修正申請を承認
     *
     * @param  int  $id  打刻修正申請ID
     * @param  int  $approverId  承認者ID
     */
    public function approve(int $id, int $approverId): TimeRecordCorrectionRequest
    {
        $correctionRequest = $this->findById($id);
        if (! $correctionRequest) {
            throw new \RuntimeException("TimeRecordCorrectionRequest with ID {$id} not found");
        }

        $correctionRequest->update([
            'status' => 2, // 承認済み
            'approver_id' => $approverId,
            'approved_at' => CarbonImmutable::now(),
        ]);

        return $correctionRequest->fresh();
    }

    /**
     * 打刻修正申請を却下
     *
     * @param  int  $id  打刻修正申請ID
     * @param  int  $approverId  承認者ID
     * @param  string  $rejectionReason  却下理由
     */
    public function reject(int $id, int $approverId, string $rejectionReason): TimeRecordCorrectionRequest
    {
        $correctionRequest = $this->findById($id);
        if (! $correctionRequest) {
            throw new \RuntimeException("TimeRecordCorrectionRequest with ID {$id} not found");
        }

        $correctionRequest->update([
            'status' => 3, // 却下
            'approver_id' => $approverId,
            'approved_at' => CarbonImmutable::now(),
            'rejection_reason' => $rejectionReason,
        ]);

        return $correctionRequest->fresh();
    }
}

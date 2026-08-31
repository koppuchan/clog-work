<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Request;
use App\Repositories\Contracts\RequestRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * 申請リポジトリ実装
 */
class RequestRepository implements RequestRepositoryInterface
{
    public function __construct(
        private readonly Request $request
    ) {}

    /**
     * IDで申請を取得
     *
     * @param  int  $id  申請ID
     */
    public function findById(int $id): ?Request
    {
        return $this->request->query()
            ->with(['company', 'requestedBy', 'approver', 'applicationType'])
            ->find($id);
    }

    /**
     * 会社IDとステータスで申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByCompanyIdAndStatus(int $companyId, ?int $status = null): Collection
    {
        $query = $this->request->query()
            ->where('company_id', $companyId)
            ->with(['company', 'requestedBy', 'approver', 'applicationType']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 申請者IDで申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  申請者ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByRequestedBy(int $companyId, int $userId, ?int $status = null): Collection
    {
        $query = $this->request->query()
            ->where('company_id', $companyId)
            ->where('requested_by', $userId)
            ->with(['company', 'requestedBy', 'approver', 'applicationType']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 承認者IDで申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $approverId  承認者ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByApproverId(int $companyId, int $approverId, ?int $status = null): Collection
    {
        $query = $this->request->query()
            ->where('company_id', $companyId)
            ->where('approver_id', $approverId)
            ->with(['company', 'requestedBy', 'approver', 'applicationType']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 対象日で申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @return Collection<int, Request>
     */
    public function findByTargetDate(int $companyId, string $targetDate): Collection
    {
        return $this->request->query()
            ->where('company_id', $companyId)
            ->where('target_date', $targetDate)
            ->with(['company', 'requestedBy', 'approver', 'applicationType'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 日付範囲で申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByDateRange(int $companyId, string $startDate, string $endDate, ?int $status = null): Collection
    {
        $query = $this->request->query()
            ->where('company_id', $companyId)
            ->whereBetween('target_date', [$startDate, $endDate])
            ->with(['company', 'requestedBy', 'approver', 'applicationType']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * ユーザーIDと日付範囲で申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate, ?int $status = null): Collection
    {
        $query = $this->request->query()
            ->where('company_id', $companyId)
            ->where('requested_by', $userId)
            ->whereBetween('target_date', [$startDate, $endDate])
            ->with(['company', 'requestedBy', 'approver', 'applicationType']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('target_date', 'asc')
            ->get();
    }

    /**
     * ユーザーIDと対象日と申請タイプで申請が存在するか確認
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @param  int  $type  申請タイプID
     */
    public function existsByUserIdAndDateAndType(int $companyId, int $userId, string $targetDate, int $type): bool
    {
        return $this->request->query()
            ->where('company_id', $companyId)
            ->where('requested_by', $userId)
            ->where('target_date', $targetDate)
            ->where('type', $type)
            ->whereIn('status', [1, 2]) // 申請中 or 承認済みのみ
            ->exists();
    }

    /**
     * 申請を作成
     *
     * @param  array<string, mixed>  $data  申請データ
     */
    public function create(array $data): Request
    {
        return $this->request->query()->create($data);
    }

    /**
     * 申請を更新
     *
     * @param  int  $id  申請ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): Request
    {
        $request = $this->findById($id);
        if (! $request) {
            throw new \RuntimeException("Request with ID {$id} not found");
        }

        $request->update($data);

        return $request->fresh();
    }

    /**
     * 申請を削除
     *
     * @param  int  $id  申請ID
     */
    public function delete(int $id): bool
    {
        $request = $this->findById($id);
        if (! $request) {
            return false;
        }

        return $request->delete();
    }

    /**
     * 申請を承認
     *
     * @param  int  $id  申請ID
     * @param  int  $approverId  承認者ID
     * @param  string|null  $decisionNote  承認コメント
     */
    public function approve(int $id, int $approverId, ?string $decisionNote = null): Request
    {
        $request = $this->findById($id);
        if (! $request) {
            throw new \RuntimeException("Request with ID {$id} not found");
        }

        $request->update([
            'status' => 2, // 承認済み
            'approver_id' => $approverId,
            'decided_at' => CarbonImmutable::now(),
            'decision_note' => $decisionNote,
        ]);

        return $request->fresh();
    }

    /**
     * 申請を却下
     *
     * @param  int  $id  申請ID
     * @param  int  $approverId  承認者ID
     * @param  string|null  $decisionNote  却下理由
     */
    public function reject(int $id, int $approverId, ?string $decisionNote = null): Request
    {
        $request = $this->findById($id);
        if (! $request) {
            throw new \RuntimeException("Request with ID {$id} not found");
        }

        $request->update([
            'status' => 3, // 却下
            'approver_id' => $approverId,
            'decided_at' => CarbonImmutable::now(),
            'decision_note' => $decisionNote,
        ]);

        return $request->fresh();
    }

    /**
     * 承認済みかつ未反映の申請を取得
     *
     * @param  int|null  $limit  取得件数上限（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findApprovedAndNotApplied(?int $limit = null): Collection
    {
        $query = $this->request->query()
            ->where('status', 2) // 承認済み
            ->whereNull('applied_at')
            ->with(['company', 'requestedBy', 'approver', 'applicationType'])
            ->orderBy('decided_at', 'asc');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * 申請を反映済みに更新
     *
     * @param  int  $id  申請ID
     */
    public function markAsApplied(int $id): Request
    {
        $request = $this->findById($id);
        if (! $request) {
            throw new \RuntimeException("Request with ID {$id} not found");
        }

        $request->update([
            'applied_at' => CarbonImmutable::now(),
        ]);

        return $request->fresh();
    }
}

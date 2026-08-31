<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Request;
use Illuminate\Database\Eloquent\Collection;

/**
 * 申請リポジトリインターフェース
 */
interface RequestRepositoryInterface
{
    /**
     * IDで申請を取得
     *
     * @param  int  $id  申請ID
     */
    public function findById(int $id): ?Request;

    /**
     * 会社IDとステータスで申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByCompanyIdAndStatus(int $companyId, ?int $status = null): Collection;

    /**
     * 申請者IDで申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  申請者ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByRequestedBy(int $companyId, int $userId, ?int $status = null): Collection;

    /**
     * 承認者IDで申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $approverId  承認者ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByApproverId(int $companyId, int $approverId, ?int $status = null): Collection;

    /**
     * 対象日で申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @return Collection<int, Request>
     */
    public function findByTargetDate(int $companyId, string $targetDate): Collection;

    /**
     * 日付範囲で申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findByDateRange(int $companyId, string $startDate, string $endDate, ?int $status = null): Collection;

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
    public function findByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate, ?int $status = null): Collection;

    /**
     * ユーザーIDと対象日と申請タイプで申請が存在するか確認
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @param  int  $type  申請タイプID
     */
    public function existsByUserIdAndDateAndType(int $companyId, int $userId, string $targetDate, int $type): bool;

    /**
     * 申請を作成
     *
     * @param  array<string, mixed>  $data  申請データ
     */
    public function create(array $data): Request;

    /**
     * 申請を更新
     *
     * @param  int  $id  申請ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): Request;

    /**
     * 申請を削除
     *
     * @param  int  $id  申請ID
     */
    public function delete(int $id): bool;

    /**
     * 申請を承認
     *
     * @param  int  $id  申請ID
     * @param  int  $approverId  承認者ID
     * @param  string|null  $decisionNote  承認コメント
     */
    public function approve(int $id, int $approverId, ?string $decisionNote = null): Request;

    /**
     * 申請を却下
     *
     * @param  int  $id  申請ID
     * @param  int  $approverId  承認者ID
     * @param  string|null  $decisionNote  却下理由
     */
    public function reject(int $id, int $approverId, ?string $decisionNote = null): Request;

    /**
     * 承認済みかつ未反映の申請を取得
     *
     * @param  int|null  $limit  取得件数上限（nullの場合は全て）
     * @return Collection<int, Request>
     */
    public function findApprovedAndNotApplied(?int $limit = null): Collection;

    /**
     * 申請を反映済みに更新
     *
     * @param  int  $id  申請ID
     */
    public function markAsApplied(int $id): Request;
}

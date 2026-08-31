<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TimeRecordCorrectionRequest;
use Illuminate\Database\Eloquent\Collection;

/**
 * 打刻修正申請リポジトリインターフェース
 */
interface TimeRecordCorrectionRequestRepositoryInterface
{
    /**
     * IDで打刻修正申請を取得
     *
     * @param  int  $id  打刻修正申請ID
     */
    public function findById(int $id): ?TimeRecordCorrectionRequest;

    /**
     * 会社IDとステータスで打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByCompanyIdAndStatus(int $companyId, ?int $status = null): Collection;

    /**
     * ユーザーIDで打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByUserId(int $companyId, int $userId, ?int $status = null): Collection;

    /**
     * 承認者IDで打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $approverId  承認者ID
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByApproverId(int $companyId, int $approverId, ?int $status = null): Collection;

    /**
     * 対象日で打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日（Y-m-d形式）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByTargetDate(int $companyId, string $targetDate): Collection;

    /**
     * 日付範囲で打刻修正申請を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  int|null  $status  ステータス（nullの場合は全て）
     * @return Collection<int, TimeRecordCorrectionRequest>
     */
    public function findByDateRange(int $companyId, string $startDate, string $endDate, ?int $status = null): Collection;

    /**
     * 打刻修正申請を作成
     *
     * @param  array<string, mixed>  $data  打刻修正申請データ
     */
    public function create(array $data): TimeRecordCorrectionRequest;

    /**
     * 打刻修正申請を更新
     *
     * @param  int  $id  打刻修正申請ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): TimeRecordCorrectionRequest;

    /**
     * 打刻修正申請を削除
     *
     * @param  int  $id  打刻修正申請ID
     */
    public function delete(int $id): bool;

    /**
     * 打刻修正申請を承認
     *
     * @param  int  $id  打刻修正申請ID
     * @param  int  $approverId  承認者ID
     */
    public function approve(int $id, int $approverId): TimeRecordCorrectionRequest;

    /**
     * 打刻修正申請を却下
     *
     * @param  int  $id  打刻修正申請ID
     * @param  int  $approverId  承認者ID
     * @param  string  $rejectionReason  却下理由
     */
    public function reject(int $id, int $approverId, string $rejectionReason): TimeRecordCorrectionRequest;
}

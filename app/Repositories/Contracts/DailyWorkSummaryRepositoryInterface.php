<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DailyWorkSummary;
use Illuminate\Database\Eloquent\Collection;

/**
 * 勤務実績リポジトリインターフェース
 */
interface DailyWorkSummaryRepositoryInterface
{
    /**
     * IDで勤務実績を取得
     *
     * @param  int  $id  勤務実績ID
     */
    public function findById(int $id): ?DailyWorkSummary;

    /**
     * ユーザーIDと日付範囲で勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function findByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate): Collection;

    /**
     * 会社IDと日付範囲で勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function findByCompanyIdAndDateRange(int $companyId, string $startDate, string $endDate): Collection;

    /**
     * ユーザーIDと日付で勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $date  日付（Y-m-d形式）
     */
    public function findByUserIdAndDate(int $companyId, int $userId, string $date): ?DailyWorkSummary;

    /**
     * 複数のユーザーの日付範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function findByUserIdsAndDateRange(int $companyId, array $userIds, string $startDate, string $endDate): Collection;

    /**
     * 勤務実績を作成
     *
     * @param  array<string, mixed>  $data  勤務実績データ
     */
    public function create(array $data): DailyWorkSummary;

    /**
     * 勤務実績を更新
     *
     * @param  int  $id  勤務実績ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): DailyWorkSummary;

    /**
     * 勤務実績を削除
     *
     * @param  int  $id  勤務実績ID
     */
    public function delete(int $id): bool;

    /**
     * 勤務実績をupsert（存在すれば更新、なければ作成）
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $workDate  勤務日（Y-m-d形式）
     * @param  array<string, mixed>  $data  更新データ
     */
    public function upsert(int $companyId, int $userId, string $workDate, array $data): DailyWorkSummary;
}

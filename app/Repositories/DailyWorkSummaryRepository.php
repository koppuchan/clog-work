<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DailyWorkSummary;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * 勤務実績リポジトリ実装
 */
class DailyWorkSummaryRepository implements DailyWorkSummaryRepositoryInterface
{
    public function __construct(
        private readonly DailyWorkSummary $dailyWorkSummary
    ) {}

    /**
     * IDで勤務実績を取得
     *
     * @param  int  $id  勤務実績ID
     */
    public function findById(int $id): ?DailyWorkSummary
    {
        return $this->dailyWorkSummary->query()
            ->with(['user', 'company'])
            ->find($id);
    }

    /**
     * ユーザーIDと日付範囲で勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function findByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate): Collection
    {
        return $this->dailyWorkSummary->query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->orderBy('work_date')
            ->get();
    }

    /**
     * 会社IDと日付範囲で勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function findByCompanyIdAndDateRange(int $companyId, string $startDate, string $endDate): Collection
    {
        return $this->dailyWorkSummary->query()
            ->where('company_id', $companyId)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->with(['user'])
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get();
    }

    /**
     * ユーザーIDと日付で勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $date  日付（Y-m-d形式）
     */
    public function findByUserIdAndDate(int $companyId, int $userId, string $date): ?DailyWorkSummary
    {
        return $this->dailyWorkSummary->query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('work_date', $date)
            ->first();
    }

    /**
     * 複数のユーザーの日付範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, DailyWorkSummary>
     */
    public function findByUserIdsAndDateRange(int $companyId, array $userIds, string $startDate, string $endDate): Collection
    {
        return $this->dailyWorkSummary->query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $userIds)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->with(['user'])
            ->orderBy('user_id')
            ->orderBy('work_date')
            ->get();
    }

    /**
     * 勤務実績を作成
     *
     * @param  array<string, mixed>  $data  勤務実績データ
     */
    public function create(array $data): DailyWorkSummary
    {
        return $this->dailyWorkSummary->query()->create($data);
    }

    /**
     * 勤務実績を更新
     *
     * @param  int  $id  勤務実績ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): DailyWorkSummary
    {
        $dailyWorkSummary = $this->findById($id);
        if (! $dailyWorkSummary) {
            throw new \RuntimeException("DailyWorkSummary with ID {$id} not found");
        }

        $dailyWorkSummary->update($data);

        return $dailyWorkSummary->fresh();
    }

    /**
     * 勤務実績を削除
     *
     * @param  int  $id  勤務実績ID
     */
    public function delete(int $id): bool
    {
        $dailyWorkSummary = $this->findById($id);
        if (! $dailyWorkSummary) {
            return false;
        }

        return $dailyWorkSummary->delete();
    }

    /**
     * 勤務実績をupsert（存在すれば更新、なければ作成）
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $workDate  勤務日（Y-m-d形式）
     * @param  array<string, mixed>  $data  更新データ
     */
    public function upsert(int $companyId, int $userId, string $workDate, array $data): DailyWorkSummary
    {
        $existing = $this->findByUserIdAndDate($companyId, $userId, $workDate);

        if ($existing) {
            $existing->update($data);

            return $existing->fresh();
        }

        return $this->create(array_merge([
            'company_id' => $companyId,
            'user_id' => $userId,
            'work_date' => $workDate,
        ], $data));
    }
}

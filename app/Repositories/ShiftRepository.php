<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Shift;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * シフトリポジトリ実装
 */
class ShiftRepository implements ShiftRepositoryInterface
{
    public function __construct(
        private readonly Shift $shift
    ) {}

    /**
     * IDでシフトを取得
     *
     * @param  int  $id  シフトID
     */
    public function findById(int $id): ?Shift
    {
        return $this->shift->query()
            ->with(['user', 'shiftPattern'])
            ->find($id);
    }

    /**
     * 会社IDとユーザーIDと日付範囲でシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function findByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate): Collection
    {
        return $this->shift->query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->with(['shiftPattern.colors'])
            ->orderBy('shift_date')
            ->get();
    }

    /**
     * 会社IDと日付範囲でシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function findByCompanyIdAndDateRange(int $companyId, string $startDate, string $endDate): Collection
    {
        return $this->shift->query()
            ->where('company_id', $companyId)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->with(['user', 'shiftPattern.colors'])
            ->orderBy('shift_date')
            ->orderBy('user_id')
            ->get();
    }

    /**
     * 部署IDと日付範囲でシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $departmentId  部署ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function findByDepartmentIdAndDateRange(int $companyId, int $departmentId, string $startDate, string $endDate): Collection
    {
        return $this->shift->query()
            ->where('company_id', $companyId)
            ->whereHas('user.departments', function ($query) use ($departmentId) {
                $query->where('departments.id', $departmentId)
                    ->where('user_departments.is_primary', true);
            })
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->with(['user', 'shiftPattern.colors'])
            ->orderBy('shift_date')
            ->orderBy('user_id')
            ->get();
    }

    /**
     * シフトを作成
     *
     * @param  array<string, mixed>  $data  シフトデータ
     */
    public function create(array $data): Shift
    {
        return $this->shift->query()->create($data);
    }

    /**
     * シフトを更新
     *
     * @param  int  $id  シフトID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): Shift
    {
        $shift = $this->findById($id);
        if (! $shift) {
            throw new \RuntimeException("Shift with ID {$id} not found");
        }

        $shift->update($data);

        return $shift->fresh();
    }

    /**
     * シフトを削除
     *
     * @param  int  $id  シフトID
     */
    public function delete(int $id): bool
    {
        $shift = $this->findById($id);
        if (! $shift) {
            return false;
        }

        return $shift->delete();
    }

    /**
     * 複数のシフトを一括作成・更新
     *
     * @param  int  $companyId  会社ID
     * @param  array<int, array<string, mixed>>  $shifts  シフトデータの配列
     */
    public function upsertMany(int $companyId, array $shifts): void
    {
        $now = CarbonImmutable::now();

        $collection = collect($shifts);

        // shift_pattern_idがnull（休み）の行は既存レコードを一括削除
        $shiftsToDelete = $collection->filter(fn ($shift) => empty($shift['shift_pattern_id']));
        if ($shiftsToDelete->isNotEmpty()) {
            $this->shift->query()
                ->where('company_id', $companyId)
                ->where(function ($query) use ($shiftsToDelete): void {
                    foreach ($shiftsToDelete as $shift) {
                        $query->orWhere(function ($q) use ($shift): void {
                            $q->where('user_id', $shift['user_id'])
                                ->where('shift_date', $shift['shift_date']);
                        });
                    }
                })
                ->delete();
        }

        // shift_pattern_idがある行のみupsert
        $shiftsToUpsert = $collection
            ->filter(fn ($shift) => ! empty($shift['shift_pattern_id']))
            ->map(function ($shift) use ($companyId, $now) {
                return [
                    'company_id' => $companyId,
                    'user_id' => $shift['user_id'],
                    'shift_date' => $shift['shift_date'],
                    'shift_pattern_id' => $shift['shift_pattern_id'],
                    'note' => $shift['note'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->toArray();

        if (! empty($shiftsToUpsert)) {
            $this->shift->query()->upsert(
                $shiftsToUpsert,
                ['company_id', 'user_id', 'shift_date'], // unique keys
                ['shift_pattern_id', 'note', 'updated_at'] // columns to update
            );
        }
    }
}

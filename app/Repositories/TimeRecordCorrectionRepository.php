<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TimeRecordCorrection;
use App\Repositories\Contracts\TimeRecordCorrectionRepositoryInterface;

/**
 * 打刻修正履歴リポジトリ実装
 */
class TimeRecordCorrectionRepository implements TimeRecordCorrectionRepositoryInterface
{
    public function __construct(
        private readonly TimeRecordCorrection $timeRecordCorrection
    ) {}

    /**
     * 打刻修正履歴を作成
     *
     * @param  array<string, mixed>  $data  打刻修正履歴データ
     */
    public function create(array $data): TimeRecordCorrection
    {
        return $this->timeRecordCorrection->query()->create($data);
    }

    /**
     * 複数の打刻修正履歴を一括作成
     *
     * @param  array<int, array<string, mixed>>  $records  レコードデータの配列
     */
    public function insertMany(array $records): void
    {
        if (empty($records)) {
            return;
        }

        $this->timeRecordCorrection->query()->insert($records);
    }

    /**
     * ユーザーIDと日付範囲で打刻修正履歴を取得
     *
     * @param  int  $userId  ユーザーID
     * @param  string  $startDateTime  開始日時
     * @param  string  $endDateTime  終了日時
     * @return \Illuminate\Database\Eloquent\Collection<int, TimeRecordCorrection>
     */
    public function findByUserIdAndDateRange(int $userId, string $startDateTime, string $endDateTime): \Illuminate\Database\Eloquent\Collection
    {
        return $this->timeRecordCorrection->query()
            ->whereHas('timeRecord', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->whereBetween('before_record_time', [$startDateTime, $endDateTime])
            ->with(['timeRecord:id,record_time,user_id'])
            ->get();
    }
}

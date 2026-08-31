<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TimeRecordCorrection;

/**
 * 打刻修正履歴リポジトリインターフェース
 */
interface TimeRecordCorrectionRepositoryInterface
{
    /**
     * 打刻修正履歴を作成
     *
     * @param  array<string, mixed>  $data  打刻修正履歴データ
     */
    public function create(array $data): TimeRecordCorrection;

    /**
     * 複数の打刻修正履歴を一括作成
     *
     * @param  array<int, array<string, mixed>>  $records  レコードデータの配列
     */
    public function insertMany(array $records): void;

    /**
     * ユーザーIDと日付範囲で打刻修正履歴を取得
     *
     * @param  int  $userId  ユーザーID
     * @param  string  $startDateTime  開始日時
     * @param  string  $endDateTime  終了日時
     * @return \Illuminate\Database\Eloquent\Collection<int, TimeRecordCorrection>
     */
    public function findByUserIdAndDateRange(int $userId, string $startDateTime, string $endDateTime): \Illuminate\Database\Eloquent\Collection;
}

<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\TimeRecordTypeEnum;
use App\Models\TimeRecord;
use Illuminate\Database\Eloquent\Collection;

/**
 * 勤務実績リポジトリインターフェース
 */
interface TimeRecordRepositoryInterface
{
    /**
     * IDで勤務実績を取得
     *
     * @param  int  $id  勤務実績ID
     */
    public function findById(int $id): ?TimeRecord;

    /**
     * 複数のIDで勤務実績を取得
     *
     * @param  array<int>  $ids  勤務実績IDの配列
     * @return Collection<int, TimeRecord>
     */
    public function findByIds(array $ids): Collection;

    /**
     * 会社IDとユーザーIDと日時範囲で勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDateTime  開始日時（Y-m-d H:i:s形式）
     * @param  string  $endDateTime  終了日時（Y-m-d H:i:s形式）
     * @return Collection<int, TimeRecord>
     */
    public function findByUserIdAndDateTimeRange(int $companyId, int $userId, string $startDateTime, string $endDateTime): Collection;

    /**
     * 会社IDと日時範囲で勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDateTime  開始日時（Y-m-d H:i:s形式）
     * @param  string  $endDateTime  終了日時（Y-m-d H:i:s形式）
     * @return Collection<int, TimeRecord>
     */
    public function findByCompanyIdAndDateTimeRange(int $companyId, string $startDateTime, string $endDateTime): Collection;

    /**
     * ユーザーの最新の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  TimeRecordTypeEnum|null  $recordType  打刻種別（指定時はその種別のみ）
     */
    public function findLatestByUserId(int $companyId, int $userId, ?TimeRecordTypeEnum $recordType = null): ?TimeRecord;

    /**
     * ユーザーの当日の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $date  日付（Y-m-d形式）
     * @return Collection<int, TimeRecord>
     */
    public function findByUserIdAndDate(int $companyId, int $userId, string $date): Collection;

    /**
     * 勤務実績を作成
     *
     * @param  array<string, mixed>  $data  勤務実績データ
     */
    public function create(array $data): TimeRecord;

    /**
     * 勤務実績を更新
     *
     * @param  int  $id  勤務実績ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): TimeRecord;

    /**
     * 勤務実績を削除
     *
     * @param  int  $id  勤務実績ID
     */
    public function delete(int $id): bool;

    /**
     * 複数の勤務実績を一括削除
     *
     * @param  array<int>  $ids  勤務実績IDの配列
     * @return int 削除された件数
     */
    public function deleteByIds(array $ids): int;

    /**
     * 複数の打刻レコードをupsert
     *
     * @param  array<int, array<string, mixed>>  $records  レコードデータの配列
     * @param  array<string>  $uniqueBy  ユニークキーのカラム名
     * @param  array<string>  $update  更新対象のカラム名
     */
    public function upsertMany(array $records, array $uniqueBy, array $update): void;

    /**
     * 指定ユーザー・日付・レコードタイプの打刻を削除
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $date  対象日（Y-m-d形式）
     * @param  array<int>  $recordTypes  レコードタイプの値の配列
     * @return int 削除された件数
     */
    public function deleteByUserDateAndTypes(int $companyId, int $userId, string $date, array $recordTypes): int;

    /**
     * 複数のユーザーの日付範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, TimeRecord>
     */
    public function findByUserIdsAndDateRange(int $companyId, array $userIds, string $startDate, string $endDate): Collection;
}

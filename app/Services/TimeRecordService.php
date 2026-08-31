<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCodeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\TimeRecord;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use App\Traits\HasLogService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 勤務実績サービス
 */
class TimeRecordService
{
    use HasLogService;

    public function __construct(
        private readonly TimeRecordRepositoryInterface $timeRecordRepository,
        private readonly TimeRoundingService $timeRoundingService
    ) {}

    /**
     * IDで勤務実績を取得
     *
     * @param  int  $id  勤務実績ID
     *
     * @throws NotFoundException 勤務実績が見つからない場合
     */
    public function findById(int $id): TimeRecord
    {
        $timeRecord = $this->timeRecordRepository->findById($id);

        if (! $timeRecord) {
            throw new NotFoundException(ErrorCodeEnum::ATTENDANCE_NOT_FOUND);
        }

        return $timeRecord;
    }

    /**
     * ユーザーの日時範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDateTime  開始日時（Y-m-d H:i:s形式）
     * @param  string  $endDateTime  終了日時（Y-m-d H:i:s形式）
     * @return Collection<int, TimeRecord>
     */
    public function getTimeRecordsByUserIdAndDateTimeRange(int $companyId, int $userId, string $startDateTime, string $endDateTime): Collection
    {
        return $this->timeRecordRepository->findByUserIdAndDateTimeRange($companyId, $userId, $startDateTime, $endDateTime);
    }

    /**
     * 会社全体の日時範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDateTime  開始日時（Y-m-d H:i:s形式）
     * @param  string  $endDateTime  終了日時（Y-m-d H:i:s形式）
     * @return Collection<int, TimeRecord>
     */
    public function getTimeRecordsByCompanyIdAndDateTimeRange(int $companyId, string $startDateTime, string $endDateTime): Collection
    {
        return $this->timeRecordRepository->findByCompanyIdAndDateTimeRange($companyId, $startDateTime, $endDateTime);
    }

    /**
     * ユーザーの最新の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  TimeRecordTypeEnum|null  $recordType  打刻種別（指定時はその種別のみ）
     */
    public function getLatestTimeRecord(int $companyId, int $userId, ?TimeRecordTypeEnum $recordType = null): ?TimeRecord
    {
        return $this->timeRecordRepository->findLatestByUserId($companyId, $userId, $recordType);
    }

    /**
     * ユーザーの当日の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string|null  $date  日付（Y-m-d形式、nullの場合は当日）
     * @return Collection<int, TimeRecord>
     */
    public function getTodayTimeRecords(int $companyId, int $userId, ?string $date = null): Collection
    {
        $targetDate = $date ?? CarbonImmutable::now()->format('Y-m-d');

        return $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $targetDate);
    }

    /**
     * 打刻を記録
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  TimeRecordTypeEnum  $recordType  打刻種別
     * @param  CarbonImmutable|null  $recordTime  打刻時刻（nullの場合は現在時刻）
     * @param  RecordSourceEnum  $recordSource  記録元
     * @param  string|null  $note  備考
     * @return TimeRecord 作成された勤務実績
     *
     * @throws BusinessException ビジネスルール違反時
     */
    public function recordTime(
        int $companyId,
        int $userId,
        TimeRecordTypeEnum $recordType,
        ?CarbonImmutable $recordTime = null,
        RecordSourceEnum $recordSource = RecordSourceEnum::AUTO,
        ?string $note = null
    ): TimeRecord {
        try {
            return DB::transaction(function () use ($companyId, $userId, $recordType, $recordTime, $recordSource, $note): TimeRecord {
                $now = $recordTime ?? CarbonImmutable::now();

                // ビジネスルールのバリデーション
                $this->validateTimeRecordRule($companyId, $userId, $recordType, $now);

                $data = [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'record_type' => $recordType,
                    'record_time' => $now,
                    'rounded_time' => $this->timeRoundingService->roundTime($companyId, $now, $recordType),
                    'record_source' => $recordSource,
                    'note' => $note,
                ];

                return $this->timeRecordRepository->create($data);
            });
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::ATTENDANCE_RECORD_FAILED, [
                'company_id' => $companyId,
                'user_id' => $userId,
                'record_type' => $recordType->value,
            ], $e);

            throw new BusinessException(ErrorCodeEnum::ATTENDANCE_RECORD_FAILED);
        }
    }

    /**
     * 勤務実績を更新
     *
     * @param  int  $id  勤務実績ID
     * @param  array<string, mixed>  $data  更新データ
     * @return TimeRecord 更新された勤務実績
     *
     * @throws NotFoundException 勤務実績が見つからない場合
     * @throws BusinessException ビジネスルール違反時
     */
    public function update(int $id, array $data): TimeRecord
    {
        try {
            $timeRecord = $this->findById($id);

            // 申請修正による記録は編集不可
            if ($timeRecord->record_source === RecordSourceEnum::REQUEST) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_CANNOT_EDIT_REQUEST);
            }

            return DB::transaction(function () use ($id, $data): TimeRecord {
                return $this->timeRecordRepository->update($id, $data);
            });
        } catch (NotFoundException|BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::ATTENDANCE_UPDATE_FAILED, [
                'time_record_id' => $id,
                'data' => $data,
            ], $e);

            throw new BusinessException(ErrorCodeEnum::ATTENDANCE_UPDATE_FAILED);
        }
    }

    /**
     * 勤務実績を削除
     *
     * @param  int  $id  勤務実績ID
     *
     * @throws NotFoundException 勤務実績が見つからない場合
     * @throws BusinessException 削除できない状態の場合
     */
    public function delete(int $id): void
    {
        try {
            $timeRecord = $this->findById($id);

            // 申請修正による記録は削除不可
            if ($timeRecord->record_source === RecordSourceEnum::REQUEST) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_CANNOT_EDIT_REQUEST);
            }

            $deleted = $this->timeRecordRepository->delete($id);

            if (! $deleted) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_DELETE_FAILED);
            }
        } catch (NotFoundException|BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::ATTENDANCE_DELETE_FAILED, [
                'time_record_id' => $id,
            ], $e);

            throw new BusinessException(ErrorCodeEnum::ATTENDANCE_DELETE_FAILED);
        }
    }

    /**
     * 複数ユーザーの日付範囲の勤務実績を取得
     *
     * @param  int  $companyId  会社ID
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, TimeRecord>
     */
    public function getTimeRecordsByUserIdsAndDateRange(int $companyId, array $userIds, string $startDate, string $endDate): Collection
    {
        if (empty($userIds)) {
            return new Collection;
        }

        return $this->timeRecordRepository->findByUserIdsAndDateRange($companyId, $userIds, $startDate, $endDate);
    }

    /**
     * ユーザーの現在の勤務状態を取得
     *
     * 「当日のレコードのみを見る」方式だと、日跨ぎの退勤忘れを後追い追加した
     * 際に最終レコードが今日のWORK_STARTになり退勤済みと判定できないため、
     * 最新のWORK_START以降のシーケンスを直接見る。
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @return array{is_working: bool, is_on_break: bool, last_record: TimeRecord|null}
     */
    public function getCurrentWorkStatus(int $companyId, int $userId): array
    {
        $latestWorkStart = $this->timeRecordRepository->findLatestByUserId(
            $companyId,
            $userId,
            TimeRecordTypeEnum::WORK_START
        );

        if ($latestWorkStart === null) {
            return ['is_working' => false, 'is_on_break' => false, 'last_record' => null];
        }

        $sessionRecords = $this->timeRecordRepository->findByUserIdAndDateTimeRange(
            $companyId,
            $userId,
            $latestWorkStart->record_time->format('Y-m-d H:i:s'),
            CarbonImmutable::parse($latestWorkStart->record_time)->addDays(2)->format('Y-m-d H:i:s')
        );

        $isWorking = false;
        $isOnBreak = false;
        $lastRecord = null;

        foreach ($sessionRecords as $record) {
            $lastRecord = $record;

            if ($record->record_type->isWorkStart()) {
                $isWorking = true;
                $isOnBreak = false;
            } elseif ($record->record_type->isWorkEnd()) {
                $isWorking = false;
                $isOnBreak = false;
            } elseif ($record->record_type->isBreakStart()) {
                $isOnBreak = true;
            } elseif ($record->record_type->isBreakEnd()) {
                $isOnBreak = false;
            }
        }

        return [
            'is_working' => $isWorking,
            'is_on_break' => $isOnBreak,
            'last_record' => $lastRecord,
        ];
    }

    /**
     * 打刻ルールのバリデーション
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  TimeRecordTypeEnum  $recordType  打刻種別
     * @param  CarbonImmutable  $recordTime  打刻時刻
     *
     * @throws BusinessException ビジネスルール違反時
     */
    private function validateTimeRecordRule(int $companyId, int $userId, TimeRecordTypeEnum $recordType, CarbonImmutable $recordTime): void
    {
        $status = $this->getCurrentWorkStatus($companyId, $userId);

        // 勤務開始の場合
        if ($recordType->isWorkStart()) {
            if ($status['is_working']) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_ALREADY_CLOCKED_IN);
            }
        }

        // 勤務終了の場合
        if ($recordType->isWorkEnd()) {
            if (! $status['is_working']) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_NOT_CLOCKED_IN);
            }
            if ($status['is_on_break']) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_ON_BREAK);
            }
        }

        // 休憩開始の場合
        if ($recordType->isBreakStart()) {
            if (! $status['is_working']) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_NOT_CLOCKED_IN);
            }
            if ($status['is_on_break']) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_ON_BREAK);
            }
        }

        // 休憩終了の場合
        if ($recordType->isBreakEnd()) {
            if (! $status['is_on_break']) {
                throw new BusinessException(ErrorCodeEnum::ATTENDANCE_NOT_ON_BREAK);
            }
        }
    }
}

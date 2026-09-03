<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Exceptions\BusinessException;
use App\Models\TimeRecord;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 打刻サービス
 *
 * スタッフ画面での打刻機能のビジネスロジックを担当
 */
class StampService
{
    private const MAX_BREAK_COUNT_PER_DAY = 2;

    public function __construct(
        private readonly TimeRecordRepositoryInterface $timeRecordRepository,
        private readonly TimeRoundingService $timeRoundingService,
        private readonly DailyWorkSummaryBatchService $dailyWorkSummaryBatchService,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * 出勤打刻を行う
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  CarbonImmutable|null  $recordTime  打刻時刻（指定がない場合は現在時刻）
     * @return TimeRecord 作成された打刻レコード
     *
     * @throws BusinessException 既に出勤中の場合
     */
    public function clockIn(int $companyId, int $userId, ?CarbonImmutable $recordTime = null): TimeRecord
    {
        return DB::transaction(function () use ($companyId, $userId, $recordTime): TimeRecord {
            $recordTime = $recordTime ?? CarbonImmutable::now();

            // ビジネスルールチェック: 既に出勤中でないか
            if ($this->isWorking($companyId, $userId)) {
                throw new BusinessException('既に出勤中です。先に退勤打刻を行ってください。');
            }

            // ビジネスルールチェック: 本日既に出勤打刻済みでないか
            if ($this->hasClockInToday($companyId, $userId, $recordTime)) {
                throw new BusinessException('本日は既に出勤打刻済みです。打刻時間を変更する場合は申請してください。');
            }

            return $this->timeRecordRepository->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'record_type' => TimeRecordTypeEnum::WORK_START,
                'record_time' => $recordTime,
                'rounded_time' => $this->timeRoundingService->roundTime($companyId, $recordTime, TimeRecordTypeEnum::WORK_START),
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        });
    }

    /**
     * 退勤打刻を行う
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  CarbonImmutable|null  $recordTime  打刻時刻（指定がない場合は現在時刻）
     * @return TimeRecord 作成された打刻レコード
     *
     * @throws BusinessException 出勤していない場合、または休憩中の場合
     */
    public function clockOut(int $companyId, int $userId, ?CarbonImmutable $recordTime = null): TimeRecord
    {
        $record = DB::transaction(function () use ($companyId, $userId, $recordTime): TimeRecord {
            $recordTime = $recordTime ?? CarbonImmutable::now();

            // ビジネスルールチェック: 出勤中か
            if (! $this->isWorking($companyId, $userId)) {
                throw new BusinessException('出勤していません。先に出勤打刻を行ってください。');
            }

            // ビジネスルールチェック: 休憩中でないか
            if ($this->isOnBreak($companyId, $userId)) {
                throw new BusinessException('休憩中は退勤できません。先に休憩終了打刻を行ってください。');
            }

            // ビジネスルールチェック: 本日既に退勤打刻済みでないか
            if ($this->hasClockOutToday($companyId, $userId, $recordTime)) {
                throw new BusinessException('本日は既に退勤打刻済みです。打刻時間を変更する場合は申請してください。');
            }

            // 日付越えチェック
            $lastClockIn = $this->timeRecordRepository->findLatestByUserId(
                $companyId,
                $userId,
                TimeRecordTypeEnum::WORK_START
            );

            $recordType = TimeRecordTypeEnum::WORK_END;
            if ($lastClockIn && $lastClockIn->record_time->toDateString() !== $recordTime->toDateString()) {
                $recordType = TimeRecordTypeEnum::WORK_END_NEXT_DAY;
            }

            return $this->timeRecordRepository->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'record_type' => $recordType,
                'record_time' => $recordTime,
                'rounded_time' => $this->timeRoundingService->roundTime($companyId, $recordTime, $recordType),
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        });

        // 退勤打刻後に勤務実績を即時集計
        $company = $this->companyRepository->findById($companyId);
        $user = $this->userRepository->findById($userId);
        // 日付越え退勤の場合は出勤日（前日）で集計
        $targetDate = $record->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
            ? CarbonImmutable::parse($record->record_time)->subDay()
            : CarbonImmutable::parse($record->record_time);
        $this->dailyWorkSummaryBatchService->aggregateByUser($company, $user, $targetDate->startOfDay());

        return $record;
    }

    /**
     * 休憩開始打刻を行う
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  CarbonImmutable|null  $recordTime  打刻時刻（指定がない場合は現在時刻）
     * @return TimeRecord 作成された打刻レコード
     *
     * @throws BusinessException 出勤していない場合、または既に休憩中の場合
     */
    public function breakStart(int $companyId, int $userId, ?CarbonImmutable $recordTime = null): TimeRecord
    {
        return DB::transaction(function () use ($companyId, $userId, $recordTime): TimeRecord {
            $recordTime = $recordTime ?? CarbonImmutable::now();

            // ビジネスルールチェック: 出勤中か
            if (! $this->isWorking($companyId, $userId)) {
                throw new BusinessException('出勤していません。先に出勤打刻を行ってください。');
            }

            // ビジネスルールチェック: 既に休憩中でないか
            if ($this->isOnBreak($companyId, $userId)) {
                throw new BusinessException('既に休憩中です。先に休憩終了打刻を行ってください。');
            }

            // ビジネスルールチェック: 休憩回数上限（1日2回まで）
            $todayRecords = $this->timeRecordRepository->findByUserIdAndDate(
                $companyId,
                $userId,
                $recordTime->format('Y-m-d')
            );
            $breakCount = $todayRecords->filter(
                fn (TimeRecord $r) => $r->record_type === TimeRecordTypeEnum::BREAK_START
            )->count();
            if ($breakCount >= self::MAX_BREAK_COUNT_PER_DAY) {
                throw new BusinessException('休憩は1日2回までです。');
            }

            return $this->timeRecordRepository->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'record_type' => TimeRecordTypeEnum::BREAK_START,
                'record_time' => $recordTime,
                'rounded_time' => $this->timeRoundingService->roundTime($companyId, $recordTime, TimeRecordTypeEnum::BREAK_START),
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        });
    }

    /**
     * 休憩終了打刻を行う
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  CarbonImmutable|null  $recordTime  打刻時刻（指定がない場合は現在時刻）
     * @return TimeRecord 作成された打刻レコード
     *
     * @throws BusinessException 休憩中でない場合
     */
    public function breakEnd(int $companyId, int $userId, ?CarbonImmutable $recordTime = null): TimeRecord
    {
        return DB::transaction(function () use ($companyId, $userId, $recordTime): TimeRecord {
            $recordTime = $recordTime ?? CarbonImmutable::now();

            // ビジネスルールチェック: 休憩中か
            if (! $this->isOnBreak($companyId, $userId)) {
                throw new BusinessException('休憩中ではありません。');
            }

            return $this->timeRecordRepository->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'record_type' => TimeRecordTypeEnum::BREAK_END,
                'record_time' => $recordTime,
                'rounded_time' => $this->timeRoundingService->roundTime($companyId, $recordTime, TimeRecordTypeEnum::BREAK_END),
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        });
    }

    /**
     * ユーザーの直近の打刻レコードを取得する
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     */
    public function findLatestRecord(int $companyId, int $userId): ?TimeRecord
    {
        return $this->timeRecordRepository->findLatestByUserId($companyId, $userId);
    }

    /**
     * 現在の勤務状態を取得
     *
     * isWorking/isOnBreak は最新WORK_STARTからの打刻シーケンスで判定する
     * （日跨ぎ退勤忘れを後追い追加されたケースに対応するため）。
     * hasClockInToday/hasClockOutToday は「当日の」UI制限用なので当日基準のまま。
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @return array{isWorking: bool, isOnBreak: bool, clockInTime: string|null, breakCount: int, hasClockInToday: bool, hasClockOutToday: bool}
     */
    public function getCurrentStatus(int $companyId, int $userId): array
    {
        $now = CarbonImmutable::now();
        $today = $now->format('Y-m-d');

        $session = $this->getCurrentWorkSession($companyId, $userId);
        $isWorking = $session['isWorking'];
        $isOnBreak = $session['isOnBreak'];
        $sessionStart = $session['sessionStart'];

        // 当日の打刻レコード（hasClockInToday/hasClockOutToday の判定用）
        $todayRecords = $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $today);

        $hasClockInToday = $todayRecords->contains(
            fn (TimeRecord $record) => $record->record_type === TimeRecordTypeEnum::WORK_START
        );
        $hasClockOutToday = $todayRecords->contains(
            fn (TimeRecord $record) => $record->record_type === TimeRecordTypeEnum::WORK_END
                || $record->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
        );

        // 出勤時刻と休憩回数: 現在のセッションのWORK_STARTとそれ以降のBREAK_STARTから算出
        $clockInTime = null;
        $breakCount = 0;
        if ($isWorking && $sessionStart !== null) {
            $clockInTime = $sessionStart->record_time->format('H:i');

            $sessionRecords = $this->timeRecordRepository->findByUserIdAndDateTimeRange(
                $companyId,
                $userId,
                $sessionStart->record_time->format('Y-m-d H:i:s'),
                CarbonImmutable::parse($sessionStart->record_time)->addDays(2)->format('Y-m-d H:i:s')
            );
            $breakCount = $sessionRecords->filter(
                fn (TimeRecord $record) => $record->record_type === TimeRecordTypeEnum::BREAK_START
            )->count();
        }

        return [
            'isWorking' => $isWorking,
            'isOnBreak' => $isOnBreak,
            'clockInTime' => $clockInTime,
            'breakCount' => $breakCount,
            'hasClockInToday' => $hasClockInToday,
            'hasClockOutToday' => $hasClockOutToday,
        ];
    }

    /**
     * 本日の打刻履歴を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string|null  $date  日付（Y-m-d形式）、指定がない場合は当日
     * @return Collection<int, array{id: int, type: string, typeLabel: string, time: string, source: string}>
     */
    public function getTodayRecords(int $companyId, int $userId, ?string $date = null): Collection
    {
        $date = $date ?? CarbonImmutable::now()->format('Y-m-d');

        $records = $this->timeRecordRepository->findByUserIdAndDate($companyId, $userId, $date);

        return $records->map(fn (TimeRecord $record) => [
            'id' => $record->id,
            'type' => $record->record_type->name,
            'typeLabel' => $record->record_type->label(),
            'time' => $record->record_time->format('H:i'),
            'source' => $record->record_source->label(),
        ]);
    }

    /**
     * 現在の勤務セッションを取得（最新のWORK_START以降のシーケンス基準）
     *
     * 「当日のレコードのみを見る」方式だと、日跨ぎの退勤忘れを後から
     * 追加した場合（昨日のWORK_ENDが昨日日付で作られる）に当日の最終
     * レコードが今日のWORK_STARTのままになり、退勤済みと判定できない。
     * このため最新の WORK_START 以降のレコードシーケンスを直接見る。
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @return array{isWorking: bool, isOnBreak: bool, sessionStart: TimeRecord|null}
     */
    private function getCurrentWorkSession(int $companyId, int $userId): array
    {
        $latestWorkStart = $this->timeRecordRepository->findLatestByUserId(
            $companyId,
            $userId,
            TimeRecordTypeEnum::WORK_START
        );

        if ($latestWorkStart === null) {
            return ['isWorking' => false, 'isOnBreak' => false, 'sessionStart' => null];
        }

        // 最新の出勤打刻 以降 のレコードを取得（数日先まで含めて広めに）
        $sessionRecords = $this->timeRecordRepository->findByUserIdAndDateTimeRange(
            $companyId,
            $userId,
            $latestWorkStart->record_time->format('Y-m-d H:i:s'),
            CarbonImmutable::parse($latestWorkStart->record_time)->addDays(2)->format('Y-m-d H:i:s')
        );

        // セッションの最終状態を順に判定
        $isWorking = false;
        $isOnBreak = false;

        foreach ($sessionRecords as $record) {
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

        // 出勤から一定時間を超えたセッションは、退勤し忘れたものとして打ち切る。
        // 打ち切らないと、数日後の打刻が古い出勤に対する退勤として記録される。
        if ($isWorking && $this->isSessionExpired($latestWorkStart->record_time)) {
            $isWorking = false;
            $isOnBreak = false;
        }

        return [
            'isWorking' => $isWorking,
            'isOnBreak' => $isOnBreak,
            'sessionStart' => $isWorking ? $latestWorkStart : null,
        ];
    }

    /**
     * 勤務セッションが継続とみなせる時間を超えているか
     *
     * @param  \DateTimeInterface  $workStart  出勤打刻の時刻
     */
    private function isSessionExpired(\DateTimeInterface $workStart): bool
    {
        $maxHours = (int) config('attendance.work_session_max_hours', 24);

        if ($maxHours <= 0) {
            return false;
        }

        return CarbonImmutable::parse($workStart)->addHours($maxHours)->isPast();
    }

    /**
     * 出勤中かどうかを判定
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     */
    private function isWorking(int $companyId, int $userId): bool
    {
        return $this->getCurrentWorkSession($companyId, $userId)['isWorking'];
    }

    /**
     * 休憩中かどうかを判定
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     */
    private function isOnBreak(int $companyId, int $userId): bool
    {
        return $this->getCurrentWorkSession($companyId, $userId)['isOnBreak'];
    }

    /**
     * 本日既に出勤打刻済みかどうかを判定
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  CarbonImmutable  $recordTime  打刻時刻
     */
    private function hasClockInToday(int $companyId, int $userId, CarbonImmutable $recordTime): bool
    {
        $todayRecords = $this->timeRecordRepository->findByUserIdAndDate(
            $companyId,
            $userId,
            $recordTime->format('Y-m-d')
        );

        return $todayRecords->contains(
            fn (TimeRecord $record) => $record->record_type === TimeRecordTypeEnum::WORK_START
        );
    }

    /**
     * 本日既に退勤打刻済みかどうかを判定
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  CarbonImmutable  $recordTime  打刻時刻
     */
    private function hasClockOutToday(int $companyId, int $userId, CarbonImmutable $recordTime): bool
    {
        $todayRecords = $this->timeRecordRepository->findByUserIdAndDate(
            $companyId,
            $userId,
            $recordTime->format('Y-m-d')
        );

        return $todayRecords->contains(
            fn (TimeRecord $record) => $record->record_type === TimeRecordTypeEnum::WORK_END
                || $record->record_type === TimeRecordTypeEnum::WORK_END_NEXT_DAY
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCodeEnum;
use App\Enums\RecordSourceEnum;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\HasLogService;
use Carbon\CarbonImmutable;

/**
 * シフトの変更に合わせて、計算済みの勤務実績を再集計する
 *
 * 勤務実績（daily_work_summaries）の労働時間・時間外・休憩の自動控除などは、
 * 集計した時点のシフト（時刻・休憩）で計算した結果を保存している。そのため、
 * 後からシフトパターンの時刻・休憩を変えたり、日ごとのシフトを入れ替えたり
 * しても、すでに集計済みの日は古い計算のまま残っていた
 * （クライアント報告: 時間の計算が違う。シフト時刻を後から変更したためでは
 * ないか。例: 休憩を12:00〜13:00から12:00〜13:20に変えた後も、変更前に
 * 集計した日は休憩が60分のまま労働時間が20分長く出ていた）。
 *
 * 再集計はDailyWorkSummaryBatchService::aggregateByUser()（打刻のたびに
 * 動くものと同じ処理）を使う。次の日は対象にしない。
 * - 手動修正・申請修正の勤務実績（管理者が内容を確定させたもの）
 * - 出勤打刻の無い勤務実績（休暇だけの日など）
 */
class DailyWorkSummaryRecalculationService
{
    use HasLogService;

    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository,
        private readonly DailyWorkSummaryBatchService $dailyWorkSummaryBatchService,
    ) {}

    /**
     * 指定したシフトパターンが割り当てられている日の勤務実績を再集計する
     *
     * @return int 再集計した件数
     */
    public function recalculateForShiftPattern(int $companyId, int $shiftPatternId): int
    {
        $targets = $this->shiftRepository
            ->findByShiftPatternId($companyId, $shiftPatternId)
            ->map(fn ($shift) => [
                'user_id' => (int) $shift->user_id,
                'shift_date' => $shift->shift_date->format('Y-m-d'),
            ])
            ->all();

        return $this->recalculateForUserDates($companyId, $targets);
    }

    /**
     * 指定したユーザー・日付の勤務実績を再集計する
     *
     * 1件の失敗で残りの再集計を止めないよう、失敗はログに残して続ける。
     *
     * @param  array<int, array{user_id: int, shift_date: string}>  $userDates
     * @return int 再集計した件数
     */
    public function recalculateForUserDates(int $companyId, array $userDates): int
    {
        if ($userDates === []) {
            return 0;
        }

        // 件数が多いと通常のリクエストの実行時間では足りないことがある
        set_time_limit(600);

        $company = $this->companyRepository->findById($companyId);
        if ($company === null) {
            return 0;
        }

        $users = [];
        $recalculated = 0;

        foreach ($userDates as $target) {
            try {
                $summary = $this->dailyWorkSummaryRepository->findByUserIdAndDate(
                    $companyId,
                    $target['user_id'],
                    $target['shift_date']
                );

                if (
                    $summary === null
                    || $summary->record_source !== RecordSourceEnum::AUTO
                    || $summary->work_start === null
                ) {
                    continue;
                }

                $users[$target['user_id']] ??= $this->userRepository->findById($target['user_id']);
                $user = $users[$target['user_id']];
                if ($user === null) {
                    continue;
                }

                $this->dailyWorkSummaryBatchService->aggregateByUser(
                    $company,
                    $user,
                    CarbonImmutable::parse($target['shift_date'])
                );
                $recalculated++;
            } catch (\Throwable $e) {
                $this->logError(ErrorCodeEnum::ATTENDANCE_BATCH_AGGREGATE_FAILED, [
                    'company_id' => $companyId,
                    'user_id' => $target['user_id'],
                    'work_date' => $target['shift_date'],
                ], $e);
            }
        }

        return $recalculated;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaveTypeEnum;
use App\Models\DailyWorkSummary;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;

/**
 * 承認された休暇をシフト表に反映する
 *
 * シフトの人数を数えるときに勤務実績と突き合わせる手間をなくすため、
 * 承認済みの有給・特別休暇・欠勤をシフト表のセルに表示し、
 * 出勤予定人数からも差し引く。
 *
 * 人数の数え方は次のとおり。
 *   全日の休暇（有給・特別休暇・欠勤） -1人
 *   半日有給                           -0.5人
 *   時間有給                           シフト所定労働時間に対する按分
 *                                      （8時間のシフトで2時間取得なら -0.25人）
 *
 * 部署別の集計には効かせず、全体の合計のみを対象とする。
 */
class ShiftLeaveService
{
    private const DEFAULT_DAILY_WORKING_MINUTES = 480;

    /** 半日として扱う誤差の範囲（分） */
    private const HALF_DAY_TOLERANCE_MINUTES = 30;

    public function __construct(
        private readonly DailyWorkSummaryRepositoryInterface $dailyWorkSummaryRepository
    ) {}

    /**
     * 期間内の承認済み休暇をシフト表用にまとめる
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{
     *     user_id: int,
     *     date: string,
     *     leave_type: int,
     *     label: string,
     *     is_full_day: bool,
     *     deduction: float,
     *     background_color: string,
     *     text_color: string
     * }>
     */
    public function getLeavesForShift(
        int $companyId,
        array $userIds,
        string $startDate,
        string $endDate,
        int $dailyWorkingMinutes = self::DEFAULT_DAILY_WORKING_MINUTES,
    ): array {
        if ($userIds === []) {
            return [];
        }

        $summaries = $this->dailyWorkSummaryRepository->findByUserIdsAndDateRange(
            $companyId,
            $userIds,
            $startDate,
            $endDate,
        );

        return $summaries
            ->filter(fn (DailyWorkSummary $summary) => $summary->leave_type !== null)
            ->map(fn (DailyWorkSummary $summary) => $this->formatLeave($summary, $dailyWorkingMinutes))
            ->values()
            ->all();
    }

    /**
     * 出勤予定人数から差し引く人数
     *
     * 全日なら1人、半日なら0.5人、時間有給なら所定労働時間に対する割合。
     */
    public function deductionFor(?int $leaveMinutes, int $dailyWorkingMinutes = self::DEFAULT_DAILY_WORKING_MINUTES): float
    {
        // 時間の指定がなければ全日の休暇
        if ($leaveMinutes === null) {
            return 1.0;
        }

        $workingMinutes = $dailyWorkingMinutes > 0 ? $dailyWorkingMinutes : self::DEFAULT_DAILY_WORKING_MINUTES;

        if ($leaveMinutes >= $workingMinutes) {
            return 1.0;
        }

        if (abs($leaveMinutes - ($workingMinutes / 2)) <= self::HALF_DAY_TOLERANCE_MINUTES) {
            return 0.5;
        }

        return round($leaveMinutes / $workingMinutes, 2);
    }

    /**
     * @return array{
     *     user_id: int,
     *     date: string,
     *     leave_type: int,
     *     label: string,
     *     is_full_day: bool,
     *     deduction: float,
     *     background_color: string,
     *     text_color: string
     * }
     */
    private function formatLeave(DailyWorkSummary $summary, int $dailyWorkingMinutes): array
    {
        $leaveType = $summary->leave_type instanceof LeaveTypeEnum
            ? $summary->leave_type
            : LeaveTypeEnum::from((int) $summary->leave_type);

        $deduction = $this->deductionFor($summary->leave_minutes, $dailyWorkingMinutes);
        $isFullDay = $deduction >= 1.0;

        return [
            'user_id' => $summary->user_id,
            'date' => $summary->work_date->format('Y-m-d'),
            'leave_type' => $leaveType->value,
            'label' => $this->label($leaveType, $summary->leave_minutes, $isFullDay),
            'is_full_day' => $isFullDay,
            'deduction' => $deduction,
            'background_color' => $this->backgroundColor($leaveType),
            'text_color' => $this->textColor($leaveType),
        ];
    }

    /**
     * セルに出す文字
     *
     * 全日は「有」「特」「欠」、半日有給は「半」、時間有給は「時」。
     */
    private function label(LeaveTypeEnum $leaveType, ?int $leaveMinutes, bool $isFullDay): string
    {
        if ($isFullDay) {
            return match ($leaveType) {
                LeaveTypeEnum::PAID_LEAVE => '有',
                LeaveTypeEnum::SPECIAL_LEAVE => '特',
                LeaveTypeEnum::ABSENCE => '欠',
            };
        }

        // 半日は0.5人、それ以外の部分取得は時間有給として扱う
        return $this->deductionFor($leaveMinutes) === 0.5 ? '半' : '時';
    }

    /**
     * シフトパターンの色（青系・緑系）と重ならない薄い色を割り当てる
     */
    private function backgroundColor(LeaveTypeEnum $leaveType): string
    {
        return match ($leaveType) {
            LeaveTypeEnum::PAID_LEAVE => '#FFEED4',
            LeaveTypeEnum::SPECIAL_LEAVE => '#FFE4F0',
            LeaveTypeEnum::ABSENCE => '#FFE0E0',
        };
    }

    private function textColor(LeaveTypeEnum $leaveType): string
    {
        return match ($leaveType) {
            LeaveTypeEnum::PAID_LEAVE => '#8A5A00',
            LeaveTypeEnum::SPECIAL_LEAVE => '#8A2B5B',
            LeaveTypeEnum::ABSENCE => '#8A1F1F',
        };
    }
}

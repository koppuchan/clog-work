<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * シフト表（印刷・Excel出力用）のデータを作るサービス
 *
 * シフト管理画面と同じ見え方になるよう、次の点を画面に合わせる。
 * - スタッフは主所属の部署ごとにまとめ、部署の並びは部署IDの昇順、未所属は末尾
 * - 承認済みの休暇はセルに表示し、出勤人数から差し引く
 * - 日曜と祝日は赤、土曜は青で日付を示す
 * - 出勤・休日の日数は、期間の日数のうちシフトのある日を出勤、それ以外を休日として数える
 */
class ShiftTableService
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /** シフトパターンに色がないときの色（画面と同じ青緑） */
    private const DEFAULT_PATTERN_BACKGROUND = '#CCFBF1';

    private const DEFAULT_PATTERN_TEXT = '#115E59';

    /** 「休み」のセルの色 */
    private const REST_BACKGROUND = '#E5E7EB';

    private const REST_TEXT = '#4B5563';

    public function __construct(
        private readonly UserService $userService,
        private readonly ShiftService $shiftService,
        private readonly DepartmentService $departmentService,
        private readonly ShiftPatternService $shiftPatternService,
        private readonly ShiftLeaveService $shiftLeaveService,
        private readonly JapaneseHolidayService $holidayService,
        private readonly ShiftColorConverter $colorConverter,
    ) {}

    /**
     * シフト表のデータを作る
     *
     * @param  array<int, int>  $viewableUserIds  閲覧できるスタッフのID（権限の範囲）
     * @param  array<int, int>  $departmentIds  指定すると、それらの部署（主所属）のスタッフだけにする。空なら全部署（未所属も含む）
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     department_name: string|null,
     *     department_names: array<int, string>,
     *     days: array<int, array{date: string, month: int, day: int, weekday: string, tone: string, holiday_name: string|null}>,
     *     groups: array<int, array{
     *         department_id: int|null,
     *         name: string,
     *         users: array<int, array{id: int, name: string, employee_code: string|null, cells: array<string, array<string, mixed>>, work_days: int, rest_days: int}>,
     *         counts: array<string, float>
     *     }>,
     *     totals: array{counts: array<string, float>, work_days: int, rest_days: int},
     *     patterns: array<int, array{name: string, start_time: string|null, end_time: string|null, background: string, text: string}>
     * }
     */
    public function build(int $companyId, array $viewableUserIds, string $startDate, string $endDate, array $departmentIds = []): array
    {
        $departments = $this->departmentService->findByCompanyId($companyId)->pluck('name', 'id');

        $users = $this->userService->findByCompanyIdForShift($companyId, $startDate)
            ->filter(fn (User $user) => in_array($user->id, $viewableUserIds, true))
            ->values();

        $usersByDepartment = $users->toBase()->groupBy(function (User $user) use ($departments) {
            $primaryId = $user->departments->where('pivot.is_primary', true)->first()?->id;

            // 部署が見つからないスタッフは、画面と同じく「未所属」にまとめる
            return $primaryId !== null && $departments->has($primaryId) ? $primaryId : 'none';
        });

        if ($departmentIds !== []) {
            $usersByDepartment = $usersByDepartment->only($departmentIds);
        }

        $shiftsByUserDate = $this->shiftService
            ->getShiftsByCompanyIdAndDateRange($companyId, $startDate, $endDate)
            ->groupBy(fn (Shift $shift) => $shift->user_id.'|'.$shift->shift_date->format('Y-m-d'))
            ->map(fn ($shifts) => $shifts->first());

        $includedUserIds = $usersByDepartment->flatten(1)->pluck('id')->all();
        $leaves = collect($this->shiftLeaveService->getLeavesForShift($companyId, $includedUserIds, $startDate, $endDate))
            ->keyBy(fn (array $leave) => $leave['user_id'].'|'.$leave['date']);

        $days = $this->buildDays($startDate, $endDate);
        $totalDays = count($days);

        // 部署IDの昇順、未所属は末尾
        $sortedKeys = $usersByDepartment->keys()
            ->sortBy(fn ($key) => $key === 'none' ? PHP_INT_MAX : $key)
            ->values();

        $groups = [];
        $allUsers = [];
        foreach ($sortedKeys as $key) {
            $groupUsers = $usersByDepartment->get($key);
            $rows = $groupUsers->map(fn (User $user) => $this->buildUserRow($user, $days, $shiftsByUserDate, $leaves, $totalDays))->all();
            $allUsers = [...$allUsers, ...$rows];

            $groups[] = [
                'department_id' => $key === 'none' ? null : $key,
                'name' => $key === 'none' ? '未所属' : (string) $departments->get($key),
                'users' => $rows,
                'counts' => $this->dailyCounts($rows, $days),
            ];
        }

        // 選んだ部署の名前（部署IDの昇順）。全部署のときは空
        $selectedNames = $departmentIds === []
            ? []
            : $departments->only($departmentIds)->sortKeys()->values()->all();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'department_name' => $selectedNames === [] ? null : implode('・', $selectedNames),
            'department_names' => $selectedNames,
            'days' => $days,
            'groups' => $groups,
            'totals' => [
                'counts' => $this->dailyCounts($allUsers, $days),
                'work_days' => array_sum(array_column($allUsers, 'work_days')),
                'rest_days' => array_sum(array_column($allUsers, 'rest_days')),
            ],
            'patterns' => $this->buildPatterns($companyId),
        ];
    }

    /**
     * 期間の日付の一覧（曜日・祝日の色分けつき）
     *
     * @return array<int, array{date: string, month: int, day: int, weekday: string, tone: string, holiday_name: string|null}>
     */
    private function buildDays(string $startDate, string $endDate): array
    {
        $days = [];
        $date = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);

        while ($date->lte($end)) {
            $key = $date->format('Y-m-d');
            $holidayName = $this->holidayService->nameOn($key);

            $days[] = [
                'date' => $key,
                'month' => $date->month,
                'day' => $date->day,
                'weekday' => self::WEEKDAYS[$date->dayOfWeek],
                // 日曜と祝日は赤、祝日でない土曜は青
                'tone' => ($date->dayOfWeek === CarbonImmutable::SUNDAY || $holidayName !== null)
                    ? 'red'
                    : ($date->dayOfWeek === CarbonImmutable::SATURDAY ? 'blue' : 'normal'),
                'holiday_name' => $holidayName,
            ];
            $date = $date->addDay();
        }

        return $days;
    }

    /**
     * スタッフ1人分の行を作る
     *
     * @param  array<int, array{date: string}>  $days
     * @param  \Illuminate\Support\Collection<string, Shift>  $shiftsByUserDate
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $leaves
     * @return array{id: int, name: string, employee_code: string|null, cells: array<string, array<string, mixed>>, work_days: int, rest_days: int}
     */
    private function buildUserRow(User $user, array $days, $shiftsByUserDate, $leaves, int $totalDays): array
    {
        $cells = [];
        $workDays = 0;

        foreach ($days as $day) {
            $key = $user->id.'|'.$day['date'];
            $pattern = $shiftsByUserDate->get($key)?->shiftPattern;
            $leave = $leaves->get($key);

            if ($pattern !== null) {
                $workDays++;
                $color = $pattern->colors->first();
                $background = $this->colorConverter->toHex($color?->background_color, self::DEFAULT_PATTERN_BACKGROUND);
                $text = $this->colorConverter->toHex($color?->text_color, self::DEFAULT_PATTERN_TEXT);
            } else {
                $background = self::REST_BACKGROUND;
                $text = self::REST_TEXT;
            }

            $cells[$day['date']] = [
                'is_work' => $pattern !== null,
                'label' => $pattern?->name ?? '休み',
                'background' => $background,
                'text' => $text,
                'leave' => $leave === null ? null : [
                    'label' => $leave['label'],
                    'is_full_day' => $leave['is_full_day'],
                    'deduction' => $leave['deduction'],
                    'background' => $this->colorConverter->toHex($leave['background_color'], '#FFEED4'),
                    'text' => $this->colorConverter->toHex($leave['text_color'], '#8A5A00'),
                ],
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'employee_code' => $user->employee_code,
            'cells' => $cells,
            'work_days' => $workDays,
            'rest_days' => $totalDays - $workDays,
        ];
    }

    /**
     * 日ごとの出勤予定人数（「休み」は数えず、承認済みの休暇の分を差し引く）
     *
     * @param  array<int, array{cells: array<string, array<string, mixed>>}>  $rows
     * @param  array<int, array{date: string}>  $days
     * @return array<string, float>
     */
    private function dailyCounts(array $rows, array $days): array
    {
        $counts = [];

        foreach ($days as $day) {
            $scheduled = 0;
            $deducted = 0.0;

            foreach ($rows as $row) {
                $cell = $row['cells'][$day['date']];
                $scheduled += $cell['is_work'] ? 1 : 0;
                $deducted += $cell['leave']['deduction'] ?? 0.0;
            }

            $counts[$day['date']] = max(0.0, round(($scheduled - $deducted) * 100) / 100);
        }

        return $counts;
    }

    /**
     * 凡例に出すシフトパターン
     *
     * @return array<int, array{name: string, start_time: string|null, end_time: string|null, background: string, text: string}>
     */
    private function buildPatterns(int $companyId): array
    {
        return $this->shiftPatternService->getShiftPatternsByCompanyId($companyId)
            ->map(fn (array $pattern) => [
                'name' => $pattern['name'],
                'start_time' => $pattern['start_time'],
                'end_time' => $pattern['end_time'],
                'background' => $this->colorConverter->toHex($pattern['background_color'], self::DEFAULT_PATTERN_BACKGROUND),
                'text' => $this->colorConverter->toHex($pattern['text_color'], self::DEFAULT_PATTERN_TEXT),
            ])
            ->values()
            ->all();
    }
}

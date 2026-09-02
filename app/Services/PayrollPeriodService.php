<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use Carbon\CarbonImmutable;

/**
 * 給与の締め期間を求める
 *
 * 締日が20日なら「前月21日〜当月20日」が1か月分になる。
 * シフト表・勤務実績はこの期間で表示しているため、労務アラートの
 * 集計も同じ期間に揃える必要がある。暦月で集計すると、締め期間で
 * 残業が閾値を超えていてもアラートが出ない。
 */
class PayrollPeriodService
{
    /** 締日の設定がない場合の既定値 */
    private const DEFAULT_CLOSING_DAY = 31;

    /**
     * 指定した年月の締め期間を求める
     *
     * 締日が月末（31）の場合は暦月と同じになる。
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function resolve(int $companyId, int $year, int $month): array
    {
        $closingDay = $this->closingDay($companyId);
        $base = CarbonImmutable::create($year, $month, 1);

        if ($closingDay >= 31) {
            return [$base->startOfMonth(), $base->endOfMonth()];
        }

        // 締日を超える月は末日に丸める（2月に31日はないため）
        $end = $base->day(min($closingDay, $base->daysInMonth));
        $previous = $base->subMonth();
        $start = $previous->day(min($closingDay, $previous->daysInMonth))->addDay();

        return [$start->startOfDay(), $end->endOfDay()];
    }

    /**
     * 会社の締日
     */
    private function closingDay(int $companyId): int
    {
        $day = Company::query()->find($companyId)?->payroll_closing_day;

        return $day !== null ? (int) $day : self::DEFAULT_CLOSING_DAY;
    }
}

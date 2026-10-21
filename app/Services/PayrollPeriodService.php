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
     * 指定した日が属する月度（締め期間の終わりの年月）を求める
     *
     * 締日が15日なら、10/16〜11/15は「11月度」になる。暦月（10月）でそのまま
     * 引くと、10/16以降も10/15までの終わった期間が「今月」として扱われ、
     * いま進行中の期間のアラートが見られなかった。
     *
     * @return array{year: int, month: int}
     */
    public function periodMonthContaining(int $companyId, CarbonImmutable $date): array
    {
        $closingDay = $this->closingDay($companyId);

        if ($closingDay >= 31 || $date->day <= min($closingDay, $date->daysInMonth)) {
            return ['year' => $date->year, 'month' => $date->month];
        }

        $next = $date->startOfMonth()->addMonth();

        return ['year' => $next->year, 'month' => $next->month];
    }

    /**
     * 画面で選べる直近の月度（新しい順）
     *
     * 先頭は、指定した日が属する月度（進行中の期間）。
     *
     * @return array<int, array{year: int, month: int, value: string, label: string, range: string}>
     */
    public function recentPeriods(int $companyId, CarbonImmutable $today, int $count = 12): array
    {
        $latest = $this->periodMonthContaining($companyId, $today);
        $base = CarbonImmutable::create($latest['year'], $latest['month'], 1);

        return collect(range(0, $count - 1))
            ->map(function (int $i) use ($companyId, $base): array {
                $month = $base->subMonthsNoOverflow($i);
                [$start, $end] = $this->resolve($companyId, $month->year, $month->month);

                return [
                    'year' => $month->year,
                    'month' => $month->month,
                    'value' => $month->format('Y-m'),
                    'label' => $month->format('Y年m月'),
                    'range' => $start->format('n/j').'〜'.$end->format('n/j'),
                ];
            })
            ->all();
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

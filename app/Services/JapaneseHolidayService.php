<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * 日本の祝日を求めるサービス
 *
 * シフト表のExcel出力など、サーバー側で祝日を判定する場面で使う。
 * 画面（フロントエンド）は japanese-holidays パッケージで判定しているため、
 * 同じ結果になるよう、祝日法に従って計算する（2020年以降が対象）。
 * 春分・秋分の日は、1980〜2099年に使える近似式で求める。
 */
class JapaneseHolidayService
{
    /**
     * 年ごとの計算結果（同じ年を何度も計算しないための保持）
     *
     * @var array<int, array<string, string>>
     */
    private array $cache = [];

    /**
     * 指定した日付の祝日名を返す（祝日でなければnull）
     */
    public function nameOn(string $date): ?string
    {
        $year = (int) substr($date, 0, 4);

        return $this->holidaysOf($year)[$date] ?? null;
    }

    /**
     * その年の祝日を返す（振替休日・国民の休日を含む）
     *
     * @return array<string, string> 日付（Y-m-d）をキーとした祝日名
     */
    public function holidaysOf(int $year): array
    {
        if (isset($this->cache[$year])) {
            return $this->cache[$year];
        }

        $holidays = [];
        $add = function (int $month, int $day, string $name) use (&$holidays, $year): void {
            $holidays[sprintf('%04d-%02d-%02d', $year, $month, $day)] = $name;
        };

        // 東京オリンピックのため、2020年・2021年だけ海の日・スポーツの日・山の日が移動していた
        $olympic = [
            2020 => ['sea' => [7, 23], 'sports' => [7, 24], 'mountain' => [8, 10]],
            2021 => ['sea' => [7, 22], 'sports' => [7, 23], 'mountain' => [8, 8]],
        ][$year] ?? null;

        $add(1, 1, '元日');
        $add(1, $this->nthMonday($year, 1, 2), '成人の日');
        $add(2, 11, '建国記念の日');
        $add(2, 23, '天皇誕生日');
        $add(3, $this->springEquinoxDay($year), '春分の日');
        $add(4, 29, '昭和の日');
        $add(5, 3, '憲法記念日');
        $add(5, 4, 'みどりの日');
        $add(5, 5, 'こどもの日');
        [$seaMonth, $seaDay] = $olympic['sea'] ?? [7, $this->nthMonday($year, 7, 3)];
        $add($seaMonth, $seaDay, '海の日');
        [$mountainMonth, $mountainDay] = $olympic['mountain'] ?? [8, 11];
        $add($mountainMonth, $mountainDay, '山の日');
        $add(9, $this->nthMonday($year, 9, 3), '敬老の日');
        $add(9, $this->autumnEquinoxDay($year), '秋分の日');
        [$sportsMonth, $sportsDay] = $olympic['sports'] ?? [10, $this->nthMonday($year, 10, 2)];
        $add($sportsMonth, $sportsDay, 'スポーツの日');
        $add(11, 3, '文化の日');
        $add(11, 23, '勤労感謝の日');

        // 国民の休日: 前日と翌日がどちらも祝日の、日曜でない平日
        $date = CarbonImmutable::create($year, 1, 2);
        while ($date->year === $year) {
            $key = $date->format('Y-m-d');
            if (! isset($holidays[$key])
                && isset($holidays[$date->subDay()->format('Y-m-d')])
                && isset($holidays[$date->addDay()->format('Y-m-d')])
                && $date->dayOfWeek !== CarbonImmutable::SUNDAY) {
                $holidays[$key] = '国民の休日';
            }
            $date = $date->addDay();
        }

        // 振替休日: 日曜と重なった祝日の後の、最初の祝日でない日
        foreach ($holidays as $key => $name) {
            $holiday = CarbonImmutable::parse($key);
            if ($holiday->dayOfWeek !== CarbonImmutable::SUNDAY || $name === '振替休日') {
                continue;
            }
            $substitute = $holiday->addDay();
            while (isset($holidays[$substitute->format('Y-m-d')])) {
                $substitute = $substitute->addDay();
            }
            if ($substitute->year === $year) {
                $holidays[$substitute->format('Y-m-d')] = '振替休日';
            }
        }

        ksort($holidays);

        return $this->cache[$year] = $holidays;
    }

    /**
     * その月の第n月曜日の日付（日）
     */
    private function nthMonday(int $year, int $month, int $nth): int
    {
        $first = CarbonImmutable::create($year, $month, 1);
        $offset = (CarbonImmutable::MONDAY - $first->dayOfWeek + 7) % 7;

        return 1 + $offset + ($nth - 1) * 7;
    }

    private function springEquinoxDay(int $year): int
    {
        $k = $year - 1980;

        return (int) floor(20.8431 + 0.242194 * $k - floor($k / 4));
    }

    private function autumnEquinoxDay(int $year): int
    {
        $k = $year - 1980;

        return (int) floor(23.2488 + 0.242194 * $k - floor($k / 4));
    }
}

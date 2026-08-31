<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TimeRecordTypeEnum;
use App\Repositories\Contracts\CompanyShiftRoundingSettingRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * 時刻丸めサービス
 *
 * 打刻時刻を会社設定に基づいて丸める処理を担当
 */
class TimeRoundingService
{
    private const MINUTES_PER_DAY = 1440;

    public function __construct(
        private readonly CompanyShiftRoundingSettingRepositoryInterface $roundingSettingRepository
    ) {}

    /**
     * 会社設定と打刻種別に基づいて時刻を丸める
     *
     * @param  int  $companyId  会社ID
     * @param  CarbonImmutable  $time  丸める時刻
     * @param  TimeRecordTypeEnum  $recordType  打刻種別
     * @return CarbonImmutable 丸められた時刻
     */
    public function roundTime(int $companyId, CarbonImmutable $time, TimeRecordTypeEnum $recordType): CarbonImmutable
    {
        $unitMinutes = $this->roundingSettingRepository->getRoundingMinutes($companyId);

        // 丸め設定がない、または1分単位の場合は丸めなし
        if ($unitMinutes === null || $unitMinutes <= 1) {
            return $time;
        }

        return $recordType->shouldRoundUp()
            ? $this->roundUp($time, $unitMinutes)
            : $this->roundDown($time, $unitMinutes);
    }

    /**
     * 時刻を切り上げる（次の区切りに丸める）
     *
     * 例: 5分単位の場合、08:47 → 08:50
     *
     * @param  CarbonImmutable  $time  丸める時刻
     * @param  int  $unitMinutes  丸め単位（分）
     * @return CarbonImmutable 切り上げられた時刻
     */
    public function roundUp(CarbonImmutable $time, int $unitMinutes): CarbonImmutable
    {
        $totalMinutes = $time->hour * 60 + $time->minute;
        $remainder = $totalMinutes % $unitMinutes;

        // 既に区切りの時刻の場合はそのまま返す
        if ($remainder === 0) {
            return $time->setTime($time->hour, $time->minute, 0);
        }

        $roundedMinutes = $totalMinutes + ($unitMinutes - $remainder);

        // 日付越えの処理

        if ($roundedMinutes >= self::MINUTES_PER_DAY) {
            $roundedMinutes -= self::MINUTES_PER_DAY;
            $time = $time->addDay();
        }

        $hours = (int) floor($roundedMinutes / 60);
        $minutes = $roundedMinutes % 60;

        return $time->setTime($hours, $minutes, 0);
    }

    /**
     * 時刻を切り捨てる（前の区切りに丸める）
     *
     * 例: 5分単位の場合、17:13 → 17:10
     *
     * @param  CarbonImmutable  $time  丸める時刻
     * @param  int  $unitMinutes  丸め単位（分）
     * @return CarbonImmutable 切り捨てられた時刻
     */
    public function roundDown(CarbonImmutable $time, int $unitMinutes): CarbonImmutable
    {
        $totalMinutes = $time->hour * 60 + $time->minute;
        $roundedMinutes = (int) floor($totalMinutes / $unitMinutes) * $unitMinutes;

        $hours = (int) floor($roundedMinutes / 60);
        $minutes = $roundedMinutes % 60;

        return $time->setTime($hours, $minutes, 0);
    }
}

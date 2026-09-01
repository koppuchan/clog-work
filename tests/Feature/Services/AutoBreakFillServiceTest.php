<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\ShiftPattern;
use App\Services\AutoBreakFillService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * 休憩打刻がない日にシフトの休憩時刻を補う機能の検証。
 *
 * 実打刻を上書きしないことと、早退などで休憩時刻が労働時間の外に出た場合に
 * 重なる範囲しか控除しないことが要点。
 */
class AutoBreakFillServiceTest extends TestCase
{
    private AutoBreakFillService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AutoBreakFillService::class);
    }

    private function pattern(array $attributes = []): ShiftPattern
    {
        return new ShiftPattern(array_merge([
            'name' => '通常勤務',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_mode' => 2,
            'break_start' => '12:00',
            'break_end' => '13:00',
            'auto_fill_break' => true,
        ], $attributes));
    }

    /**
     * @test
     */
    public function 休憩打刻がなく設定が有効なら適用する(): void
    {
        $this->assertTrue($this->service->isApplicable($this->pattern(), false));
    }

    /**
     * @test
     */
    public function 休憩打刻が1件でもあれば適用しない(): void
    {
        // 片方だけの打刻は打ち忘れではなく修正すべき状態なので上書きしない
        $this->assertFalse($this->service->isApplicable($this->pattern(), true));
    }

    /**
     * @test
     */
    public function 設定が無効なら適用しない(): void
    {
        $this->assertFalse(
            $this->service->isApplicable($this->pattern(['auto_fill_break' => false]), false)
        );
    }

    /**
     * @test
     */
    public function 休憩時刻が設定されていなければ適用しない(): void
    {
        // 分数のみの休憩設定は時間帯を特定できない
        $pattern = $this->pattern(['break_mode' => 1, 'break_start' => null, 'break_end' => null]);

        $this->assertFalse($this->service->isApplicable($pattern, false));
    }

    /**
     * @test
     */
    public function シフトがなければ適用しない(): void
    {
        $this->assertFalse($this->service->isApplicable(null, false));
    }

    /**
     * @test
     */
    public function 休憩が労働時間内に収まる場合はそのまま控除する(): void
    {
        $minutes = $this->service->fillMinutes(
            $this->pattern(),
            CarbonImmutable::parse('2026-06-24'),
            CarbonImmutable::parse('2026-06-24 09:00'),
            CarbonImmutable::parse('2026-06-24 18:00'),
        );

        $this->assertSame(60, $minutes);
    }

    /**
     * @test
     */
    public function 早退で休憩の途中までしか働いていない場合は重なる分だけ控除する(): void
    {
        // 12:30 に早退したので、休憩12:00〜13:00 のうち 12:00〜12:30 のみ
        $minutes = $this->service->fillMinutes(
            $this->pattern(),
            CarbonImmutable::parse('2026-06-24'),
            CarbonImmutable::parse('2026-06-24 09:00'),
            CarbonImmutable::parse('2026-06-24 12:30'),
        );

        $this->assertSame(30, $minutes);
    }

    /**
     * @test
     */
    public function 休憩時刻より前に退勤した場合は控除しない(): void
    {
        $minutes = $this->service->fillMinutes(
            $this->pattern(),
            CarbonImmutable::parse('2026-06-24'),
            CarbonImmutable::parse('2026-06-24 09:00'),
            CarbonImmutable::parse('2026-06-24 11:30'),
        );

        $this->assertSame(0, $minutes);
    }

    /**
     * @test
     */
    public function 出勤が休憩の途中だった場合は残りの分だけ控除する(): void
    {
        $minutes = $this->service->fillMinutes(
            $this->pattern(),
            CarbonImmutable::parse('2026-06-24'),
            CarbonImmutable::parse('2026-06-24 12:15'),
            CarbonImmutable::parse('2026-06-24 18:00'),
        );

        $this->assertSame(45, $minutes);
    }

    /**
     * @test
     */
    public function 退勤していない日は控除しない(): void
    {
        $minutes = $this->service->fillMinutes(
            $this->pattern(),
            CarbonImmutable::parse('2026-06-24'),
            CarbonImmutable::parse('2026-06-24 09:00'),
            null,
        );

        $this->assertSame(0, $minutes);
    }

    /**
     * @test
     */
    public function 夜勤で休憩が翌日側にある場合も控除する(): void
    {
        // 22:00〜翌7:00 の夜勤、休憩は翌 1:00〜2:00
        $pattern = $this->pattern([
            'start_time' => '22:00',
            'end_time' => '07:00',
            'break_start' => '01:00',
            'break_end' => '02:00',
        ]);

        $minutes = $this->service->fillMinutes(
            $pattern,
            CarbonImmutable::parse('2026-06-24'),
            CarbonImmutable::parse('2026-06-24 22:00'),
            CarbonImmutable::parse('2026-06-25 07:00'),
        );

        $this->assertSame(60, $minutes);
    }
}

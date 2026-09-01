<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WorkTimeCalculator;
use Tests\TestCase;

/**
 * 労働時間・時間外・遅早の算出。
 *
 * 櫻本さまに確定いただいた定義と、ご提示いただいた2つの例を固定する。
 */
class WorkTimeCalculatorTest extends TestCase
{
    private WorkTimeCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new WorkTimeCalculator;
    }

    /**
     * @test
     */
    public function 所定労働時間はシフトから休憩を引いた時間になる(): void
    {
        // 8:00〜17:00（9時間拘束）から休憩1時間を引いて8時間
        $this->assertSame(480, $this->calc->scheduledWorkMinutes('08:00', '17:00', 60));
    }

    /**
     * @test
     */
    public function 夜勤の所定労働時間を日跨ぎで算出できる(): void
    {
        // 22:00〜06:00（8時間拘束）から休憩1時間を引いて7時間
        $this->assertSame(420, $this->calc->scheduledWorkMinutes('22:00', '06:00', 60));
    }

    /**
     * @test
     */
    public function シフトが未設定なら所定労働時間は0になる(): void
    {
        $this->assertSame(0, $this->calc->scheduledWorkMinutes(null, null));
        $this->assertSame(0, $this->calc->scheduledWorkMinutes('09:00', null));
    }

    /**
     * @test
     */
    public function 櫻本さま提示例1_休憩を取らず早退した場合(): void
    {
        // シフト 8:00-17:00 休憩12:00-13:00 → 所定8H
        // 実働 8:00-13:00 休憩なし → 実労働5H
        $scheduled = $this->calc->scheduledWorkMinutes('08:00', '17:00', 60);
        $net = 5 * 60;

        // 8H − 5H = 3H の遅早
        $this->assertSame(3 * 60, $this->calc->shortfallMinutes($net, $scheduled));

        // 所定に届いていないため時間外は発生しない
        $this->assertSame(0, $this->calc->overtimeMinutes($net, $scheduled));
    }

    /**
     * @test
     */
    public function 櫻本さま提示例2_半日有給を取得した場合(): void
    {
        // 例1と同じ勤務に、半日有給（8H × 0.5 = 4H）を加える
        $scheduled = $this->calc->scheduledWorkMinutes('08:00', '17:00', 60);
        $net = 5 * 60;
        $paidLeave = 4 * 60;

        // 5H + 4H = 9H が所定8Hを上回るため遅早は消える
        $this->assertSame(0, $this->calc->shortfallMinutes($net, $scheduled, $paidLeave));

        // 合計が所定を超えても時間外は計上しない（実労働5H < 所定8H のため）
        $this->assertSame(0, $this->calc->overtimeMinutes($net, $scheduled));
    }

    /**
     * @test
     */
    public function 休憩を短縮した分は時間外になる(): void
    {
        // シフト 8:00-17:00 休憩1H → 所定8H
        // 実働 8:00-17:00 休憩15分 → 実労働8時間45分
        $scheduled = $this->calc->scheduledWorkMinutes('08:00', '17:00', 60);
        $net = (9 * 60) - 15;

        $this->assertSame(45, $this->calc->overtimeMinutes($net, $scheduled));
        $this->assertSame(0, $this->calc->shortfallMinutes($net, $scheduled));
    }

    /**
     * @test
     */
    public function 早出も時間外になる(): void
    {
        // 7:30〜17:00 休憩1H → 実労働8時間30分、所定8H
        $scheduled = $this->calc->scheduledWorkMinutes('08:00', '17:00', 60);
        $net = (9 * 60 + 30) - 60;

        $this->assertSame(30, $this->calc->overtimeMinutes($net, $scheduled));
    }

    /**
     * @test
     */
    public function 所定どおりに働けば時間外も遅早も発生しない(): void
    {
        $scheduled = $this->calc->scheduledWorkMinutes('08:00', '17:00', 60);
        $net = 8 * 60;

        $this->assertSame(0, $this->calc->overtimeMinutes($net, $scheduled));
        $this->assertSame(0, $this->calc->shortfallMinutes($net, $scheduled));
    }

    /**
     * @test
     */
    public function シフトのない日は時間外も遅早も算出しない(): void
    {
        // 休日勤務は所定労働時間を持たない。時間外ではなく休日として扱う
        $this->assertSame(0, $this->calc->overtimeMinutes(8 * 60, 0));
        $this->assertSame(0, $this->calc->shortfallMinutes(0, 0));
    }

    /**
     * @test
     */
    public function 有給が所定を超えても遅早は0で止まる(): void
    {
        $scheduled = $this->calc->scheduledWorkMinutes('08:00', '17:00', 60);

        $this->assertSame(0, $this->calc->shortfallMinutes(0, $scheduled, 10 * 60));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\JapaneseHolidayService;
use Tests\TestCase;

/**
 * 日本の祝日の計算。
 *
 * シフト表のExcel出力で、画面（japanese-holidays パッケージ）と同じ日を祝日として扱う。
 * 2020〜2040年の全ての祝日が、そのパッケージの結果と名前まで一致することを確認済み。
 */
class JapaneseHolidayServiceTest extends TestCase
{
    private JapaneseHolidayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JapaneseHolidayService;
    }

    /**
     * @test
     */
    public function 年間の祝日を日付順に返す(): void
    {
        $this->assertSame([
            '2026-01-01' => '元日',
            '2026-01-12' => '成人の日',
            '2026-02-11' => '建国記念の日',
            '2026-02-23' => '天皇誕生日',
            '2026-03-20' => '春分の日',
            '2026-04-29' => '昭和の日',
            '2026-05-03' => '憲法記念日',
            '2026-05-04' => 'みどりの日',
            '2026-05-05' => 'こどもの日',
            '2026-05-06' => '振替休日',
            '2026-07-20' => '海の日',
            '2026-08-11' => '山の日',
            '2026-09-21' => '敬老の日',
            '2026-09-22' => '国民の休日',
            '2026-09-23' => '秋分の日',
            '2026-10-12' => 'スポーツの日',
            '2026-11-03' => '文化の日',
            '2026-11-23' => '勤労感謝の日',
        ], $this->service->holidaysOf(2026));
    }

    /**
     * @test
     */
    public function 祝日名を日付から引ける(): void
    {
        $this->assertSame('敬老の日', $this->service->nameOn('2026-09-21'));
        $this->assertSame('国民の休日', $this->service->nameOn('2026-09-22'));
        $this->assertNull($this->service->nameOn('2026-09-24'));
    }

    /**
     * @test
     *
     * 日曜と重なった祝日は、その後の最初の祝日でない日が振替休日になる。
     */
    public function 日曜と重なった祝日は振替休日になる(): void
    {
        // 2025年はみどりの日（5/4）が日曜。5/5・5/6が続くので、振替は5/6
        $this->assertSame('振替休日', $this->service->nameOn('2025-05-06'));
        // 2024年は建国記念の日（2/11）が日曜で、振替は翌日の2/12
        $this->assertSame('振替休日', $this->service->nameOn('2024-02-12'));
    }

    /**
     * @test
     */
    public function 東京オリンピックの年は海の日_スポーツの日_山の日が移動している(): void
    {
        $this->assertSame('海の日', $this->service->nameOn('2020-07-23'));
        $this->assertSame('スポーツの日', $this->service->nameOn('2020-07-24'));
        $this->assertSame('山の日', $this->service->nameOn('2020-08-10'));
        $this->assertNull($this->service->nameOn('2020-07-20'));

        // 2021年の山の日（8/8）は日曜なので、翌日が振替休日
        $this->assertSame('山の日', $this->service->nameOn('2021-08-08'));
        $this->assertSame('振替休日', $this->service->nameOn('2021-08-09'));
    }

    /**
     * @test
     */
    public function 年をまたいでも_その年の祝日だけを返す(): void
    {
        $this->assertNull($this->service->nameOn('2026-12-31'));
        $this->assertSame('元日', $this->service->nameOn('2027-01-01'));
    }
}

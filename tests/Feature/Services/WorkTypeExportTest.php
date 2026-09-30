<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Services\AttendanceExcelExportService;
use Tests\TestCase;

/**
 * 帳票・CSVの勤務区分の判定（task#71）。
 *
 * 集計欄の欠勤日数は =COUNTIFS(B7:B37,"欠勤") でこの表記を数えるため、
 * 文字列がそのまま集計に効く。
 *
 * 判定基準（曜日は使わない）:
 * - 休暇種別が設定されている日はそれを優先する
 * - シフト時間あり + 出退勤あり → 出勤
 * - シフト時間あり + 出退勤なし → 欠勤
 * - シフト時間なし + 出退勤あり → 休出
 * - シフト時間なし + 出退勤なし → 休日
 */
class WorkTypeExportTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function workType(array $attributes, ?string $effectiveWorkStart): string
    {
        $summary = $attributes === [] ? null : (object) $attributes;

        $method = new \ReflectionMethod(AttendanceExcelExportService::class, 'getWorkType');

        return $method->invoke(
            app(AttendanceExcelExportService::class),
            $summary,
            $effectiveWorkStart,
        );
    }

    /**
     * @test
     */
    public function シフトあり_出退勤ありは出勤になる(): void
    {
        $type = $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
        ], '09:00');

        $this->assertSame('出勤', $type);
    }

    /**
     * @test
     */
    public function シフトあり_出退勤なしは欠勤になる(): void
    {
        $type = $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
        ], null);

        $this->assertSame('欠勤', $type);
    }

    /**
     * @test
     */
    public function シフトなし_出退勤ありは休出になる(): void
    {
        $type = $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => null,
        ], '09:00');

        $this->assertSame('休出', $type);
    }

    /**
     * @test
     */
    public function シフトなし_出退勤なしは休日になる(): void
    {
        $type = $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => null,
        ], null);

        $this->assertSame('休日', $type);
    }

    /**
     * @test
     *
     * 勤務実績そのものが無い日（summaryがnull）も、シフト・出退勤とも
     * 無い扱いとして休日になる。
     */
    public function 勤務実績そのものが無い日は休日になる(): void
    {
        $this->assertSame('休日', $this->workType([], null));
    }

    /**
     * @test
     *
     * 土曜・日曜であっても、平日と同じ基準（シフト・出退勤の有無）で判定する。
     * 以前は曜日で別ロジックを使っており、シフトが割り当てられている土日に
     * 出退勤がないと「休日」になってしまっていた（欠勤と同じ扱いのはず）。
     */
    public function 土日でもシフトあり_出退勤なしは欠勤になる(): void
    {
        $type = $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
        ], null);

        $this->assertSame('欠勤', $type);
    }

    /**
     * @test
     */
    public function 休暇種別があればシフト_出退勤の有無に関わらずそれを優先する(): void
    {
        $type = $this->workType([
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null,
            'scheduled_start_time' => '09:00',
        ], '09:00');

        $this->assertSame('有給休暇', $type);
    }

    /**
     * @test
     *
     * 「欠勤」の休暇種別だけは例外で、実打刻があれば優先しない。
     * 入江さまの9/18のケース: 欠勤の承認済み申請が残ったまま実際は
     * 出勤しており、そのまま欠勤で出力され続けていた
     * （クライアント報告 #70: シフト・出退勤があるのに欠勤で出力される）。
     */
    public function 欠勤の休暇種別があっても実打刻があれば出勤になる(): void
    {
        $type = $this->workType([
            'leave_type' => LeaveTypeEnum::ABSENCE,
            'scheduled_start_time' => '09:00',
        ], '08:51');

        $this->assertSame('出勤', $type);
    }

    /**
     * @test
     *
     * 欠勤の休暇種別は、実打刻が無ければこれまでどおり優先される
     * （シフトが無い日の欠勤申請など、構造的な判定だけでは欠勤に
     * ならない組み合わせでも欠勤と表示すべきケースを壊さない）。
     */
    public function 欠勤の休暇種別は実打刻が無ければ優先される(): void
    {
        $type = $this->workType([
            'leave_type' => LeaveTypeEnum::ABSENCE,
            'scheduled_start_time' => null,
        ], null);

        $this->assertSame('欠勤', $type);
    }

    /**
     * @test
     *
     * summaryのwork_startを直接見るのではなく、呼び出し側が実打刻を優先した
     * 値（effectiveWorkStart）を渡す前提であることの確認。summary自体は
     * work_startを持たない（打刻直後で集計バッチがまだ反映していない）状態
     * でも、実打刻から得た値が渡されれば出勤と判定される
     * （クライアント報告: シフト・出退勤があるのに欠勤で出力される不具合の原因）。
     */
    public function summaryのwork_startが無くても渡された実打刻があれば出勤になる(): void
    {
        $type = $this->workType([
            'leave_type' => null,
            'scheduled_start_time' => '09:00',
            'work_start' => null,
        ], '08:58');

        $this->assertSame('出勤', $type);
    }
}

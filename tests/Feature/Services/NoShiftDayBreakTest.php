<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\DailyWorkSummaryBatchService;
use App\Services\DailyWorkSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * シフトの無い日（休み）には、休憩を自動で補わない。
 *
 * クライアント報告: 休憩の自動入力ですが、休みの日の休憩時刻の表示がおかしい。
 * シフトに無い日（休日）は休憩の打刻が入らないようにしてほしい。
 * 休みの日に出勤すると、会社の基本シフトの休憩（12:00〜13:30）が代わりに使われ、
 * 打刻していないのに休憩1:30が付いていた。
 */
class NoShiftDayBreakTest extends TestCase
{
    use DatabaseTransactions;

    private const SHIFT_DAY = '2026-10-19';

    private const OFF_DAY = '2026-10-20';

    private Company $company;

    private User $user;

    private ShiftPattern $pattern;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['daily_working_hours' => 8.0]);
        $this->user = User::factory()->forCompany($this->company->id)->create();

        // 会社の基本シフト（休憩の自動入力あり）。シフトのある日の休憩にも使うパターン
        $this->pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '基本',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'work_minutes' => 450,
            'break_mode' => 2,
            'break_minutes' => 90,
            'break_start' => '12:00',
            'break_end' => '13:30',
            'auto_fill_break' => true,
        ]);
        $this->company->update(['default_shift_pattern_id' => $this->pattern->id]);
    }

    private function work(string $date, string $start, string $end): void
    {
        foreach ([[TimeRecordTypeEnum::WORK_START, $start], [TimeRecordTypeEnum::WORK_END, $end]] as [$type, $time]) {
            TimeRecord::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'record_type' => $type,
                'record_time' => $date.' '.$time,
                'rounded_time' => $date.' '.$time,
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        }
    }

    private function aggregate(string $date): DailyWorkSummary
    {
        app(DailyWorkSummaryBatchService::class)->aggregateByUser($this->company, $this->user, CarbonImmutable::parse($date));

        return DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', $date)
            ->firstOrFail();
    }

    /**
     * @test
     */
    public function シフトの無い日に出勤しても休憩は補われない(): void
    {
        $this->work(self::OFF_DAY, '06:00:00', '17:50:00');

        $summary = $this->aggregate(self::OFF_DAY);

        $this->assertSame(0, $summary->break_minutes);
        $this->assertSame(710, $summary->net_work_minutes); // 6:00〜17:50 をそのまま労働時間にする
        $this->assertSame(710, $summary->holiday_minutes);
    }

    /**
     * @test
     */
    public function シフトのある日は従来どおり休憩が補われる(): void
    {
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => self::SHIFT_DAY,
            'shift_pattern_id' => $this->pattern->id,
        ]);
        $this->work(self::SHIFT_DAY, '06:00:00', '17:50:00');

        $summary = $this->aggregate(self::SHIFT_DAY);

        $this->assertSame(90, $summary->break_minutes);
        $this->assertSame(620, $summary->net_work_minutes); // 710 - 90
    }

    /**
     * @test
     *
     * 休憩の打刻をした日は、シフトの有無にかかわらず打刻どおり計算する。
     */
    public function シフトの無い日でも休憩を打刻していれば打刻どおり控除される(): void
    {
        $this->work(self::OFF_DAY, '06:00:00', '17:50:00');
        foreach ([[TimeRecordTypeEnum::BREAK_START, '12:00:00'], [TimeRecordTypeEnum::BREAK_END, '12:45:00']] as [$type, $time]) {
            TimeRecord::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'record_type' => $type,
                'record_time' => self::OFF_DAY.' '.$time,
                'rounded_time' => self::OFF_DAY.' '.$time,
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        }

        $summary = $this->aggregate(self::OFF_DAY);

        $this->assertSame(45, $summary->break_minutes);
    }

    /**
     * @test
     *
     * 深夜時間の計算で引く休憩にも、シフトの無い日は基本シフトの休憩を使わない。
     */
    public function シフトの無い日の深夜時間は基本シフトの休憩で引かれない(): void
    {
        $this->pattern->update(['break_start' => '22:30', 'break_end' => '23:00', 'break_minutes' => 30]);
        $this->work(self::OFF_DAY, '20:00:00', '23:30:00');

        $summary = $this->aggregate(self::OFF_DAY);

        $this->assertSame(90, $summary->night_minutes); // 22:00〜23:30。休憩は引かない
    }

    /**
     * @test
     *
     * CSVも、シフトの無い日は集計に休憩分が残っていても休憩の時刻を出さない。
     */
    public function cs_vはシフトの無い日の休憩を出さない(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => self::OFF_DAY,
            'work_start' => self::OFF_DAY.' 06:00:00',
            'work_end' => self::OFF_DAY.' 17:50:00',
            'break_minutes' => 90,
            'net_work_minutes' => 620,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $csv = app(DailyWorkSummaryService::class)->generateCsv(
            $this->company->id,
            $this->user->id,
            self::OFF_DAY,
            self::OFF_DAY,
            $this->user,
        );
        $row = str_getcsv(array_values(array_filter(explode("\n", $csv), fn ($line) => str_contains($line, '10/20')))[0]);

        $this->assertSame('', $row[10]); // 休憩入①
        $this->assertSame('', $row[11]); // 休憩出①
    }
}

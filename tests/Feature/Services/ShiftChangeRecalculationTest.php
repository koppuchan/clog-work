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
use App\Services\ShiftPatternService;
use App\Services\ShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * シフトを後から変更したときに、集計済みの勤務実績が再集計される検証。
 *
 * クライアント報告: 時間の計算が違う。後からシフト時刻を変更したためでは
 * ないか。シフトパターンの休憩を12:00〜13:00から12:00〜13:20に変えた後も、
 * 変更前に集計した日は休憩60分のまま労働時間が20分長く出ていた。
 * 再集計はレスポンスを返した後に動くため、テストでは app()->terminate() で
 * その処理を実行する。
 */
class ShiftChangeRecalculationTest extends TestCase
{
    use DatabaseTransactions;

    private const WORK_DATE = '2026-10-09';

    private Company $company;

    private User $user;

    private ShiftPattern $pattern;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['daily_working_hours' => 8.0]);
        $this->user = User::factory()->forCompany($this->company->id)->create();
        $this->pattern = $this->createPattern('営業', '13:00');
    }

    private function createPattern(string $name, string $breakEnd): ShiftPattern
    {
        $breakMinutes = $breakEnd === '13:00' ? 60 : 80;

        return ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'start_time' => '09:40',
            'end_time' => '19:00',
            'work_minutes' => 560 - $breakMinutes,
            'break_mode' => 2,
            'break_minutes' => $breakMinutes,
            'break_start' => '12:00',
            'break_end' => $breakEnd,
            'auto_fill_break' => true,
        ]);
    }

    /**
     * 9:25〜20:05（丸め後）で勤務し、休憩の打刻は無い日を、現在のシフトで集計する。
     */
    private function givenWorkedAndAggregatedDay(?User $user = null, ?ShiftPattern $pattern = null): void
    {
        $user ??= $this->user;
        $pattern ??= $this->pattern;

        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'shift_date' => self::WORK_DATE,
            'shift_pattern_id' => $pattern->id,
        ]);

        foreach ([
            [TimeRecordTypeEnum::WORK_START, '09:24:10', '09:25:00'],
            [TimeRecordTypeEnum::WORK_END, '20:08:30', '20:05:00'],
        ] as [$type, $time, $rounded]) {
            TimeRecord::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'record_type' => $type,
                'record_time' => self::WORK_DATE.' '.$time,
                'rounded_time' => self::WORK_DATE.' '.$rounded,
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        }

        app(DailyWorkSummaryBatchService::class)->aggregateByUser(
            $this->company,
            $user,
            CarbonImmutable::parse(self::WORK_DATE)
        );
    }

    private function summaryOf(User $user): DailyWorkSummary
    {
        return DailyWorkSummary::query()
            ->where('user_id', $user->id)
            ->where('work_date', self::WORK_DATE)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function patternUpdate(string $breakEnd): array
    {
        return [
            'startTime' => '09:40',
            'endTime' => '19:00',
            'breakMode' => 2,
            'breakStart' => '12:00',
            'breakEnd' => $breakEnd,
            'autoFillBreak' => true,
        ];
    }

    /**
     * @test
     */
    public function 集計直後は元のシフトの休憩60分で計算されている(): void
    {
        $this->givenWorkedAndAggregatedDay();

        $summary = $this->summaryOf($this->user);

        $this->assertSame(60, $summary->break_minutes);
        $this->assertSame(580, $summary->net_work_minutes); // 9:25〜20:05 = 10:40 から休憩60分
    }

    /**
     * @test
     */
    public function シフトパターンの休憩を変えると集計済みの勤務実績が再集計される(): void
    {
        $this->givenWorkedAndAggregatedDay();

        app(ShiftPatternService::class)->updateShiftPattern($this->pattern->id, $this->patternUpdate('13:20'));
        $this->app->terminate();

        $summary = $this->summaryOf($this->user);

        $this->assertSame(80, $summary->break_minutes);
        $this->assertSame(560, $summary->net_work_minutes); // 10:40 から休憩80分 = 9:20
    }

    /**
     * @test
     */
    public function シフトパターンを変えても手動修正の勤務実績は書き換えない(): void
    {
        $this->givenWorkedAndAggregatedDay();
        DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', self::WORK_DATE)
            ->update(['record_source' => RecordSourceEnum::MANUAL, 'break_minutes' => 99]);

        app(ShiftPatternService::class)->updateShiftPattern($this->pattern->id, $this->patternUpdate('13:20'));
        $this->app->terminate();

        $this->assertSame(99, $this->summaryOf($this->user)->break_minutes);
    }

    /**
     * @test
     */
    public function シフトパターンの名前だけを変えても再集計しない(): void
    {
        $this->givenWorkedAndAggregatedDay();
        DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', self::WORK_DATE)
            ->update(['break_minutes' => 99]);

        app(ShiftPatternService::class)->updateShiftPattern($this->pattern->id, ['name' => '営業（新）']);
        $this->app->terminate();

        $this->assertSame(99, $this->summaryOf($this->user)->break_minutes);
    }

    /**
     * @test
     */
    public function 日ごとのシフトを別のパターンに変えると、その日の勤務実績が再集計される(): void
    {
        $this->givenWorkedAndAggregatedDay();
        $longBreakPattern = $this->createPattern('営業B', '13:20');

        app(ShiftService::class)->upsertMany($this->company->id, [
            ['user_id' => $this->user->id, 'shift_date' => self::WORK_DATE, 'shift_pattern_id' => $longBreakPattern->id, 'note' => null],
        ]);
        $this->app->terminate();

        $this->assertSame(80, $this->summaryOf($this->user)->break_minutes);
    }

    /**
     * @test
     *
     * 画面は保存のたびに全員分のシフトを送る。変わっていない日まで
     * 再集計すると、保存のたびに全員分の集計が走ってしまう。
     */
    public function シフトが変わっていない日は再集計しない(): void
    {
        $this->givenWorkedAndAggregatedDay();
        DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', self::WORK_DATE)
            ->update(['break_minutes' => 99]);

        app(ShiftService::class)->upsertMany($this->company->id, [
            ['user_id' => $this->user->id, 'shift_date' => self::WORK_DATE, 'shift_pattern_id' => $this->pattern->id, 'note' => null],
        ]);
        $this->app->terminate();

        $this->assertSame(99, $this->summaryOf($this->user)->break_minutes);
    }

    /**
     * @test
     *
     * 出勤した日のシフトを休みに変えた場合、勤務実績は消さず、
     * シフトなしの出勤（休日出勤扱い）として再集計する。
     */
    public function 出勤した日のシフトを休みにすると再集計される(): void
    {
        $this->givenWorkedAndAggregatedDay();

        app(ShiftService::class)->upsertMany($this->company->id, [
            ['user_id' => $this->user->id, 'shift_date' => self::WORK_DATE, 'shift_pattern_id' => null, 'note' => null],
        ]);
        $this->app->terminate();

        $summary = $this->summaryOf($this->user);

        $this->assertNull($summary->scheduled_start_time);
        $this->assertSame($summary->net_work_minutes, $summary->holiday_minutes);
    }
}

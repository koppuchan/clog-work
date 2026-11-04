<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Exceptions\BusinessException;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\DailyWorkSummaryBatchService;
use App\Services\DailyWorkSummaryService;
use App\Services\RawStampTimeService;
use App\Services\StampService;
use App\Services\TimeRecordCorrectionRequestService;
use App\Services\TimeRecordService;
use App\Services\WorkSessionAttribution;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 同じ日に2回目の出勤（翌日の勤務に向けた早めの出勤）を打刻できること、
 * およびその勤務が翌日の勤務として集計・表示されることのテスト
 *
 * 例: 0:00〜8:00 のシフトに対して
 *   11/3  0:10 出勤 →  11/3  8:00 退勤（11/3 の勤務）
 *   11/3 23:45 出勤 →  11/4  7:45 退勤（11/4 の勤務）
 */
class NextDayShiftClockInTest extends TestCase
{
    use DatabaseTransactions;

    private StampService $stamp;

    private Company $company;

    private User $user;

    private ShiftPattern $nightPattern;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stamp = app(StampService::class);
        $this->company = Company::factory()->create(['is_closed_on_holidays' => false]);
        $this->user = User::factory()->forCompany($this->company->id)->create(['is_retired' => false]);
        $this->nightPattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '夜勤',
            'start_time' => '00:00',
            'end_time' => '08:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function giveShift(string $date, ?ShiftPattern $pattern = null): void
    {
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $date,
            'shift_pattern_id' => ($pattern ?? $this->nightPattern)->id,
        ]);
    }

    private function at(string $dateTime): void
    {
        CarbonImmutable::setTestNow($dateTime);
    }

    private function record(TimeRecordTypeEnum $type, string $time): TimeRecord
    {
        return TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => $type,
            'record_time' => $time,
            'rounded_time' => $time,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    private function summaryOf(string $date): ?DailyWorkSummary
    {
        return DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->whereDate('work_date', $date)
            ->first();
    }

    /**
     * 11/3 0:10〜8:00 の勤務を、打刻の操作で作る
     */
    private function stampFirstShiftOfNov3(): void
    {
        $this->at('2026-11-03 00:10:00');
        $this->stamp->clockIn($this->company->id, $this->user->id);
        $this->at('2026-11-03 08:00:00');
        $this->stamp->clockOut($this->company->id, $this->user->id);
    }

    /**
     * @test
     */
    public function 同じ日の夜に翌日のシフトへ向けた2回目の出勤を打刻できる(): void
    {
        $this->giveShift('2026-11-03');
        $this->giveShift('2026-11-04');
        $this->stampFirstShiftOfNov3();

        $this->at('2026-11-03 23:45:00');
        $record = $this->stamp->clockIn($this->company->id, $this->user->id);

        $this->assertSame(TimeRecordTypeEnum::WORK_START, $record->record_type);
        $this->assertTrue($this->stamp->getCurrentStatus($this->company->id, $this->user->id)['isWorking']);
    }

    /**
     * @test
     */
    public function 翌日のシフトが登録されていなければ2回目の出勤は従来どおり打刻できない(): void
    {
        $this->giveShift('2026-11-03');
        $this->stampFirstShiftOfNov3();

        $this->at('2026-11-03 23:45:00');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('本日は出勤と退勤の打刻ができております');

        $this->stamp->clockIn($this->company->id, $this->user->id);
    }

    /**
     * @test
     */
    public function 翌日のシフトの開始まで間があれば2回目の出勤は打刻できない(): void
    {
        $this->giveShift('2026-11-03');
        $this->giveShift('2026-11-04', ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '日勤',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
        ]));
        $this->stampFirstShiftOfNov3();

        $this->at('2026-11-03 23:45:00');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('本日は出勤と退勤の打刻ができております');

        $this->stamp->clockIn($this->company->id, $this->user->id);
    }

    /**
     * @test
     */
    public function 設定で受け付ける時間を0にすると2回目の出勤は打刻できない(): void
    {
        config(['attendance.next_day_shift_clock_in_hours' => 0]);
        $this->giveShift('2026-11-03');
        $this->giveShift('2026-11-04');
        $this->stampFirstShiftOfNov3();

        $this->at('2026-11-03 23:45:00');

        $this->expectException(BusinessException::class);

        $this->stamp->clockIn($this->company->id, $this->user->id);
    }

    /**
     * @test
     */
    public function 出勤中に重ねて出勤することはできない(): void
    {
        $this->giveShift('2026-11-03');
        $this->giveShift('2026-11-04');
        $this->stampFirstShiftOfNov3();
        $this->at('2026-11-03 23:45:00');
        $this->stamp->clockIn($this->company->id, $this->user->id);

        $this->at('2026-11-03 23:50:00');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('既に出勤中');

        $this->stamp->clockIn($this->company->id, $this->user->id);
    }

    /**
     * @test
     */
    public function 打刻画面の状態は2回目の出勤ができる時間帯だけ出勤前に戻る(): void
    {
        $this->giveShift('2026-11-03');
        $this->giveShift('2026-11-04');
        $this->stampFirstShiftOfNov3();

        // 昼間は、これまでどおり「退勤済み」
        $this->at('2026-11-03 12:00:00');
        $noon = $this->stamp->getCurrentStatus($this->company->id, $this->user->id);
        $this->assertTrue($noon['hasClockInToday']);
        $this->assertTrue($noon['hasClockOutToday']);

        // 翌日のシフトの開始が迫ったら、出勤前の状態（出勤ボタンが押せる）
        $this->at('2026-11-03 23:30:00');
        $evening = $this->stamp->getCurrentStatus($this->company->id, $this->user->id);
        $this->assertFalse($evening['hasClockInToday']);
        $this->assertFalse($evening['hasClockOutToday']);
        $this->assertFalse($evening['isWorking']);
    }

    /**
     * @test
     */
    public function 二度目の勤務は翌日の勤務実績になり、一度目の勤務実績とつながらない(): void
    {
        $this->giveShift('2026-11-03');
        $this->giveShift('2026-11-04');
        $this->stampFirstShiftOfNov3();
        $this->at('2026-11-03 23:45:00');
        $this->stamp->clockIn($this->company->id, $this->user->id);
        $this->at('2026-11-04 07:45:00');
        $this->stamp->clockOut($this->company->id, $this->user->id);

        $first = $this->summaryOf('2026-11-03');
        $second = $this->summaryOf('2026-11-04');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame('2026-11-03 00:10:00', $first->work_start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 08:00:00', $first->work_end->format('Y-m-d H:i:s'));
        $this->assertSame(470, $first->work_minutes);
        $this->assertFalse($first->is_cross_day);

        $this->assertSame('2026-11-03 23:45:00', $second->work_start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 07:45:00', $second->work_end->format('Y-m-d H:i:s'));
        $this->assertSame(480, $second->work_minutes);
        $this->assertTrue($second->is_cross_day);
    }

    /**
     * @test
     */
    public function 集計はどちらの日から先に実行しても同じ結果になる(): void
    {
        $this->giveShift('2026-11-03');
        $this->giveShift('2026-11-04');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00');
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00');
        $this->record(TimeRecordTypeEnum::BREAK_START, '2026-11-04 03:00:00');
        $this->record(TimeRecordTypeEnum::BREAK_END, '2026-11-04 03:30:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:45:00');

        $batch = app(DailyWorkSummaryBatchService::class);
        $batch->aggregateByUser($this->company, $this->user, CarbonImmutable::parse('2026-11-04'));
        $batch->aggregateByUser($this->company, $this->user, CarbonImmutable::parse('2026-11-03'));

        $first = $this->summaryOf('2026-11-03');
        $second = $this->summaryOf('2026-11-04');

        $this->assertSame(470, $first->work_minutes);
        $this->assertSame(0, $first->break_minutes);
        $this->assertSame(480, $second->work_minutes);
        $this->assertSame(30, $second->break_minutes);
        $this->assertSame(450, $second->net_work_minutes);
        $this->assertSame(2, DailyWorkSummary::query()->where('user_id', $this->user->id)->count());
    }

    /**
     * @test
     */
    public function 帳票に出す打刻時刻は勤務日ごとに分かれる(): void
    {
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00');
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00');
        $this->record(TimeRecordTypeEnum::BREAK_START, '2026-11-04 03:00:00');
        $this->record(TimeRecordTypeEnum::BREAK_END, '2026-11-04 03:30:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:45:00');

        $raw = app(RawStampTimeService::class)
            ->mapByDate($this->company->id, $this->user->id, '2026-11-03', '2026-11-04');

        $this->assertSame(['work_start' => '00:10', 'work_end' => '08:00', 'breaks' => []], $raw['2026-11-03']);
        $this->assertSame(
            ['work_start' => '23:45', 'work_end' => '07:45', 'breaks' => [['start' => '03:00', 'end' => '03:30']]],
            $raw['2026-11-04']
        );
    }

    /**
     * @test
     */
    public function 期間の最初の日に前日から寄せた勤務も帳票に出る(): void
    {
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00');
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:45:00');

        $raw = app(RawStampTimeService::class)
            ->mapByDate($this->company->id, $this->user->id, '2026-11-04', '2026-11-04');

        $this->assertSame(['2026-11-04'], array_keys($raw));
        $this->assertSame('23:45', $raw['2026-11-04']['work_start']);
        $this->assertSame('07:45', $raw['2026-11-04']['work_end']);
    }

    /**
     * @test
     */
    public function 一日一回の夜勤は従来どおり出勤した日の勤務として集計する(): void
    {
        $this->giveShift('2026-11-03');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 22:00:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 06:00:00');

        $batch = app(DailyWorkSummaryBatchService::class);
        $batch->aggregateByUser($this->company, $this->user, CarbonImmutable::parse('2026-11-03'));
        $batch->aggregateByUser($this->company, $this->user, CarbonImmutable::parse('2026-11-04'));

        $summary = $this->summaryOf('2026-11-03');
        $this->assertNotNull($summary);
        $this->assertSame(480, $summary->work_minutes);
        $this->assertTrue($summary->is_cross_day);
        $this->assertNull($this->summaryOf('2026-11-04'));
    }

    /**
     * 2回目の勤務を含む日の打刻を作る（11/3 0:10〜8:00、11/3 23:45〜11/4 7:45）
     */
    private function createDoubleStartDays(): void
    {
        $this->giveShift('2026-11-03');
        $this->giveShift('2026-11-04');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 00:10:00');
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-03 08:00:00');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 23:45:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 07:45:00');

        $batch = app(DailyWorkSummaryBatchService::class);
        $batch->aggregateByUser($this->company, $this->user, CarbonImmutable::parse('2026-11-03'));
        $batch->aggregateByUser($this->company, $this->user, CarbonImmutable::parse('2026-11-04'));
    }

    /**
     * @test
     */
    public function 画面に渡す打刻は2回目の勤務の日付と退勤の種別を勤務日に合わせる(): void
    {
        $this->createDoubleStartDays();
        $records = TimeRecord::query()->where('user_id', $this->user->id)->orderBy('record_time')->get();

        $screen = app(TimeRecordService::class)->toScreenRecords($records)->all();

        $this->assertSame(['2026-11-03', '2026-11-03', '2026-11-04', '2026-11-04'], array_column($screen, 'record_date'));
        $this->assertSame(['00:10', '08:00', '23:45', '07:45'], array_column($screen, 'record_time'));
        $this->assertSame([1, 2, 1, 2], array_map(fn ($r) => $r['record_type']['value'], $screen));
    }

    /**
     * @test
     */
    public function 画面に渡す打刻は、ふつうの日付越えの夜勤を変えない(): void
    {
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-03 22:00:00');
        $this->record(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-04 06:00:00');
        $records = TimeRecord::query()->where('user_id', $this->user->id)->orderBy('record_time')->get();

        $screen = app(TimeRecordService::class)->toScreenRecords($records)->all();

        $this->assertSame(['2026-11-03', '2026-11-04'], array_column($screen, 'record_date'));
        $this->assertSame([1, 3], array_map(fn ($r) => $r['record_type']['value'], $screen));
    }

    /**
     * @test
     */
    public function 二度出勤した日は画面から勤務実績を編集できない(): void
    {
        $this->createDoubleStartDays();
        $before = TimeRecord::query()->where('user_id', $this->user->id)->count();

        foreach (['2026-11-03', '2026-11-04'] as $date) {
            $summary = $this->summaryOf($date);

            try {
                app(DailyWorkSummaryService::class)->updateWorkTimes($summary->id, '09:00', '18:00');
                $this->fail($date.' の編集が止められていません');
            } catch (BusinessException $e) {
                $this->assertSame(WorkSessionAttribution::EDIT_BLOCKED_MESSAGE, $e->getMessage());
            }

            try {
                app(DailyWorkSummaryService::class)->deleteWorkTimes($summary->id);
                $this->fail($date.' の削除が止められていません');
            } catch (BusinessException $e) {
                $this->assertSame(WorkSessionAttribution::EDIT_BLOCKED_MESSAGE, $e->getMessage());
            }
        }

        $this->assertSame($before, TimeRecord::query()->where('user_id', $this->user->id)->count());
    }

    /**
     * @test
     */
    public function 二度出勤した日は打刻間違いの申請もできない(): void
    {
        $this->createDoubleStartDays();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('日付をまたいで同じ日に2回出勤した勤務を含む');

        app(TimeRecordCorrectionRequestService::class)->createClockErrorRequest(
            $this->company->id,
            $this->user->id,
            ['target_date' => '2026-11-04', 'reason' => '修正', 'start_time' => '23:40', 'end_time' => '07:50']
        );
    }

    /**
     * @test
     */
    public function 二度出勤していない日はこれまでどおり編集できる(): void
    {
        $this->createDoubleStartDays();
        $this->giveShift('2026-11-10');
        $this->record(TimeRecordTypeEnum::WORK_START, '2026-11-10 09:00:00');
        $this->record(TimeRecordTypeEnum::WORK_END, '2026-11-10 18:00:00');
        app(DailyWorkSummaryBatchService::class)->aggregateByUser($this->company, $this->user, CarbonImmutable::parse('2026-11-10'));
        $summary = $this->summaryOf('2026-11-10');

        $updated = app(DailyWorkSummaryService::class)->updateWorkTimes($summary->id, '09:30', '18:00');

        $this->assertSame('2026-11-10 09:30:00', $updated->work_start->format('Y-m-d H:i:s'));
    }
}

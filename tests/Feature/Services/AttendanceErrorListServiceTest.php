<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\AttendanceErrorListService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 勤務実績の出力に付けるエラーリスト（休憩入力無し・打刻漏れ）。
 *
 * クライアント要望: 休憩の自動入力はシフトのとおりが正しい。ただし休日に出勤した日などは
 * 休憩を手で入れる必要があるため、「休憩入力無しリスト」と「打刻漏れリスト」を
 * 勤務実績の出力で出してほしい。
 *
 * クライアント回答:
 * - 休憩入力無しリストは、労働時間が一定以上の日に絞る条件を指定できるようにする。
 * - 打刻漏れは、出勤もしくは退勤が無い日（休憩が片方）。両方無いのは欠勤とみなしてよい。
 * - 打刻漏れリストは、出勤時刻と退勤時刻を備考欄ではなく列として表示する。
 */
class AttendanceErrorListServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const START = '2026-09-01';

    private const END = '2026-09-30';

    private Company $company;

    private User $user;

    private ShiftPattern $pattern;

    private AttendanceErrorListService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->forCompany($this->company->id)->create([
            'name' => '看護 花子',
            'employee_code' => '000123',
        ]);
        $this->pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => 'A日勤',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
            'auto_fill_break' => true,
        ]);
        $this->service = app(AttendanceErrorListService::class);
    }

    /**
     * @param  array<int, string>  $lists
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function build(array $lists, ?User $user = null, string $now = '2026-10-05 10:00:00'): array
    {
        return $this->service->build(
            $this->company->id,
            new Collection([$user ?? $this->user]),
            self::START,
            self::END,
            $lists,
            CarbonImmutable::parse($now),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function summary(string $date, array $overrides = []): DailyWorkSummary
    {
        return DailyWorkSummary::query()->create($overrides + [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $date,
            'work_start' => $date.' 09:00:00',
            'work_end' => $date.' 18:00:00',
            'work_minutes' => 540,
            'break_minutes' => 0,
            'net_work_minutes' => 540,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    private function stamp(TimeRecordTypeEnum $type, string $time, ?User $user = null): void
    {
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => ($user ?? $this->user)->id,
            'record_type' => $type,
            'record_time' => $time,
            'rounded_time' => $time,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    private function shift(string $date): void
    {
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => $date,
            'shift_pattern_id' => $this->pattern->id,
        ]);
    }

    /**
     * @test
     */
    public function 休憩が0分の日は休憩入力無しリストに載る(): void
    {
        $this->summary('2026-09-06'); // シフトの無い日（日曜）に出勤

        $rows = $this->build([AttendanceErrorListService::LIST_NO_BREAK])[AttendanceErrorListService::LIST_NO_BREAK];

        $this->assertCount(1, $rows);
        $this->assertSame('000123', $rows[0]['employee_code']);
        $this->assertSame('看護 花子', $rows[0]['name']);
        $this->assertSame('2026-09-06', $rows[0]['date']);
        $this->assertSame('日', $rows[0]['weekday']);
        $this->assertSame('シフトなし', $rows[0]['shift']);
        $this->assertSame('09:00', $rows[0]['work_start']);
        $this->assertSame('18:00', $rows[0]['work_end']);
        $this->assertSame(540, $rows[0]['work_minutes']);
    }

    /**
     * @test
     */
    public function シフトがあれば休憩入力無しリストにシフト名を出す(): void
    {
        $this->shift('2026-09-07');
        $this->summary('2026-09-07');

        $rows = $this->build([AttendanceErrorListService::LIST_NO_BREAK])[AttendanceErrorListService::LIST_NO_BREAK];

        $this->assertSame('A日勤', $rows[0]['shift']);
    }

    /**
     * @test
     */
    public function 休憩が入っている日と出退勤が揃っていない日と休暇の日は載らない(): void
    {
        $this->summary('2026-09-07', ['break_minutes' => 60, 'net_work_minutes' => 480]);
        $this->summary('2026-09-08', ['work_end' => null, 'work_minutes' => 0, 'net_work_minutes' => null]);
        $this->summary('2026-09-09', [
            'work_start' => null, 'work_end' => null, 'work_minutes' => 0, 'net_work_minutes' => 0,
            'leave_type' => LeaveTypeEnum::PAID_LEAVE, 'record_source' => RecordSourceEnum::REQUEST,
        ]);

        $rows = $this->build([AttendanceErrorListService::LIST_NO_BREAK])[AttendanceErrorListService::LIST_NO_BREAK];

        $this->assertSame([], $rows);
    }

    /**
     * @test
     */
    public function 日付越えの勤務は退勤に翌日の印が付く(): void
    {
        $this->summary('2026-09-10', [
            'work_start' => '2026-09-10 22:00:00',
            'work_end' => '2026-09-11 06:00:00',
            'is_cross_day' => true,
        ]);

        $rows = $this->build([AttendanceErrorListService::LIST_NO_BREAK])[AttendanceErrorListService::LIST_NO_BREAK];

        $this->assertTrue($rows[0]['is_cross_day']);
        $this->assertSame('06:00', $rows[0]['work_end']);
    }

    /**
     * @test
     */
    public function 指定したリストだけを作る(): void
    {
        $this->summary('2026-09-06');

        $result = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP]);

        $this->assertSame([AttendanceErrorListService::LIST_MISSING_STAMP], array_keys($result));
    }

    /**
     * @test
     */
    public function 退勤の打刻がない日は打刻漏れリストに載る(): void
    {
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-08 09:00:00');
        $this->summary('2026-09-08', ['work_end' => null, 'work_minutes' => 0, 'net_work_minutes' => null]);

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertCount(1, $rows);
        $this->assertSame('2026-09-08', $rows[0]['date']);
        $this->assertSame('火', $rows[0]['weekday']);
        $this->assertSame('退勤の打刻なし', $rows[0]['issue']);
        $this->assertSame('勤務開始 09:00', $rows[0]['stamps']);
    }

    /**
     * @test
     */
    public function 休憩終了の打刻がない日は打刻漏れリストに載る(): void
    {
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-08 09:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '2026-09-08 12:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_END, '2026-09-08 18:00:00');
        $this->summary('2026-09-08');

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertSame('休憩終了の打刻なし', $rows[0]['issue']);
        $this->assertSame('勤務開始 09:00、休憩開始 12:00、勤務終了 18:00', $rows[0]['stamps']);
    }

    /**
     * @test
     *
     * クライアント回答: 出勤も退勤も無い日は欠勤とみなしてよい。シフトがあっても載せない。
     */
    public function 出勤も退勤も打刻がない日はシフトがあっても載らない(): void
    {
        $this->shift('2026-09-10');
        $this->shift('2026-09-11');
        $this->summary('2026-09-11', [
            'work_start' => null, 'work_end' => null, 'work_minutes' => 0, 'net_work_minutes' => 0,
            'leave_type' => LeaveTypeEnum::PAID_LEAVE, 'record_source' => RecordSourceEnum::REQUEST,
        ]);

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertSame([], $rows);
    }

    /**
     * @test
     */
    public function 退勤の打刻だけがある日は出勤の打刻なしとして載り_退勤時刻が列に出る(): void
    {
        $this->stamp(TimeRecordTypeEnum::WORK_END, '2026-09-16 20:25:00');

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertCount(1, $rows);
        $this->assertSame('出勤の打刻なし', $rows[0]['issue']);
        $this->assertSame('', $rows[0]['clock_in']);
        $this->assertSame('20:25', $rows[0]['clock_out']);
        $this->assertSame('勤務終了 20:25', $rows[0]['stamps']);
    }

    /**
     * @test
     */
    public function 休憩開始の打刻がない日は打刻漏れリストに載り_出勤と退勤の時刻が列に出る(): void
    {
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-08 09:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_END, '2026-09-08 13:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_END, '2026-09-08 18:00:00');
        $this->summary('2026-09-08');

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertSame('休憩開始の打刻なし', $rows[0]['issue']);
        $this->assertSame('09:00', $rows[0]['clock_in']);
        $this->assertSame('18:00', $rows[0]['clock_out']);
    }

    /**
     * @test
     */
    public function 退勤の打刻がない日は出勤時刻だけが列に出る(): void
    {
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-08 09:00:00');
        $this->summary('2026-09-08', ['work_end' => null, 'net_work_minutes' => null]);

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertSame('09:00', $rows[0]['clock_in']);
        $this->assertSame('', $rows[0]['clock_out']);
    }

    /**
     * @test
     *
     * 日付をまたぐ勤務は、前日の出勤・翌日の退勤であることが分かるように「前」「翌」を付ける。
     */
    public function 日付をまたぐ勤務の出勤と退勤の時刻には前と翌の印が付く(): void
    {
        // 9/10 22:00出勤 → 9/11 01:00に休憩開始（終了なし）→ 9/11 06:00退勤
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-10 22:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '2026-09-11 01:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-09-11 06:00:00');
        // 9/20 22:00出勤 → 9/21 06:00退勤、ただし休憩終了だけが9/20にある
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-20 22:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '2026-09-20 23:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-09-21 06:00:00');

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertSame(['2026-09-11', '2026-09-20'], array_column($rows, 'date'));
        $this->assertSame('休憩終了の打刻なし', $rows[0]['issue']);
        $this->assertSame('前22:00', $rows[0]['clock_in']); // 休憩の打刻がある日（9/11）は、前日に出勤した勤務
        $this->assertSame('06:00', $rows[0]['clock_out']);
        $this->assertSame('22:00', $rows[1]['clock_in']);
        $this->assertSame('翌06:00', $rows[1]['clock_out']);
    }

    /**
     * @test
     */
    public function 当日分は打刻漏れリストに載らない(): void
    {
        $this->shift('2026-09-30');
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-30 09:00:00');

        $rows = $this->build(
            [AttendanceErrorListService::LIST_MISSING_STAMP],
            now: '2026-09-30 15:00:00'
        )[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertSame([], $rows);
    }

    /**
     * @test
     *
     * 期間の最初の日に前日からの夜勤の退勤が見えても、期間の最後の日に夜勤を始めて
     * 退勤が翌日（期間の外）になっても、打刻漏れとはしない。
     */
    public function 期間の境目をまたぐ夜勤は打刻漏れにしない(): void
    {
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-08-31 22:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-09-01 06:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-30 22:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-01 06:00:00');
        // 勤務実績が未集計でも、打刻の対応関係だけで正しく判定できる。
        // 期間の前日（8/31）の出勤と、期間の最後の日（9/30）の退勤が期間の外でも対応する

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertSame([], $rows);
    }

    /**
     * @test
     */
    public function 期間の外の打刻漏れは載らない(): void
    {
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-08-20 09:00:00');
        $this->summary('2026-08-20', ['work_end' => null, 'net_work_minutes' => null]);
        $this->summary('2026-08-25'); // 期間の外の休憩無しも載らない

        $result = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP, AttendanceErrorListService::LIST_NO_BREAK]);

        $this->assertSame([], $result[AttendanceErrorListService::LIST_MISSING_STAMP]);
        $this->assertSame([], $result[AttendanceErrorListService::LIST_NO_BREAK]);
    }

    /**
     * @test
     */
    public function 同じ日に複数の問題があればまとめて内容に並べ_日付順に並べる(): void
    {
        $this->stamp(TimeRecordTypeEnum::WORK_START, '2026-09-12 09:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '2026-09-12 12:00:00');
        $this->summary('2026-09-12', ['work_end' => null, 'net_work_minutes' => null]);
        $this->stamp(TimeRecordTypeEnum::WORK_END, '2026-09-03 18:00:00');

        $rows = $this->build([AttendanceErrorListService::LIST_MISSING_STAMP])[AttendanceErrorListService::LIST_MISSING_STAMP];

        $this->assertSame(['2026-09-03', '2026-09-12'], array_column($rows, 'date'));
        $this->assertSame('退勤の打刻なし、休憩終了の打刻なし', $rows[1]['issue']);
    }

    /**
     * @test
     *
     * クライアント回答: 勤務時間が一定以上に絞るなら、時間を条件指定できるようにする。
     */
    public function 休憩入力無しリストは労働時間の下限で絞り込める(): void
    {
        $this->summary('2026-09-02', ['work_minutes' => 240, 'net_work_minutes' => 240]); // 4:00
        $this->summary('2026-09-03', ['work_minutes' => 360, 'net_work_minutes' => 360]); // 6:00ちょうど
        $this->summary('2026-09-04', ['work_minutes' => 540, 'net_work_minutes' => 540]); // 9:00

        $all = $this->build([AttendanceErrorListService::LIST_NO_BREAK])[AttendanceErrorListService::LIST_NO_BREAK];
        $sixHours = $this->service->build(
            $this->company->id,
            new Collection([$this->user]),
            self::START,
            self::END,
            [AttendanceErrorListService::LIST_NO_BREAK],
            CarbonImmutable::parse('2026-10-05 10:00:00'),
            360,
        )[AttendanceErrorListService::LIST_NO_BREAK];

        $this->assertSame(['2026-09-02', '2026-09-03', '2026-09-04'], array_column($all, 'date'));
        $this->assertSame(['2026-09-03', '2026-09-04'], array_column($sixHours, 'date'));
    }

    /**
     * @test
     */
    public function 対象の従業員だけを出力し_従業員の並びを保つ(): void
    {
        $other = User::factory()->forCompany($this->company->id)->create(['name' => '別の人', 'employee_code' => '000999']);
        $this->summary('2026-09-06');
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $other->id,
            'work_date' => '2026-09-06',
            'work_start' => '2026-09-06 10:00:00',
            'work_end' => '2026-09-06 15:00:00',
            'work_minutes' => 300,
            'break_minutes' => 0,
            'net_work_minutes' => 300,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $both = $this->service->build(
            $this->company->id,
            new Collection([$other, $this->user]),
            self::START,
            self::END,
            [AttendanceErrorListService::LIST_NO_BREAK],
            CarbonImmutable::parse('2026-10-05 10:00:00'),
        )[AttendanceErrorListService::LIST_NO_BREAK];

        $this->assertSame(['別の人', '看護 花子'], array_column($both, 'name'));

        $onlyOther = $this->build([AttendanceErrorListService::LIST_NO_BREAK], $other)[AttendanceErrorListService::LIST_NO_BREAK];
        $this->assertSame(['別の人'], array_column($onlyOther, 'name'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TimeRecordTypeEnum;
use App\Models\TimeRecord;
use App\Services\AttendanceIssueService;
use Tests\TestCase;

/**
 * 勤務実績の要対応状態の検出。
 */
class AttendanceIssueServiceTest extends TestCase
{
    private AttendanceIssueService $service;

    private const TODAY = '2026-09-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceIssueService;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, string>>
     */
    private function detect(array $rows): array
    {
        return $this->service->detect(collect($rows), self::TODAY);
    }

    /**
     * @test
     */
    public function 出退勤が揃っていて集計済みなら何も検出しない(): void
    {
        $issues = $this->detect([
            ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => '18:00', 'net_work_minutes' => 480],
        ]);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 出退勤が揃っているのに未集計の日を検出する(): void
    {
        $issues = $this->detect([
            ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => '18:00', 'net_work_minutes' => null],
        ]);

        $this->assertSame(
            ['2026-09-08' => [AttendanceIssueService::NOT_CALCULATED]],
            $issues,
        );
    }

    /**
     * @test
     */
    public function 労働時間が0でも集計済みとして扱う(): void
    {
        // 全休など、労働時間が0分になるケースを未集計と誤判定しない
        $issues = $this->detect([
            ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => '09:00', 'net_work_minutes' => 0],
        ]);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 出勤自体がない日は検出しない(): void
    {
        // 休日や欠勤を要対応として扱わない
        $issues = $this->detect([
            ['work_date' => '2026-09-08', 'work_start' => null, 'work_end' => null, 'net_work_minutes' => null],
        ]);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 未来日は検出しない(): void
    {
        $issues = $this->detect([
            ['work_date' => '2026-09-20', 'work_start' => '09:00', 'work_end' => '18:00', 'net_work_minutes' => null],
        ]);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 日時形式の日付でも判定できる(): void
    {
        $issues = $this->detect([
            ['work_date' => '2026-09-08 00:00:00', 'work_start' => '09:00', 'work_end' => '18:00', 'net_work_minutes' => null],
        ]);

        $this->assertArrayHasKey('2026-09-08', $issues);
    }

    /**
     * @test
     */
    public function オブジェクトでも判定できる(): void
    {
        $issues = $this->service->detect(
            collect([(object) ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => '18:00', 'net_work_minutes' => null]]),
            self::TODAY,
        );

        $this->assertArrayHasKey('2026-09-08', $issues);
    }

    /**
     * @test
     */
    public function 複数日をまとめて検出する(): void
    {
        $issues = $this->detect([
            ['work_date' => '2026-09-07', 'work_start' => '09:00', 'work_end' => '18:00', 'net_work_minutes' => null],
            ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => '18:00', 'net_work_minutes' => 480],
            ['work_date' => '2026-09-09', 'work_start' => '09:00', 'work_end' => '18:00', 'net_work_minutes' => null],
        ]);

        $this->assertSame(['2026-09-07', '2026-09-09'], array_keys($issues));
    }

    private function timeRecord(TimeRecordTypeEnum $type, string $recordTime): TimeRecord
    {
        return new TimeRecord([
            'record_type' => $type,
            'record_time' => $recordTime,
        ]);
    }

    /**
     * @test
     */
    public function 休憩開始のみで終了がない日を検出する(): void
    {
        $issues = $this->service->detectMissingBreakEnd(collect([
            $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-08 12:00:00'),
        ]), self::TODAY);

        $this->assertSame(
            ['2026-09-08' => [AttendanceIssueService::MISSING_BREAK_END]],
            $issues,
        );
    }

    /**
     * @test
     */
    public function 休憩開始と終了が揃っていれば検出しない(): void
    {
        $issues = $this->service->detectMissingBreakEnd(collect([
            $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-08 12:00:00'),
            $this->timeRecord(TimeRecordTypeEnum::BREAK_END, '2026-09-08 13:00:00'),
        ]), self::TODAY);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 複数回休憩していても終了が揃っていれば検出しない(): void
    {
        $issues = $this->service->detectMissingBreakEnd(collect([
            $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-08 10:00:00'),
            $this->timeRecord(TimeRecordTypeEnum::BREAK_END, '2026-09-08 10:15:00'),
            $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-08 12:00:00'),
            $this->timeRecord(TimeRecordTypeEnum::BREAK_END, '2026-09-08 13:00:00'),
        ]), self::TODAY);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 休憩の当日分は検出しない(): void
    {
        $issues = $this->service->detectMissingBreakEnd(collect([
            $this->timeRecord(TimeRecordTypeEnum::BREAK_START, self::TODAY.' 12:00:00'),
        ]), self::TODAY);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     *
     * 休憩終了が日付をまたいだ場合、日付ごとの単純な件数比較では
     * 休憩開始側の日付が終了0件に見えて誤検出してしまう
     * （クライアント報告: 休憩打刻しているのに休憩漏れと表示される）。
     */
    public function 休憩終了が日付をまたいでいれば検出しない(): void
    {
        $issues = $this->service->detectMissingBreakEnd(collect([
            $this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-08 09:39:00'),
            $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-08 20:53:00'),
            $this->timeRecord(TimeRecordTypeEnum::BREAK_END, '2026-09-09 09:05:00'),
            $this->timeRecord(TimeRecordTypeEnum::WORK_END, '2026-09-09 09:06:00'),
        ]), self::TODAY);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     *
     * 日付をまたぐ休憩でも、本当に終了打刻がなければ引き続き検出する。
     */
    public function 休憩終了が日付をまたいでいなくても本当に終了がなければ検出する(): void
    {
        $issues = $this->service->detectMissingBreakEnd(collect([
            $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-08 20:53:00'),
            $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-09 08:00:00'),
            $this->timeRecord(TimeRecordTypeEnum::BREAK_END, '2026-09-09 08:30:00'),
        ]), self::TODAY);

        $this->assertSame(
            ['2026-09-08' => [AttendanceIssueService::MISSING_BREAK_END]],
            $issues,
        );
    }

    /**
     * @test
     */
    public function 退勤忘れと休憩打刻漏れをまとめて検出する(): void
    {
        $issues = $this->service->detectAll(
            collect([
                ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => null, 'net_work_minutes' => null],
            ]),
            collect([
                $this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-08 09:00:00'),
                $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-08 12:00:00'),
            ]),
            self::TODAY,
        );

        $this->assertSame(
            ['2026-09-08' => [AttendanceIssueService::MISSING_CLOCK_OUT, AttendanceIssueService::MISSING_BREAK_END]],
            $issues,
        );
    }

    /**
     * @test
     *
     * detect()はDailyWorkSummary（バッチ集計済み）だけを見るため、打刻直後で
     * まだ集計されていない日（summaries に該当行がない）は拾えない。
     * detectMissingClockOutは打刻データから直接判定するため、集計を
     * 待たずに退勤忘れを検出できる。
     */
    public function 集計前でも打刻データから退勤忘れを検出する(): void
    {
        $issues = $this->service->detectMissingClockOut(collect([
            $this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-08 10:44:00'),
        ]), collect(), self::TODAY);

        $this->assertSame(
            ['2026-09-08' => [AttendanceIssueService::MISSING_CLOCK_OUT]],
            $issues,
        );
    }

    /**
     * @test
     */
    public function 打刻データで出退勤が揃っていれば検出しない(): void
    {
        $issues = $this->service->detectMissingClockOut(collect([
            $this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-08 09:00:00'),
            $this->timeRecord(TimeRecordTypeEnum::WORK_END, '2026-09-08 18:00:00'),
        ]), collect(), self::TODAY);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     *
     * 日付越え退勤(WORK_END_NEXT_DAY)は翌日の日付で記録されるが、
     * 出勤日の退勤忘れとして誤検出してはいけない。
     */
    public function 日付越え退勤があれば検出しない(): void
    {
        $issues = $this->service->detectMissingClockOut(collect([
            $this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-08 22:00:00'),
            $this->timeRecord(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-09-09 06:00:00'),
        ]), collect(), self::TODAY);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 打刻データでも当日は検出しない(): void
    {
        $issues = $this->service->detectMissingClockOut(collect([
            $this->timeRecord(TimeRecordTypeEnum::WORK_START, self::TODAY.' 09:00:00'),
        ]), collect(), self::TODAY);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     *
     * 退勤忘れの翌日以降に別の出勤・退勤があると、その後日の退勤を
     * 「この出勤より後に退勤打刻がある」というだけで誤って一致させて
     * しまい、実際には退勤忘れの日を見逃す不具合の回帰テスト。
     * 本番で桜本真治の9/5に実際に起きていたパターン
     * （9/5出勤・退勤なし → 9/6出勤・退勤あり）。
     */
    public function 退勤忘れの翌日に別の出退勤があっても見逃さない(): void
    {
        $issues = $this->service->detectMissingClockOut(collect([
            $this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-05 10:44:00'),
            $this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-06 09:00:00'),
            $this->timeRecord(TimeRecordTypeEnum::WORK_END, '2026-09-06 18:00:00'),
        ]), collect(), self::TODAY);

        $this->assertSame(
            ['2026-09-05' => [AttendanceIssueService::MISSING_CLOCK_OUT]],
            $issues,
        );
    }

    /**
     * @test
     *
     * 日付が変わった直後は、出勤からまだ24時間（打刻し忘れとみなして
     * セッションを打ち切る基準時間 = attendance.work_session_max_hours）
     * 経っていないことがある。日跨ぎ夜勤の途中を退勤忘れと誤検出しない
     * ように、経過時間で判定する（前田9/7の09:14出勤について、9/8に
     * なった直後に退勤忘れ表示が出てしまっていた不具合の回帰テスト）。
     */
    public function 出勤から24時間経っていなければ検出しない(): void
    {
        $issues = $this->service->detectMissingClockOut(
            collect([$this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-07 09:14:00')]),
            collect(),
            '2026-09-08 03:00:00', // 出勤から17時間45分しか経っていない
        );

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 出勤から24時間経てば検出する(): void
    {
        $issues = $this->service->detectMissingClockOut(
            collect([$this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-07 09:14:00')]),
            collect(),
            '2026-09-08 10:00:00', // 出勤から24時間46分経過
        );

        $this->assertSame(
            ['2026-09-07' => [AttendanceIssueService::MISSING_CLOCK_OUT]],
            $issues,
        );
    }

    /**
     * @test
     *
     * 打刻データ上は開いたまま（対応する退勤が見当たらない）に見えても、
     * 勤務実績（DailyWorkSummary）側で既にその日の退勤時刻が確定して
     * いれば検出しない。日付越え退勤を含む日を後から修正した際、前日側の
     * 集計が再実行されずに残るなどして打刻データと集計結果が一時的に
     * 食い違うことがあり、その場合に確定済みの表示と矛盾する退勤忘れ
     * 警告を出さないようにするため（前田9/2の回帰テスト。表示上は
     * 21:48〜09:15(翌)の勤務時間が確定しているのに、退勤忘れバッジが
     * 出てしまっていた）。
     */
    public function 勤務実績で退勤が確定していれば打刻データ上は開いていても検出しない(): void
    {
        $issues = $this->service->detectMissingClockOut(
            collect([$this->timeRecord(TimeRecordTypeEnum::WORK_START, '2026-09-02 21:48:00')]),
            collect([
                ['work_date' => '2026-09-02', 'work_start' => '21:48', 'work_end' => '09:15', 'net_work_minutes' => 450],
            ]),
            self::TODAY,
        );

        $this->assertSame([], $issues);
    }
}

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
    public function 退勤打刻がない日を検出する(): void
    {
        $issues = $this->detect([
            ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => null, 'net_work_minutes' => null],
        ]);

        $this->assertSame(
            ['2026-09-08' => [AttendanceIssueService::MISSING_CLOCK_OUT]],
            $issues,
        );
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
    public function 当日は勤務の途中とみなして検出しない(): void
    {
        // 出勤済みで未退勤でも、当日なら退勤忘れではない
        $issues = $this->detect([
            ['work_date' => self::TODAY, 'work_start' => '09:00', 'work_end' => null, 'net_work_minutes' => null],
        ]);

        $this->assertSame([], $issues);
    }

    /**
     * @test
     */
    public function 未来日は検出しない(): void
    {
        $issues = $this->detect([
            ['work_date' => '2026-09-20', 'work_start' => '09:00', 'work_end' => null, 'net_work_minutes' => null],
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
    public function 日時形式の日付でも判定できる(): void
    {
        $issues = $this->detect([
            ['work_date' => '2026-09-08 00:00:00', 'work_start' => '09:00', 'work_end' => null],
        ]);

        $this->assertArrayHasKey('2026-09-08', $issues);
    }

    /**
     * @test
     */
    public function オブジェクトでも判定できる(): void
    {
        $issues = $this->service->detect(
            collect([(object) ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => null]]),
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
            ['work_date' => '2026-09-07', 'work_start' => '09:00', 'work_end' => null],
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
     */
    public function 退勤忘れと休憩打刻漏れをまとめて検出する(): void
    {
        $issues = $this->service->detectAll(
            collect([
                ['work_date' => '2026-09-08', 'work_start' => '09:00', 'work_end' => null, 'net_work_minutes' => null],
            ]),
            collect([
                $this->timeRecord(TimeRecordTypeEnum::BREAK_START, '2026-09-08 12:00:00'),
            ]),
            self::TODAY,
        );

        $this->assertSame(
            ['2026-09-08' => [AttendanceIssueService::MISSING_CLOCK_OUT, AttendanceIssueService::MISSING_BREAK_END]],
            $issues,
        );
    }
}

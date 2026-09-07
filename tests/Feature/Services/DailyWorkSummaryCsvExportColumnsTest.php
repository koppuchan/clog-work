<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Request;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\DailyWorkSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 勤務実績CSV出力の項目を、帳票（Excel）と揃える対応の検証。
 *
 * 「コード・氏名・日付〜備考/申請」という並びで、シフト・休憩(2枠)・
 * 承認済み申請から得られる備考/申請列まで、帳票と同じ項目を出力する。
 */
class DailyWorkSummaryCsvExportColumnsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    private DailyWorkSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['daily_working_hours' => 8.0]);
        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create(['name' => '出力 花子', 'employee_code' => '000123']);
        $this->service = app(DailyWorkSummaryService::class);
    }

    /**
     * @test
     */
    public function csv_header_matches_excel_column_order(): void
    {
        $csv = $this->service->generateCsv(
            $this->company->id,
            $this->user->id,
            '2026-06-24',
            '2026-06-24',
            $this->user,
        );

        $header = str_getcsv(explode("\n", $csv)[0]);

        $this->assertSame([
            'コード', '氏名', '日付', '勤務区分', 'シフト開始', 'シフト終了',
            'シフト休憩入', 'シフト休憩出', '出勤時刻', '退勤時刻',
            '休憩入①', '休憩出①', '休憩入②', '休憩出②',
            '労働時間', '時間外', '休日', '深夜', '遅刻早退', '備考/申請',
        ], $header);
    }

    /**
     * @test
     *
     * シフト・実打刻・休憩2枠が、帳票と同じ列にそれぞれ出力されることを確認する。
     */
    public function csv_row_includes_code_shift_and_two_break_periods(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 420,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        foreach ([
            [TimeRecordTypeEnum::WORK_START, '09:00:00'],
            [TimeRecordTypeEnum::BREAK_START, '12:00:00'],
            [TimeRecordTypeEnum::BREAK_END, '12:30:00'],
            [TimeRecordTypeEnum::BREAK_START, '15:00:00'],
            [TimeRecordTypeEnum::BREAK_END, '15:10:00'],
            [TimeRecordTypeEnum::WORK_END, '18:00:00'],
        ] as [$type, $time]) {
            TimeRecord::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'record_type' => $type,
                'record_time' => '2026-06-24 '.$time,
                'rounded_time' => '2026-06-24 '.$time,
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        }

        $csv = $this->service->generateCsv(
            $this->company->id,
            $this->user->id,
            '2026-06-24',
            '2026-06-24',
            $this->user,
        );
        $row = str_getcsv(array_values(array_filter(
            explode("\n", $csv),
            fn ($line) => str_contains($line, '6/24(水)')
        ))[0]);

        $this->assertSame('000123', $row[0]); // コード
        $this->assertSame('出力 花子', $row[1]); // 氏名
        $this->assertSame('6/24(水)', $row[2]); // 日付
        $this->assertSame('09:00', $row[4]); // シフト開始
        $this->assertSame('18:00', $row[5]); // シフト終了
        $this->assertSame('09:00', $row[8]); // 出勤時刻（実打刻）
        $this->assertSame('18:00', $row[9]); // 退勤時刻（実打刻）
        $this->assertSame('12:00', $row[10]); // 休憩入①
        $this->assertSame('12:30', $row[11]); // 休憩出①
        $this->assertSame('15:00', $row[12]); // 休憩入②
        $this->assertSame('15:10', $row[13]); // 休憩出②
        $this->assertSame('7:00', $row[14]); // 労働時間
    }

    /**
     * @test
     *
     * 備考/申請列は、帳票と同じく承認済み申請から日数・時間を算出して出力する。
     */
    public function csv_note_column_shows_approved_paid_leave_like_excel(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'leave_type' => \App\Enums\LeaveTypeEnum::PAID_LEAVE,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 1, // 有給休暇
            'target_date' => '2026-06-24',
            'reason' => '私用のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        $csv = $this->service->generateCsv(
            $this->company->id,
            $this->user->id,
            '2026-06-24',
            '2026-06-24',
            $this->user,
        );
        $row = str_getcsv(array_values(array_filter(
            explode("\n", $csv),
            fn ($line) => str_contains($line, '6/24(水)')
        ))[0]);

        $this->assertSame('有給休暇 1.0', $row[19]); // 備考/申請
    }

    /**
     * @test
     *
     * 残業申請は承認しても daily_work_summaries.overtime_minutes を書き換えない設計
     * (OvertimeApplicationService::applyOvertimeToWorkSummary が無効化されている)。
     * そのため overtime_minutes が0のままでも、申請自体の開始・終了時刻から
     * 残業時間を算出して備考/申請列に出力する。
     */
    public function csv_note_column_shows_overtime_request_hours_even_when_summary_overtime_is_zero(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => '2026-06-24 09:00:00',
            'work_end' => '2026-06-24 18:00:00',
            'net_work_minutes' => 480,
            'overtime_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 7, // 残業申請
            'target_date' => '2026-06-24',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => '月次締め作業のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        $csv = $this->service->generateCsv(
            $this->company->id,
            $this->user->id,
            '2026-06-24',
            '2026-06-24',
            $this->user,
        );
        $row = str_getcsv(array_values(array_filter(
            explode("\n", $csv),
            fn ($line) => str_contains($line, '6/24(水)')
        ))[0]);

        $this->assertSame('残業申請 2H', $row[19]); // 備考/申請
    }
}

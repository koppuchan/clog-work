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
use App\Services\AttendanceExcelExportService;
use App\Services\DailyWorkSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 休憩の自動入力で補われた休憩が、CSV・Excelの出力に反映される検証。
 *
 * クライアント報告: 休憩の自動入力で計算はされていますが、出力時に
 * 反映されていないようでした。画面は休憩の打刻が無い日にシフトの休憩を
 * 薄く表示し、集計も休憩時間を控除しているが、CSV・Excelは実打刻しか
 * 出しておらず、休憩の欄が空欄のまま労働時間だけ休憩分が引かれていた。
 */
class AutoBreakOutputTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    private string $generatedPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['daily_working_hours' => 8.0]);
        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create(['name' => '休憩 太郎', 'employee_code' => '000321']);
    }

    protected function tearDown(): void
    {
        if ($this->generatedPath !== '' && file_exists($this->generatedPath)) {
            unlink($this->generatedPath);
        }

        parent::tearDown();
    }

    private function givenWorkedDay(bool $autoFillBreak, int $breakMinutes = 45): void
    {
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '1係',
            'start_time' => '08:30',
            'end_time' => '17:15',
            'work_minutes' => 465,
            'break_mode' => 1,
            'break_minutes' => 45,
            'break_start' => '12:00',
            'break_end' => '12:45',
            'auto_fill_break' => $autoFillBreak,
        ]);
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_date' => '2026-06-24',
            'shift_pattern_id' => $pattern->id,
        ]);

        // 集計には丸め後の時刻（5分単位）が入る
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'scheduled_start_time' => '08:30:00',
            'scheduled_end_time' => '17:15:00',
            'work_start' => '2026-06-24 08:00:00',
            'work_end' => '2026-06-24 19:50:00',
            'break_minutes' => $breakMinutes,
            'net_work_minutes' => 665,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $this->stamp(TimeRecordTypeEnum::WORK_START, '07:58:02');
        $this->stamp(TimeRecordTypeEnum::WORK_END, '19:53:38');
    }

    private function stamp(TimeRecordTypeEnum $type, string $time): void
    {
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => $type,
            'record_time' => '2026-06-24 '.$time,
            'rounded_time' => '2026-06-24 '.$time,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function csvRow(): array
    {
        $csv = app(DailyWorkSummaryService::class)->generateCsv(
            $this->company->id,
            $this->user->id,
            '2026-06-24',
            '2026-06-24',
            $this->user,
        );

        return str_getcsv(array_values(array_filter(
            explode("\n", $csv),
            fn ($line) => str_contains($line, '6/24(水)')
        ))[0]);
    }

    private function excelSheet(): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $this->generatedPath = app(AttendanceExcelExportService::class)->generate(
            $this->company->id,
            $this->user,
            '2026-06-21',
            '2026-07-20',
        );

        return IOFactory::load($this->generatedPath)->getActiveSheet();
    }

    /**
     * @test
     */
    public function csv_shows_auto_filled_break_when_no_break_stamps(): void
    {
        $this->givenWorkedDay(autoFillBreak: true);

        $row = $this->csvRow();

        $this->assertSame('12:00', $row[10]); // 休憩入①
        $this->assertSame('12:45', $row[11]); // 休憩出①
        $this->assertSame('', $row[12]); // 休憩入②
    }

    /**
     * @test
     */
    public function excel_shows_auto_filled_break_when_no_break_stamps(): void
    {
        $this->givenWorkedDay(autoFillBreak: true);

        $sheet = $this->excelSheet();

        // 6/24 は 6/21 開始の期間の4日目（7行目起点で10行目）
        $this->assertSame('12:00', (string) $sheet->getCell('I10')->getValue());
        $this->assertSame('12:45', (string) $sheet->getCell('J10')->getValue());
    }

    /**
     * @test
     */
    public function real_break_stamps_take_priority_over_auto_filled_break(): void
    {
        $this->givenWorkedDay(autoFillBreak: true);
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '12:10:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_END, '12:55:00');

        $row = $this->csvRow();

        $this->assertSame('12:10', $row[10]);
        $this->assertSame('12:55', $row[11]);
    }

    /**
     * @test
     */
    public function no_break_is_shown_when_auto_fill_is_disabled(): void
    {
        $this->givenWorkedDay(autoFillBreak: false, breakMinutes: 0);

        $row = $this->csvRow();

        $this->assertSame('', $row[10]);
        $this->assertSame('', $row[11]);
    }

    /**
     * @test
     *
     * 打刻修正で休憩を消した場合など、集計の休憩時間が0なら補わない
     * （画面も「-」表示で、控除されていない休憩を出すと食い違う）。
     */
    public function no_break_is_shown_when_deducted_break_minutes_is_zero(): void
    {
        $this->givenWorkedDay(autoFillBreak: true, breakMinutes: 0);

        $row = $this->csvRow();

        $this->assertSame('', $row[10]);
        $this->assertSame('', $row[11]);
    }

    /**
     * @test
     *
     * 早退などで休憩時刻が実労働時間の外に出る日は、控除される重なりの
     * 範囲だけを出す（集計の控除分と同じ範囲）。
     */
    public function break_is_clipped_to_the_worked_range(): void
    {
        $this->givenWorkedDay(autoFillBreak: true, breakMinutes: 30);
        // 12:30 に退勤した日（休憩 12:00〜12:45 のうち 12:00〜12:30 だけが労働時間と重なる）
        DailyWorkSummary::query()
            ->where('user_id', $this->user->id)
            ->where('work_date', '2026-06-24')
            ->update(['work_end' => '2026-06-24 12:30:00']);

        $row = $this->csvRow();

        $this->assertSame('12:00', $row[10]);
        $this->assertSame('12:30', $row[11]);
    }
}

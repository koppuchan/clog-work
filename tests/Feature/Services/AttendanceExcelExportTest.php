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
use App\Services\AttendanceExcelExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 勤務実績Excelの出力検証。
 *
 * 集計欄と合計行はシート側の数式で算出される。出力処理がそこへ値を書くと
 * 数式が失われ、給与計算に使えなくなる。出力後も数式が残ることを固定する。
 */
class AttendanceExcelExportTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Company $company;

    private string $generatedPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['company_code' => '970001']);
        $this->user = User::factory()->create([
            'name' => '出力 太郎',
            'employee_code' => '000123',
        ]);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->generatedPath !== '' && file_exists($this->generatedPath)) {
            unlink($this->generatedPath);
        }

        parent::tearDown();
    }

    private function generatedSheet(): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
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
    public function 出力後も集計欄の数式が残っている(): void
    {
        $sheet = $this->generatedSheet();

        foreach (['G4', 'H4', 'I4', 'J4', 'K4', 'L4', 'M4', 'N4', 'O4', 'P4', 'R4', 'S4', 'T4', 'U4'] as $cell) {
            $this->assertStringStartsWith(
                '=',
                (string) $sheet->getCell($cell)->getValue(),
                "{$cell} の数式が出力時に失われています",
            );
        }
    }

    /**
     * @test
     */
    public function 出力後も合計行の数式が残っている(): void
    {
        $sheet = $this->generatedSheet();

        foreach (['M38', 'N38', 'O38', 'P38', 'Q38'] as $cell) {
            $this->assertStringStartsWith(
                '=',
                (string) $sheet->getCell($cell)->getValue(),
                "{$cell} の数式が出力時に失われています",
            );
        }
    }

    /**
     * @test
     */
    public function スタッフの識別情報が出力される(): void
    {
        $sheet = $this->generatedSheet();

        $this->assertSame('000123', (string) $sheet->getCell('A4')->getValue());
        $this->assertSame('出力 太郎', (string) $sheet->getCell('B4')->getValue());
    }

    /**
     * @test
     */
    public function 日付欄が締め期間どおりに並ぶ(): void
    {
        $sheet = $this->generatedSheet();

        // 6/21〜7/20 は30日間。7行目から36行目までが埋まる
        $this->assertStringStartsWith('6/21', (string) $sheet->getCell('A7')->getValue());
        $this->assertStringStartsWith('7/20', (string) $sheet->getCell('A36')->getValue());

        // 31日ある月に備えて37行目まで用意されているが、今回は空のまま
        $this->assertSame('', (string) $sheet->getCell('A37')->getValue());
    }

    /**
     * @test
     *
     * 残業申請は承認しても daily_work_summaries.overtime_minutes を書き換えない設計
     * (OvertimeApplicationService::applyOvertimeToWorkSummary が無効化されている)。
     * そのため overtime_minutes が0のままでも、申請自体の開始・終了時刻から
     * 残業時間を算出して備考/申請列(R・S列)に出力する。
     */
    public function 残業申請の時間数が備考申請列に出力される(): void
    {
        // 6/21始まりの期間で6/24は10行目
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

        $sheet = $this->generatedSheet();

        $this->assertSame('残業申請', (string) $sheet->getCell('R10')->getValue());
        $this->assertSame('2.0', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * 端数のある残業時間は「2H」のように整数へ丸めず、1時間45分なら
     * 「1.75」のように小数の時間数で出力する。
     */
    public function 端数のある残業申請の時間数は小数で出力される(): void
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
            'end_time' => '19:45',
            'reason' => '月次締め作業のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('1.75', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * 明細行の労働時間・時間外・休日・深夜・遅刻早退は、文字列ではなく
     * 実際の数値（時刻の端数）として書き込まれ、合計行の数式が正しく
     * 計算できること（クライアント報告 #70: 合計値が計算されていない）。
     */
    public function 明細行の時間は合計可能な数値として出力され合計行が計算される(): void
    {
        // 6/21始まりの期間で6/24は10行目、6/25は11行目
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => '2026-06-24 09:00:00',
            'work_end' => '2026-06-24 18:00:00',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 480,
            'overtime_minutes' => 30,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-25',
            'work_start' => '2026-06-25 09:00:00',
            'work_end' => '2026-06-25 19:00:00',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 540,
            'overtime_minutes' => 90,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        // M10・M11は文字列ではなく数値（時刻の端数）で入っていること
        $this->assertIsNumeric($sheet->getCell('M10')->getValue());
        $this->assertEqualsWithDelta(480 / 1440, (float) $sheet->getCell('M10')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(540 / 1440, (float) $sheet->getCell('M11')->getValue(), 0.0001);

        // 合計行の数式(=SUM(M7:M37))が、書き込んだ数値を正しく合算できること
        $totalMinutes = (int) round(((float) $sheet->getCell('M38')->getCalculatedValue()) * 1440);
        $this->assertSame(480 + 540, $totalMinutes, 'M38=SUM(M7:M37) が実際の労働時間の合計を計算できていない');

        $totalOvertimeMinutes = (int) round(((float) $sheet->getCell('N38')->getCalculatedValue()) * 1440);
        $this->assertSame(30 + 90, $totalOvertimeMinutes);
    }

    /**
     * @test
     *
     * 0分の項目は、これまでどおり空欄のまま（文字列"0:00"等にはしない）。
     */
    public function 時間が0の項目は空欄のまま(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => '2026-06-24 09:00:00',
            'work_end' => '2026-06-24 18:00:00',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 480,
            'overtime_minutes' => 0,
            'holiday_minutes' => 0,
            'night_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('', (string) $sheet->getCell('N10')->getValue());
        $this->assertSame('', (string) $sheet->getCell('O10')->getValue());
        $this->assertSame('', (string) $sheet->getCell('P10')->getValue());
    }

    /**
     * @test
     *
     * シフトの所定時刻と実際の出退勤があるのに「欠勤」と出力されていた
     * 不具合（クライアント報告 #70）。日次集計バッチがまだ反映しておらず
     * summary.work_startがnullのままでも、実打刻（time_records）があれば
     * 「出勤」と判定されること。
     */
    public function シフトと実打刻があれば集計未反映でも出勤と出力される(): void
    {
        // 6/24は10行目。summaryは作るがwork_startは未反映のまま（バッチ未実行を再現）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-06-24 08:58:00',
            'rounded_time' => '2026-06-24 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('出勤', (string) $sheet->getCell('B10')->getValue());
        $this->assertSame('08:58', (string) $sheet->getCell('G10')->getValue(), '出勤時刻は実打刻を優先する');
    }

    /**
     * @test
     *
     * シフトが割り当てられている土曜・日曜に出退勤がなければ、平日と同じ
     * 基準で「欠勤」と出力されること（以前は曜日で別ロジックを使っており
     * 「休日」になってしまっていた）。
     */
    public function シフトのある土日に出退勤がなければ欠勤と出力される(): void
    {
        // 6/27(土)は13行目
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-27',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('欠勤', (string) $sheet->getCell('B13')->getValue());
    }
}

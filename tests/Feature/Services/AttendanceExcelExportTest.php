<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Request;
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
}

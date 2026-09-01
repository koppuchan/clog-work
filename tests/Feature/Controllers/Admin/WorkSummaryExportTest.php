<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WorkSummaryExportTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'name' => 'テスト会社',
        ]);

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create([
                'name' => '管理者ユーザー',
                'employee_code' => '100001',
                'is_retired' => false,
            ]);

        $this->employee = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create([
                'name' => '一般ユーザー',
                'employee_code' => '200001',
                'is_retired' => false,
            ]);
    }

    /**
     * テスト用の勤務実績データを作成
     */
    private function createWorkSummaries(int $userId, string $yearMonth, int $days = 5): void
    {
        $targetMonth = CarbonImmutable::createFromFormat('Y-m', $yearMonth)->startOfMonth();

        for ($i = 0; $i < $days; $i++) {
            $date = $targetMonth->addDays($i);
            // 土日はスキップ
            if ($date->isSaturday() || $date->isSunday()) {
                $days++;

                continue;
            }

            DailyWorkSummary::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $userId,
                'work_date' => $date->format('Y-m-d'),
                'scheduled_start_time' => '09:00',
                'scheduled_end_time' => '18:00',
                'work_start' => $date->format('Y-m-d').' 09:00:00',
                'work_end' => $date->format('Y-m-d').' 18:00:00',
                'work_minutes' => 540,
                'break_minutes' => 60,
                'net_work_minutes' => 480,
                'night_minutes' => 0,
                'holiday_minutes' => 0,
                'overtime_minutes' => 0,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'is_cross_day' => false,
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        }
    }

    // ========================================
    // CSV エクスポート テスト
    // ========================================

    /**
     * @test
     */
    public function export_csv_returns_csv_file_for_admin(): void
    {
        $this->createWorkSummaries($this->employee->id, '2025-01');

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /**
     * @test
     */
    public function export_csv_contains_user_name_and_month(): void
    {
        $this->createWorkSummaries($this->employee->id, '2025-01');

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // BOM除去
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $this->assertStringContainsString('一般ユーザー', $content);
        $this->assertStringContainsString('2025/01/01', $content);
    }

    /**
     * @test
     */
    public function export_csv_contains_work_data(): void
    {
        $this->createWorkSummaries($this->employee->id, '2025-01', 1);

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // BOM除去
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // CSV本体にヘッダー行が含まれている（全員CSVと同形式）
        $this->assertStringContainsString('氏名,日付,曜日,勤務区分,出勤時刻,退勤時刻', $content);
        // 出勤・退勤時刻
        $this->assertStringContainsString('09:00', $content);
        $this->assertStringContainsString('18:00', $content);
    }

    /**
     * @test
     */
    public function export_csv_format_matches_export_csv_all(): void
    {
        $this->createWorkSummaries($this->employee->id, '2025-01', 1);

        // 個別CSV
        $singleResponse = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&user_id='.$this->employee->id);
        ob_start();
        $singleResponse->sendContent();
        $singleContent = ob_get_clean();
        $singleContent = preg_replace('/^\xEF\xBB\xBF/', '', $singleContent);

        // 全員CSV
        $allResponse = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&scope=all');
        ob_start();
        $allResponse->sendContent();
        $allContent = ob_get_clean();
        $allContent = preg_replace('/^\xEF\xBB\xBF/', '', $allContent);

        // 1行目（ヘッダー）が両者で完全一致すること
        $singleHeader = strtok($singleContent, "\n");
        $allHeader = strtok($allContent, "\n");
        $this->assertSame($allHeader, $singleHeader);
    }

    /**
     * @test
     */
    public function export_csv_forbidden_for_non_admin(): void
    {
        $response = $this->actingAs($this->employee, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function export_csv_redirects_guest_to_login(): void
    {
        $response = $this->get('/admin/reports/export/csv?month=2025-01&user_id=1');

        $response->assertRedirect();
    }

    /**
     * @test
     */
    public function export_csv_uses_default_month_if_not_specified(): void
    {
        $this->createWorkSummaries($this->admin->id, CarbonImmutable::now()->format('Y-m'));

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv');

        $response->assertStatus(200);
    }

    /**
     * @test
     */
    public function export_csv_with_no_data_returns_empty_csv(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // ヘッダーと空のデータ行がある（全員CSVと同形式）
        $this->assertStringContainsString('氏名,日付,曜日,勤務区分,出勤時刻,退勤時刻', $content);
    }

    // ========================================
    // Excel エクスポート テスト
    // ========================================

    /**
     * @test
     */
    public function export_excel_returns_xlsx_file_for_admin(): void
    {
        $this->createWorkSummaries($this->employee->id, '2025-01');

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/excel?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /**
     * @test
     */
    public function export_excel_filename_contains_user_name_and_month(): void
    {
        $this->createWorkSummaries($this->employee->id, '2025-01');

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/excel?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(200);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('2025', $disposition);
    }

    /**
     * @test
     */
    public function export_excel_forbidden_for_non_admin(): void
    {
        $response = $this->actingAs($this->employee, 'admin')
            ->get('/admin/reports/export/excel?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function export_excel_redirects_guest_to_login(): void
    {
        $response = $this->get('/admin/reports/export/excel?month=2025-01&user_id=1');

        $response->assertRedirect();
    }

    /**
     * @test
     */
    public function export_excel_defaults_to_current_month_when_no_params(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/excel?user_id='.$this->employee->id);

        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /**
     * @test
     */
    public function export_excel_with_no_data_still_generates_file(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/excel?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /**
     * @test
     */
    public function export_excel_generates_valid_xlsx(): void
    {
        $this->createWorkSummaries($this->employee->id, '2025-01', 3);

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/excel?month=2025-01&user_id='.$this->employee->id);

        $response->assertStatus(200);

        // ダウンロードレスポンスの本体はファイルなので getContent() では取れない
        $path = $response->baseResponse->getFile()->getPathname();
        $content = file_get_contents($path);

        $this->assertNotEmpty($content);
        // xlsxはZIP形式なのでPKで始まる
        $this->assertEquals('PK', substr($content, 0, 2));

        // 送信後の削除はテストでは走らないため、次回実行に残さない
        @unlink($path);
    }

    /**
     * @test
     */
    public function export_excel_falls_back_to_first_user_if_user_id_invalid(): void
    {
        $this->createWorkSummaries($this->admin->id, '2025-01');

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/excel?month=2025-01&user_id=99999');

        // 無効なIDの場合は最初のユーザーにフォールバック
        $response->assertStatus(200);
    }

    // ========================================
    // CSV エクスポート 勤務データ詳細テスト
    // ========================================

    /**
     * @test
     */
    public function export_csv_includes_overtime_data(): void
    {
        $date = CarbonImmutable::parse('2025-01-06'); // 月曜日

        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'work_date' => $date->format('Y-m-d'),
            'scheduled_start_time' => '09:00',
            'scheduled_end_time' => '18:00',
            'work_start' => $date->format('Y-m-d').' 09:00:00',
            'work_end' => $date->format('Y-m-d').' 21:00:00',
            'work_minutes' => 720,
            'break_minutes' => 60,
            'net_work_minutes' => 660,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => 180,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&user_id='.$this->employee->id);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // 時間外: 180分 = 3:00（日次データ行に出力される）
        $this->assertStringContainsString('3:00', $content);
    }

    /**
     * @test
     */
    public function export_csv_includes_late_and_early_leave_minutes(): void
    {
        $date = CarbonImmutable::parse('2025-01-06');

        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'work_date' => $date->format('Y-m-d'),
            'scheduled_start_time' => '09:00',
            'scheduled_end_time' => '18:00',
            'work_start' => $date->format('Y-m-d').' 09:30:00',
            'work_end' => $date->format('Y-m-d').' 17:00:00',
            'work_minutes' => 450,
            'break_minutes' => 60,
            'net_work_minutes' => 390,
            'night_minutes' => 0,
            'holiday_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 30,
            'early_leave_minutes' => 60,
            'is_cross_day' => false,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/reports/export/csv?month=2025-01&user_id='.$this->employee->id);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // 遅刻30分=0:30, 早退60分=1:00 が日次データ行に出力される
        $this->assertStringContainsString('0:30', $content);
        $this->assertStringContainsString('1:00', $content);
    }
}

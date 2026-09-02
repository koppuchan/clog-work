<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\User;
use App\Services\WorkSummaryCsvImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 旧環境から出力した勤務実績CSVの取り込み。
 *
 * 旧環境の出力には勤務区分の列がないため、新旧どちらのヘッダーでも
 * 取り込めることが要点。
 */
class WorkSummaryCsvImportServiceTest extends TestCase
{
    use DatabaseTransactions;

    private WorkSummaryCsvImportService $service;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkSummaryCsvImportService::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create(['name' => '前田 依子', 'is_retired' => false]);
    }

    /** 旧環境の出力（勤務区分なし・14列） */
    private function oldFormat(string ...$rows): string
    {
        $header = '氏名,日付,曜日,出勤時刻,退勤時刻,勤務時間,休憩,実働時間,時間外,休日,深夜,遅刻,早退,備考';

        return $header."\n".implode("\n", $rows)."\n";
    }

    private function import(string $csv): array
    {
        return $this->service->import($csv, $this->company->id);
    }

    private function summaryFor(string $date): ?DailyWorkSummary
    {
        return DailyWorkSummary::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('work_date', $date)
            ->first();
    }

    /**
     * @test
     */
    public function 旧環境の形式を取り込める(): void
    {
        $csv = $this->oldFormat(
            '前田 依子,2026/06/24,水,9:00,18:00,9:00,1:00,8:00,0:00,0:00,0:00,0:00,0:00,'
        );

        $result = $this->import($csv);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);

        $summary = $this->summaryFor('2026-06-24');
        $this->assertNotNull($summary);
        $this->assertSame(540, $summary->work_minutes);
        $this->assertSame(60, $summary->break_minutes);
        $this->assertSame(480, $summary->net_work_minutes);
        $this->assertSame('09:00', $summary->work_start->format('H:i'));
        $this->assertSame('18:00', $summary->work_end->format('H:i'));
    }

    /**
     * @test
     */
    public function 勤務区分の列がある新しい形式も取り込める(): void
    {
        $csv = "氏名,日付,曜日,勤務区分,出勤時刻,退勤時刻,勤務時間,休憩,実働時間,時間外,休日,深夜,遅刻,早退,備考\n"
            ."前田 依子,2026/06/24,水,出勤,9:00,18:00,9:00,1:00,8:00,1:00,0:00,0:00,0:00,0:00,\n";

        $result = $this->import($csv);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(60, $this->summaryFor('2026-06-24')?->overtime_minutes);
    }

    /**
     * @test
     */
    public function 打刻も時間もない行は取り込まない(): void
    {
        // 出力には勤務のない日も含まれる
        $csv = $this->oldFormat(
            '前田 依子,2026/06/25,木,,,0:00,0:00,0:00,0:00,0:00,0:00,0:00,0:00,'
        );

        $result = $this->import($csv);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertNull($this->summaryFor('2026-06-25'));
    }

    /**
     * @test
     */
    public function 一致するスタッフがいない行は理由を返す(): void
    {
        $csv = $this->oldFormat(
            '存在 しない,2026/06/24,水,9:00,18:00,9:00,1:00,8:00,0:00,0:00,0:00,0:00,0:00,'
        );

        $result = $this->import($csv);

        $this->assertSame(0, $result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('存在 しない', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function 同姓同名は取り違えを避けて取り込まない(): void
    {
        User::factory()
            ->forCompany($this->company->id)
            ->create(['name' => '前田 依子', 'is_retired' => false]);

        $csv = $this->oldFormat(
            '前田 依子,2026/06/24,水,9:00,18:00,9:00,1:00,8:00,0:00,0:00,0:00,0:00,0:00,'
        );

        $result = $this->import($csv);

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('複数登録されている', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function 夜勤は退勤を翌日として取り込む(): void
    {
        $csv = $this->oldFormat(
            '前田 依子,2026/06/24,水,22:00,7:00,9:00,1:00,8:00,0:00,0:00,7:00,0:00,0:00,'
        );

        $this->import($csv);

        $summary = $this->summaryFor('2026-06-24');
        $this->assertSame('2026-06-25 07:00', $summary?->work_end->format('Y-m-d H:i'));
    }

    /**
     * @test
     */
    public function 確認のみの場合は保存しない(): void
    {
        $csv = $this->oldFormat(
            '前田 依子,2026/06/24,水,9:00,18:00,9:00,1:00,8:00,0:00,0:00,0:00,0:00,0:00,'
        );

        $result = $this->service->import($csv, $this->company->id, true);

        $this->assertSame(1, $result['imported']);
        $this->assertNull($this->summaryFor('2026-06-24'));
    }

    /**
     * @test
     */
    public function 日付の列がなければ取り込まない(): void
    {
        $result = $this->import("氏名,出勤時刻\n前田 依子,9:00\n");

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('日付', $result['errors'][0]);
    }

    /**
     * @test
     */
    public function 個人コードでスタッフを特定できる(): void
    {
        // 旧環境の出力には個人コードが含まれるため、氏名より確実に照合できる
        $this->user->update(['employee_code' => '000002']);

        $csv = "個人コード,日付,勤務区分,出勤時刻,退勤時刻,労働時間,時間外,休日,深夜,遅刻早退\n"
            ."000002,2026/06/24,出勤,09:00,18:00,8:00,1:00,0:00,0:00,0:00\n";

        $result = $this->import($csv);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(480, $this->summaryFor('2026-06-24')?->net_work_minutes);
    }

    /**
     * @test
     */
    public function 勤務区分から休暇種別を引き継ぐ(): void
    {
        $this->user->update(['employee_code' => '000002']);

        $csv = "個人コード,日付,勤務区分,出勤時刻,退勤時刻,労働時間\n"
            ."000002,2026/06/24,有給休暇,,,0:00\n";

        $result = $this->import($csv);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $this->summaryFor('2026-06-24')?->leave_type?->value);
    }

    /**
     * @test
     */
    public function 備考を引き継ぐ(): void
    {
        $csv = $this->oldFormat(
            '前田 依子,2026/06/24,水,9:00,18:00,9:00,1:00,8:00,0:00,0:00,0:00,0:00,0:00,直行'
        );

        $this->import($csv);

        $this->assertSame('直行', $this->summaryFor('2026-06-24')?->note);
    }
}

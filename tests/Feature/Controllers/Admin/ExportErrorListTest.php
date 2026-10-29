<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\TimeRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 勤務実績の出力で、エラーリスト（休憩入力無しリスト・打刻漏れリスト）を出力できる。
 *
 * クライアント要望: シフトの休憩の自動入力は設定のとおりが正しいが、休日に出勤した日などは
 * 休憩を手で入れる必要がある。休憩入力無しのリストと打刻漏れのリストを出力してほしい。
 */
class ExportErrorListTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private User $first;

    private User $second;

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.export_batch_size' => 1]);

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->forCompany($this->company->id)->admin()->create([
            'name' => '管理者', 'employee_code' => '100001', 'is_retired' => false,
        ]);
        $this->first = User::factory()->forCompany($this->company->id)->employee()->create([
            'name' => '一郎', 'employee_code' => '200001', 'is_retired' => false,
        ]);
        $this->second = User::factory()->forCompany($this->company->id)->employee()->create([
            'name' => '二郎', 'employee_code' => '200002', 'is_retired' => false,
        ]);

        // 休憩が0分の日（シフトなし）
        foreach ([$this->first, $this->second] as $user) {
            DailyWorkSummary::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'work_date' => '2025-01-05',
                'work_start' => '2025-01-05 09:00:00',
                'work_end' => '2025-01-05 18:00:00',
                'work_minutes' => 540,
                'break_minutes' => 0,
                'net_work_minutes' => 540,
                'record_source' => RecordSourceEnum::AUTO,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function exportErrors(array $query, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin, 'admin')->get(
            '/admin/reports/export/errors?'.http_build_query($query + [
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-31',
            ])
        );
    }

    /**
     * 読み込んだExcelは、テスト全体（同じプロセス）のメモリを使い続けないよう、終了時に解放する
     *
     * @var array<int, \PhpOffice\PhpSpreadsheet\Spreadsheet>
     */
    private array $loaded = [];

    protected function tearDown(): void
    {
        foreach ($this->loaded as $spreadsheet) {
            $spreadsheet->disconnectWorksheets();
        }
        $this->loaded = [];
        gc_collect_cycles();

        parent::tearDown();
    }

    private function load(TestResponse $response): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        return $this->loaded[] = IOFactory::load($response->baseResponse->getFile()->getPathname());
    }

    /**
     * @test
     */
    public function 選んだリストごとにシートを分けた1つのexcelで出力される(): void
    {
        $response = $this->exportErrors(['scope' => 'all', 'lists' => ['no_break', 'missing_stamp']]);

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'エラーリスト_全従業員_2025年01月01日〜01月31日.xlsx',
            urldecode($response->headers->get('content-disposition') ?? '')
        );
        $this->assertSame(['休憩入力無しリスト', '打刻漏れリスト'], $this->load($response)->getSheetNames());
    }

    /**
     * @test
     */
    public function 休憩入力無しリストに該当の日が行として出る(): void
    {
        $sheet = $this->load($this->exportErrors(['scope' => 'all', 'lists' => ['no_break']]))->getSheetByName('休憩入力無しリスト');

        $this->assertStringContainsString('2件', $sheet->getCell('A1')->getValue());
        $this->assertSame('従業員コード', $sheet->getCell('A4')->getValue());
        $this->assertSame('氏名', $sheet->getCell('B4')->getValue());
        $this->assertSame(['一郎', '二郎'], [$sheet->getCell('B5')->getValue(), $sheet->getCell('B6')->getValue()]);
        $this->assertSame('2025-01-05', $sheet->getCell('C5')->getValue());
        $this->assertSame('日', $sheet->getCell('D5')->getValue());
        $this->assertSame('シフトなし', $sheet->getCell('E5')->getValue());
        $this->assertSame('9:00', $sheet->getCell('H5')->getValue());
    }

    /**
     * @test
     */
    public function 該当が無いときは該当なしと表示する(): void
    {
        $sheet = $this->load($this->exportErrors(['scope' => 'all', 'lists' => ['missing_stamp']]))->getSheetByName('打刻漏れリスト');

        $this->assertSame('該当はありません。', $sheet->getCell('A5')->getValue());
    }

    /**
     * @test
     *
     * クライアント回答: 打刻漏れリストの出力は、出勤時刻と退勤時刻を備考欄ではなく列として表示する。
     */
    public function 打刻漏れリストは出勤時刻と退勤時刻が列になる(): void
    {
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->first->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2025-01-06 09:00:00',
            'rounded_time' => '2025-01-06 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->load($this->exportErrors(['scope' => 'all', 'lists' => ['missing_stamp']]))->getSheetByName('打刻漏れリスト');

        $this->assertSame(
            ['従業員コード', '氏名', '日付', '曜日', '出勤時刻', '退勤時刻', '内容', 'その日の打刻'],
            array_map(fn (string $column) => $sheet->getCell($column.'4')->getValue(), ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'])
        );
        $this->assertSame('一郎', $sheet->getCell('B5')->getValue());
        $this->assertSame('2025-01-06', $sheet->getCell('C5')->getValue());
        $this->assertSame('09:00', $sheet->getCell('E5')->getValue());
        $this->assertSame('', (string) $sheet->getCell('F5')->getValue());
        $this->assertSame('退勤の打刻なし', $sheet->getCell('G5')->getValue());
        $this->assertSame('勤務開始 09:00', $sheet->getCell('H5')->getValue());
    }

    /**
     * @test
     *
     * クライアント回答: 休憩入力無しリストは、労働時間を条件指定して絞り込めるようにする。
     */
    public function 休憩入力無しリストは労働時間の下限を指定して出力できる(): void
    {
        // 勤務実績は2人とも9時間（540分）。下限はちょうど9時間なら載り、それを超えると載らない
        $atLimit = $this->load($this->exportErrors(['scope' => 'all', 'lists' => ['no_break'], 'min_work_minutes' => 540]))
            ->getSheetByName('休憩入力無しリスト');
        $this->assertStringContainsString('2件', $atLimit->getCell('A1')->getValue());
        $this->assertStringContainsString('条件：労働時間9:00以上', $atLimit->getCell('A1')->getValue());

        $overLimit = $this->load($this->exportErrors(['scope' => 'all', 'lists' => ['no_break'], 'min_work_minutes' => 541]))
            ->getSheetByName('休憩入力無しリスト');
        $this->assertStringContainsString('0件', $overLimit->getCell('A1')->getValue());
        $this->assertSame('該当はありません。', $overLimit->getCell('A5')->getValue());
    }

    /**
     * @test
     */
    public function 労働時間の条件が不正だと出力できない(): void
    {
        $this->exportErrors(['scope' => 'all', 'lists' => ['no_break'], 'min_work_minutes' => 'abc'])->assertSessionHasErrors('min_work_minutes');
        $this->exportErrors(['scope' => 'all', 'lists' => ['no_break'], 'min_work_minutes' => -1])->assertSessionHasErrors('min_work_minutes');
        $this->exportErrors(['scope' => 'all', 'lists' => ['no_break'], 'min_work_minutes' => 1441])->assertSessionHasErrors('min_work_minutes');
    }

    /**
     * @test
     */
    public function 選択中のスタッフだけを出力できる(): void
    {
        $response = $this->exportErrors(['scope' => 'single', 'user_id' => $this->second->id, 'lists' => ['no_break']]);

        $sheet = $this->load($response)->getSheetByName('休憩入力無しリスト');
        $this->assertSame('二郎', $sheet->getCell('B5')->getValue());
        $this->assertNull($sheet->getCell('B6')->getValue());
        $this->assertStringContainsString('エラーリスト_二郎_', urldecode($response->headers->get('content-disposition') ?? ''));
    }

    /**
     * @test
     */
    public function 全従業員は人数ごとの範囲に分けて出力できる(): void
    {
        // 出力範囲はスタッフの並び順で区切られる。2番目の範囲は2人目から
        $response = $this->exportErrors(['scope' => 'all', 'batch' => 2, 'lists' => ['no_break']]);

        $response->assertStatus(200);
        $this->assertStringContainsString('エラーリスト_2〜2名_', urldecode($response->headers->get('content-disposition') ?? ''));
        $this->assertStringContainsString('1件', $this->load($response)->getSheetByName('休憩入力無しリスト')->getCell('A1')->getValue());
    }

    /**
     * @test
     */
    public function 管理者以外は出力できない(): void
    {
        // 管理画面全体の権限チェックで、ファイルを返さずに弾かれる
        $response = $this->exportErrors(['scope' => 'all', 'lists' => ['no_break']], $this->first);

        $response->assertRedirect();
        $this->assertStringNotContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
    }

    /**
     * @test
     */
    public function リストを選ばないと出力できない(): void
    {
        $this->exportErrors(['scope' => 'all'])->assertSessionHasErrors('lists');
        $this->exportErrors(['scope' => 'all', 'lists' => ['unknown']])->assertSessionHasErrors('lists.0');
    }

    /**
     * @test
     */
    public function 終了日が開始日より前だと出力できない(): void
    {
        $this->exportErrors([
            'scope' => 'all', 'lists' => ['no_break'], 'start_date' => '2025-02-01', 'end_date' => '2025-01-01',
        ])->assertSessionHasErrors('end_date');
    }
}

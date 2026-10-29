<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\Department;
use App\Models\Shift;
use App\Models\ShiftColor;
use App\Models\ShiftPattern;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * シフト管理の画面を、全部署または選択した部署で印刷・Excel出力する。
 *
 * クライアント要望: シフト管理の画面を印刷（全部署もしくは選択した部署）できるようにするのと、
 * Excelで出力できるようにしてほしい。
 */
class ShiftTableOutputTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private User $sato;

    private User $tanaka;

    private Department $sales;

    private Department $care;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->forCompany($this->company->id)->admin()->create([
            'name' => '管理者', 'employee_code' => '0000', 'is_retired' => false, 'is_shift_hidden' => true,
        ]);

        $this->sales = Department::factory()->forCompany($this->company->id)->create(['name' => '営業']);
        $this->care = Department::factory()->forCompany($this->company->id)->create(['name' => '介護']);

        $this->sato = User::factory()->forCompany($this->company->id)->employee()->create([
            'name' => '佐藤', 'employee_code' => '0001', 'is_retired' => false,
        ]);
        $this->sato->departments()->attach($this->sales->id, ['is_primary' => true]);
        $this->tanaka = User::factory()->forCompany($this->company->id)->employee()->create([
            'name' => '田中', 'employee_code' => '0002', 'is_retired' => false,
        ]);
        $this->tanaka->departments()->attach($this->care->id, ['is_primary' => true]);

        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => 'A日勤',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'auto_fill_break' => false,
        ]);
        $pattern->colors()->attach(ShiftColor::query()->firstOrCreate(['background_color' => 'bg-blue-100', 'text_color' => 'text-blue-800'])->id);

        foreach ([[$this->sato, '2026-10-16'], [$this->sato, '2026-10-17'], [$this->tanaka, '2026-10-16']] as [$user, $date]) {
            Shift::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'shift_date' => $date,
                'shift_pattern_id' => $pattern->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(string $path, array $query = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin, 'admin')->get(
            '/admin/shifts/'.$path.'?'.http_build_query($query + ['start_date' => '2026-10-16', 'end_date' => '2026-10-20'])
        );
    }

    /**
     * 読み込んだExcelは、テスト全体（同じプロセス）のメモリを使い続けないよう、終了時に解放する
     *
     * @var array<int, Spreadsheet>
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

    private function sheetOf(TestResponse $response): Worksheet
    {
        $this->loaded[] = $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @return array<int, string>
     */
    private function columnA(Worksheet $sheet): array
    {
        $values = [];
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            $values[] = (string) $sheet->getCell('A'.$row)->getValue();
        }

        return $values;
    }

    /**
     * @test
     */
    public function 印刷用ページに全部署のシフト表が渡る(): void
    {
        $this->request('print')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/ShiftsPrint')
            ->where('table.department_name', null)
            ->where('table.start_date', '2026-10-16')
            ->has('table.days', 5)
            ->has('table.groups', 2)
            ->where('table.groups.0.name', '営業')
            ->where('table.groups.0.users.0.name', '佐藤')
            ->where('table.groups.0.users.0.cells.2026-10-16.label', 'A日勤')
            ->where('table.groups.0.users.0.cells.2026-10-16.background', '#DBEAFE')
            ->where('table.groups.1.name', '介護')
            ->where('table.groups.0.counts.2026-10-16', 1)
        );
    }

    /**
     * @test
     */
    public function 印刷用ページは選択した部署だけのシフト表になる(): void
    {
        $this->request('print', ['department_ids' => [$this->care->id]])->assertInertia(fn (AssertableInertia $page) => $page
            ->where('table.department_name', '介護')
            ->has('table.groups', 1)
            ->where('table.groups.0.users.0.name', '田中')
        );
    }

    /**
     * @test
     */
    public function excelは全部署を1枚のシートに部署ごとにまとめて出力する(): void
    {
        $response = $this->request('export/excel');

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'シフト表_全部署_2026年10月16日〜10月20日.xlsx',
            urldecode($response->headers->get('content-disposition') ?? '')
        );

        $sheet = $this->sheetOf($response);
        $this->assertSame('シフト表', $sheet->getTitle());
        $this->assertSame('シフト表　2026年10月16日〜10月20日　全部署', $sheet->getCell('A1')->getValue());

        // 見出し: スタッフ、日付（最初の日は月つき）、曜日
        $this->assertSame('スタッフ', $sheet->getCell('A3')->getValue());
        $this->assertSame('10/16', $sheet->getCell('B3')->getValue());
        $this->assertSame('17', $sheet->getCell('C3')->getValue());
        $this->assertSame(['金', '土', '日'], [$sheet->getCell('B4')->getValue(), $sheet->getCell('C4')->getValue(), $sheet->getCell('D4')->getValue()]);
        $this->assertSame('出勤', $sheet->getCell('G3')->getValue());
        $this->assertSame('休日', $sheet->getCell('H3')->getValue());

        // 部署ごとに、部署名・スタッフ・部署の合計が並ぶ。最後に全体の合計と凡例
        $column = $this->columnA($sheet);
        $this->assertSame(
            ['営業', '佐藤', '営業 合計', '介護', '田中', '介護 合計', '合計'],
            array_slice($column, 4, 7)
        );
        $this->assertContains('凡例', $column);
        $this->assertContains('A日勤', $column);
    }

    /**
     * @test
     */
    public function excelのセルにはシフト名と色_曜日の色_出勤人数が入る(): void
    {
        $sheet = $this->sheetOf($this->request('export/excel'));

        // 佐藤（営業）: 10/16と10/17がA日勤、10/18は休み
        $this->assertSame('A日勤', $sheet->getCell('B6')->getValue());
        $this->assertSame('DBEAFE', $sheet->getStyle('B6')->getFill()->getStartColor()->getRGB());
        $this->assertSame('1E40AF', $sheet->getStyle('B6')->getFont()->getColor()->getRGB());
        $this->assertSame('休み', $sheet->getCell('D6')->getValue());
        $this->assertSame(2, $sheet->getCell('G6')->getValue()); // 出勤2日
        $this->assertSame(3, $sheet->getCell('H6')->getValue()); // 休日3日

        // 土曜は青、日曜は赤
        $this->assertSame('DBEAFE', $sheet->getStyle('C3')->getFill()->getStartColor()->getRGB());
        $this->assertSame('1D4ED8', $sheet->getStyle('C3')->getFont()->getColor()->getRGB());
        $this->assertSame('FEE2E2', $sheet->getStyle('D3')->getFill()->getStartColor()->getRGB());

        // 営業の出勤人数と、全体の出勤人数（10/16は営業1人+介護1人=2人）
        $this->assertEquals(1, $sheet->getCell('B7')->getValue());
        $this->assertEquals(2, $sheet->getCell('B11')->getValue());
        $this->assertEquals(3, $sheet->getCell('G11')->getValue()); // 全体の出勤日数の合計
    }

    /**
     * @test
     */
    public function excelは選択した部署だけを出力し_全体の合計は出さない(): void
    {
        $response = $this->request('export/excel', ['department_ids' => [$this->sales->id]]);

        $this->assertStringContainsString('シフト表_営業_', urldecode($response->headers->get('content-disposition') ?? ''));

        $sheet = $this->sheetOf($response);
        $this->assertSame('シフト表　2026年10月16日〜10月20日　営業', $sheet->getCell('A1')->getValue());
        $column = $this->columnA($sheet);
        $this->assertSame(['営業', '佐藤', '営業 合計'], array_slice($column, 4, 3));
        $this->assertNotContains('合計', $column);
        $this->assertNotContains('田中', $column);
    }

    /**
     * @test
     *
     * クライアント要望: 全部署のときは部署ごとに改ページしてほしい。選択できるようにしてほしい。
     */
    public function 印刷ページは部署ごとに改ページするかを指定できる(): void
    {
        $this->request('print')->assertInertia(fn (AssertableInertia $page) => $page->where('pageBreak', false));
        $this->request('print', ['page_break' => 1])->assertInertia(fn (AssertableInertia $page) => $page->where('pageBreak', true));
        $this->request('print', ['page_break' => 0])->assertInertia(fn (AssertableInertia $page) => $page->where('pageBreak', false));
        $this->request('print', ['page_break' => 'abc'])->assertSessionHasErrors('page_break');
    }

    /**
     * @test
     */
    public function excelは部署ごとに改ページを入れられる(): void
    {
        // 営業（5〜7行目）の次の介護（8行目）から新しいページにする
        $withBreak = $this->sheetOf($this->request('export/excel', ['page_break' => 1]));
        $this->assertSame(['営業 合計', '介護'], [(string) $withBreak->getCell('A7')->getValue(), (string) $withBreak->getCell('A8')->getValue()]);
        $this->assertSame(['A7'], array_keys($withBreak->getRowBreaks()));

        // 指定がなければ、ページは分けない
        $this->assertSame([], $this->sheetOf($this->request('export/excel'))->getRowBreaks());

        // 部署を選んだ出力は、指定があっても分ける相手がいない
        $one = $this->sheetOf($this->request('export/excel', ['page_break' => 1, 'department_ids' => [$this->sales->id]]));
        $this->assertSame([], $one->getRowBreaks());
    }

    /**
     * @test
     *
     * クライアント要望: 部署を一つだけでなく、2つ以上選択できるようにしてほしい。
     */
    public function 印刷ページは複数の部署を選んで出力できる(): void
    {
        $third = Department::factory()->forCompany($this->company->id)->create(['name' => '総務']);
        $suzuki = User::factory()->forCompany($this->company->id)->employee()->create(['name' => '鈴木', 'employee_code' => '0003', 'is_retired' => false]);
        $suzuki->departments()->attach($third->id, ['is_primary' => true]);

        $this->request('print', ['department_ids' => [$this->care->id, $this->sales->id]])->assertInertia(fn (AssertableInertia $page) => $page
            ->where('table.department_name', '営業・介護')
            ->has('table.groups', 2)
            ->where('table.groups.0.name', '営業')
            ->where('table.groups.1.name', '介護')
        );
    }

    /**
     * @test
     */
    public function excelは複数の部署を選ぶと_選んだ部署の合計と改ページが入る(): void
    {
        $third = Department::factory()->forCompany($this->company->id)->create(['name' => '総務']);
        $suzuki = User::factory()->forCompany($this->company->id)->employee()->create(['name' => '鈴木', 'employee_code' => '0003', 'is_retired' => false]);
        $suzuki->departments()->attach($third->id, ['is_primary' => true]);

        $response = $this->request('export/excel', ['department_ids' => [$this->sales->id, $this->care->id], 'page_break' => 1]);

        $this->assertStringContainsString('シフト表_営業・介護_', urldecode($response->headers->get('content-disposition') ?? ''));

        $sheet = $this->sheetOf($response);
        $this->assertSame('シフト表　2026年10月16日〜10月20日　営業・介護', $sheet->getCell('A1')->getValue());
        $column = $this->columnA($sheet);
        $this->assertSame(['営業', '佐藤', '営業 合計', '介護', '田中', '介護 合計', '合計'], array_slice($column, 4, 7));
        $this->assertNotContains('鈴木', $column);
        $this->assertSame(['A7'], array_keys($sheet->getRowBreaks()));
    }

    /**
     * @test
     *
     * 選んだ部署が多いときは、ファイル名が長くなりすぎないよう、部署の数にする。
     */
    public function ファイル名は選んだ部署が4つ以上なら部署の数になる(): void
    {
        $extra = collect(['総務', '経理', '人事'])->map(fn (string $name) => Department::factory()->forCompany($this->company->id)->create(['name' => $name]));

        $response = $this->request('export/excel', [
            'department_ids' => [$this->sales->id, $this->care->id, ...$extra->pluck('id')->all()],
        ]);

        $this->assertStringContainsString('シフト表_5部署_', urldecode($response->headers->get('content-disposition') ?? ''));
    }

    /**
     * @test
     */
    public function 閲覧できる範囲のスタッフだけが出力される(): void
    {
        // 管理画面に入れる責任者が、自分の部署（営業）のスタッフだけ閲覧できる場合
        $manager = User::factory()->forCompany($this->company->id)->manager()->create([
            'name' => '責任者', 'employee_code' => '0009', 'is_retired' => false, 'is_shift_hidden' => true,
        ]);

        $this->partialMock(PermissionService::class, function ($mock) {
            $mock->shouldReceive('getAccessibleUserIds')
                ->with(Mockery::any(), PermissionService::RESOURCE_SHIFT_VIEW)
                ->andReturn([$this->sato->id]);
        });

        $this->request('print', as: $manager)->assertInertia(fn (AssertableInertia $page) => $page
            ->has('table.groups', 1)
            ->where('table.groups.0.name', '営業')
            ->where('table.groups.0.users.0.name', '佐藤')
        );
    }

    /**
     * @test
     */
    public function 他社の部署や不正な期間は指定できない(): void
    {
        $otherDepartment = Department::factory()->forCompany(Company::factory()->create()->id)->create(['name' => '他社の部署']);

        $this->request('print', ['department_ids' => [$this->sales->id, $otherDepartment->id]])->assertSessionHasErrors('department_ids.1');
        $this->request('export/excel', ['department_ids' => [999999]])->assertSessionHasErrors('department_ids.0');
        $this->request('print', ['department_ids' => 'abc'])->assertSessionHasErrors('department_ids');
        $this->request('print', ['start_date' => '2026-10-20', 'end_date' => '2026-10-16'])->assertSessionHasErrors('end_date');
        $this->request('print', ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'])->assertSessionHasErrors('end_date');
        $this->request('print', ['start_date' => 'abc'])->assertSessionHasErrors('start_date');
    }

    /**
     * @test
     */
    public function 一般のスタッフは出力できない(): void
    {
        $this->request('print', as: $this->sato)->assertRedirect();
        $response = $this->request('export/excel', as: $this->sato);
        $response->assertRedirect();
        $this->assertStringNotContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
    }
}

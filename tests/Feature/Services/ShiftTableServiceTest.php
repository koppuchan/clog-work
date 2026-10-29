<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Department;
use App\Models\Shift;
use App\Models\ShiftColor;
use App\Models\ShiftPattern;
use App\Models\User;
use App\Services\ShiftTableService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * シフト表（印刷・Excel出力用）のデータ。
 *
 * クライアント要望: シフト管理の画面を、全部署または選択した部署で印刷・Excel出力できるように
 * してほしい。画面と同じ見え方（部署ごとのまとまり、色、休暇、出勤人数）になることを確認する。
 */
class ShiftTableServiceTest extends TestCase
{
    use DatabaseTransactions;

    // 2026/9/19(土) 〜 9/24(木)。9/20は日曜、9/21〜9/23は祝日（敬老の日・国民の休日・秋分の日）
    private const START = '2026-09-19';

    private const END = '2026-09-24';

    private Company $company;

    private Department $sales;

    private Department $care;

    private User $sato;

    private User $suzuki;

    private User $tanaka;

    private User $noDepartment;

    private ShiftPattern $day;

    private ShiftPattern $night;

    private ShiftTableService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->sales = Department::factory()->forCompany($this->company->id)->create(['name' => '営業']);
        $this->care = Department::factory()->forCompany($this->company->id)->create(['name' => '介護']);

        $this->sato = $this->makeUser('佐藤', '0001', $this->sales);
        $this->suzuki = $this->makeUser('鈴木', '0002', $this->sales);
        $this->tanaka = $this->makeUser('田中', '0003', $this->care);
        $this->noDepartment = $this->makeUser('無所属', '0004', null);

        $this->day = $this->makePattern('A日勤', '09:00', '18:00', 'bg-blue-100', 'text-blue-800');
        $this->night = $this->makePattern('B夜勤', '22:00', '07:00', null, null);

        $this->service = app(ShiftTableService::class);
    }

    private function makeUser(string $name, string $code, ?Department $department): User
    {
        $user = User::factory()->forCompany($this->company->id)->create([
            'name' => $name, 'employee_code' => $code, 'is_retired' => false,
        ]);
        if ($department !== null) {
            $user->departments()->attach($department->id, ['is_primary' => true]);
        }

        return $user;
    }

    private function makePattern(string $name, string $start, string $end, ?string $background, ?string $text): ShiftPattern
    {
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'start_time' => $start,
            'end_time' => $end,
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 60,
            'auto_fill_break' => false,
        ]);

        if ($background !== null) {
            $color = ShiftColor::query()->firstOrCreate(['background_color' => $background, 'text_color' => $text]);
            $pattern->colors()->attach($color->id);
        }

        return $pattern;
    }

    private function shift(User $user, string $date, ShiftPattern $pattern): void
    {
        Shift::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'shift_date' => $date,
            'shift_pattern_id' => $pattern->id,
        ]);
    }

    private function leave(User $user, string $date, int $minutes): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'work_date' => $date,
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => $minutes,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function everyone(): array
    {
        return [$this->sato->id, $this->suzuki->id, $this->tanaka->id, $this->noDepartment->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function build(?array $viewable = null, array $departmentIds = []): array
    {
        return $this->service->build($this->company->id, $viewable ?? $this->everyone(), self::START, self::END, $departmentIds);
    }

    /**
     * @test
     */
    public function スタッフは部署ごとにまとめ_部署の作成順で並べ_未所属を末尾にする(): void
    {
        $table = $this->build();

        $this->assertSame(['営業', '介護', '未所属'], array_column($table['groups'], 'name'));
        $this->assertSame([$this->sales->id, $this->care->id, null], array_column($table['groups'], 'department_id'));
        $this->assertSame(['佐藤', '鈴木'], array_column($table['groups'][0]['users'], 'name'));
        $this->assertSame(['無所属'], array_column($table['groups'][2]['users'], 'name'));
        $this->assertNull($table['department_name']);
    }

    /**
     * @test
     */
    public function 日付は曜日と色分け_日曜と祝日は赤_土曜は青_を持つ(): void
    {
        $days = collect($this->build()['days'])->keyBy('date');

        $this->assertCount(6, $days);
        $this->assertSame(['土', 'blue', null], [$days['2026-09-19']['weekday'], $days['2026-09-19']['tone'], $days['2026-09-19']['holiday_name']]);
        $this->assertSame(['日', 'red'], [$days['2026-09-20']['weekday'], $days['2026-09-20']['tone']]);
        $this->assertSame(['red', '敬老の日'], [$days['2026-09-21']['tone'], $days['2026-09-21']['holiday_name']]);
        $this->assertSame(['red', '国民の休日'], [$days['2026-09-22']['tone'], $days['2026-09-22']['holiday_name']]);
        $this->assertSame(['木', 'normal'], [$days['2026-09-24']['weekday'], $days['2026-09-24']['tone']]);
        $this->assertSame([9, 19], [$days['2026-09-19']['month'], $days['2026-09-19']['day']]);
    }

    /**
     * @test
     */
    public function セルはシフトパターンの名前と色を持ち_シフトのない日は休みになる(): void
    {
        $this->shift($this->sato, '2026-09-19', $this->day);
        $this->shift($this->suzuki, '2026-09-19', $this->night);

        $sato = $this->build()['groups'][0]['users'][0]['cells'];
        $suzuki = $this->build()['groups'][0]['users'][1]['cells'];

        // 色はTailwindのクラス名から色コードに変換される
        $this->assertSame(
            ['is_work' => true, 'label' => 'A日勤', 'background' => '#DBEAFE', 'text' => '#1E40AF', 'leave' => null],
            $sato['2026-09-19']
        );
        // 色のないパターンは、画面と同じ青緑
        $this->assertSame(['B夜勤', '#CCFBF1', '#115E59'], [$suzuki['2026-09-19']['label'], $suzuki['2026-09-19']['background'], $suzuki['2026-09-19']['text']]);
        // シフトのない日は「休み」
        $this->assertSame(['is_work' => false, 'label' => '休み', 'background' => '#E5E7EB', 'text' => '#4B5563', 'leave' => null], $sato['2026-09-20']);
    }

    /**
     * @test
     */
    public function 出勤と休日の日数は期間の日数から数える(): void
    {
        $this->shift($this->sato, '2026-09-19', $this->day);
        $this->shift($this->sato, '2026-09-22', $this->day);

        $table = $this->build();
        $sato = $table['groups'][0]['users'][0];

        $this->assertSame([2, 4], [$sato['work_days'], $sato['rest_days']]);
        // 全員分の合計（6日間 × 4人 = 24人日のうち、出勤2）
        $this->assertSame([2, 22], [$table['totals']['work_days'], $table['totals']['rest_days']]);
    }

    /**
     * @test
     */
    public function 出勤人数は休みを数えず_承認済みの休暇の分を差し引く(): void
    {
        $this->shift($this->sato, '2026-09-19', $this->day);
        $this->shift($this->suzuki, '2026-09-19', $this->night);
        $this->shift($this->tanaka, '2026-09-19', $this->day);
        $this->shift($this->sato, '2026-09-21', $this->day);
        $this->shift($this->suzuki, '2026-09-21', $this->day);
        $this->leave($this->sato, '2026-09-21', 480); // 全日の有給
        $this->shift($this->tanaka, '2026-09-22', $this->day);
        $this->leave($this->tanaka, '2026-09-22', 240); // 半日有給（-0.5人）

        $table = $this->build();
        [$sales, $care] = $table['groups'];

        $this->assertSame(2.0, $sales['counts']['2026-09-19']);
        $this->assertSame(1.0, $care['counts']['2026-09-19']);
        $this->assertSame(3.0, $table['totals']['counts']['2026-09-19']);

        // 9/21: 営業は2人出勤予定から、全日の有給1人を差し引いて1人
        $this->assertSame(1.0, $sales['counts']['2026-09-21']);
        // 9/22: 介護は半日有給で0.5人
        $this->assertSame(0.5, $care['counts']['2026-09-22']);
        // シフトのない日は0人
        $this->assertSame(0.0, $table['totals']['counts']['2026-09-24']);
    }

    /**
     * @test
     */
    public function 休暇はセルに載り_全日と半日を見分けられる(): void
    {
        $this->shift($this->sato, '2026-09-21', $this->day);
        $this->leave($this->sato, '2026-09-21', 480);
        $this->shift($this->sato, '2026-09-22', $this->day);
        $this->leave($this->sato, '2026-09-22', 240);

        $cells = $this->build()['groups'][0]['users'][0]['cells'];

        $this->assertTrue($cells['2026-09-21']['leave']['is_full_day']);
        $this->assertSame('有', $cells['2026-09-21']['leave']['label']);
        $this->assertSame('#FFEED4', $cells['2026-09-21']['leave']['background']);
        $this->assertFalse($cells['2026-09-22']['leave']['is_full_day']);
        $this->assertSame('半', $cells['2026-09-22']['leave']['label']);
        // 半日有給でも、シフトの名前は残る
        $this->assertSame('A日勤', $cells['2026-09-22']['label']);
    }

    /**
     * @test
     */
    public function 部署を選ぶと_その部署のスタッフだけになる(): void
    {
        $this->shift($this->sato, '2026-09-19', $this->day);
        $this->shift($this->tanaka, '2026-09-19', $this->day);

        $table = $this->build(departmentIds: [$this->care->id]);

        $this->assertSame('介護', $table['department_name']);
        $this->assertSame(['介護'], $table['department_names']);
        $this->assertSame(['介護'], array_column($table['groups'], 'name'));
        $this->assertSame(['田中'], array_column($table['groups'][0]['users'], 'name'));
        $this->assertSame(1.0, $table['totals']['counts']['2026-09-19']);
    }

    /**
     * @test
     *
     * クライアント要望: 部署を一つだけでなく、2つ以上選択できるようにしてほしい。
     */
    public function 複数の部署を選ぶと_選んだ部署だけが部署の並び順で出力される(): void
    {
        $this->shift($this->sato, '2026-09-19', $this->day);
        $this->shift($this->tanaka, '2026-09-19', $this->day);
        $this->shift($this->noDepartment, '2026-09-19', $this->day);

        // 選んだ順（介護 → 営業）ではなく、画面と同じ部署の並び（営業 → 介護）にする。未所属は含めない
        $table = $this->build(departmentIds: [$this->care->id, $this->sales->id]);

        $this->assertSame(['営業', '介護'], array_column($table['groups'], 'name'));
        $this->assertSame('営業・介護', $table['department_name']);
        $this->assertSame(['営業', '介護'], $table['department_names']);
        $this->assertSame(['佐藤', '鈴木'], array_column($table['groups'][0]['users'], 'name'));
        // 合計は、選んだ部署のスタッフだけ（営業の佐藤と介護の田中。未所属の1人は含めない）
        $this->assertSame(2.0, $table['totals']['counts']['2026-09-19']);
    }

    /**
     * @test
     */
    public function 部署を指定しなければ全部署で_未所属も含まれる(): void
    {
        $table = $this->build();

        $this->assertNull($table['department_name']);
        $this->assertSame([], $table['department_names']);
        $this->assertSame(['営業', '介護', '未所属'], array_column($table['groups'], 'name'));
    }

    /**
     * @test
     */
    public function 閲覧できる範囲のスタッフだけを出力する(): void
    {
        $table = $this->build(viewable: [$this->sato->id, $this->tanaka->id]);

        $this->assertSame(['佐藤'], array_column($table['groups'][0]['users'], 'name'));
        $this->assertSame(['営業', '介護'], array_column($table['groups'], 'name'));
    }

    /**
     * @test
     */
    public function 凡例にシフトパターンの名前_時間_色を出す(): void
    {
        $patterns = collect($this->build()['patterns'])->keyBy('name');

        $this->assertSame(['09:00', '18:00', '#DBEAFE', '#1E40AF'], [
            $patterns['A日勤']['start_time'], $patterns['A日勤']['end_time'], $patterns['A日勤']['background'], $patterns['A日勤']['text'],
        ]);
        $this->assertSame('#CCFBF1', $patterns['B夜勤']['background']);
    }

    /**
     * @test
     */
    public function スタッフがいない部署を選んでも_空の表を返す(): void
    {
        $empty = Department::factory()->forCompany($this->company->id)->create(['name' => '空の部署']);

        $table = $this->build(departmentIds: [$empty->id]);

        $this->assertSame([], $table['groups']);
        $this->assertSame('空の部署', $table['department_name']);
    }
}

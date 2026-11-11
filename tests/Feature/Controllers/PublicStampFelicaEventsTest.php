<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * FeliCa打刻専用画面（ブラウザ）向けの打刻結果イベント取得APIの検証。
 *
 * 常駐アプリは /stamp/{uuid}/felica にサーバー側で直接POSTするため、
 * 打刻専用画面はカードをかざした結果を知る手段を持たない。
 * このエンドポイントをポーリングすることで、成功・失敗のトーストを表示できる。
 */
class PublicStampFelicaEventsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    private const IDM = '0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.felica_stamp_cooldown_seconds' => 0]);

        $this->company = Company::factory()->create(['company_code' => '910101']);
        $this->user = User::factory()->create([
            'name' => 'テスト太郎',
            'employee_code' => '000201',
            'felica_idm' => self::IDM,
        ]);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function tap(array $payload = ['idm' => self::IDM]): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/stamp/{$this->company->uuid}/felica", $payload);
    }

    private function events(int $sinceId): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/stamp/{$this->company->uuid}/felica-events?since_id={$sinceId}");
    }

    private function baseline(): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/stamp/{$this->company->uuid}/felica-events");
    }

    /**
     * @test
     */
    public function since_idを省略した初回アクセスはイベントを返さず基準idだけ返す(): void
    {
        // Arrange: 既に1件打刻しておく
        $this->tap()->assertOk();

        // Act: since_id を省略してアクセス
        $response = $this->baseline();

        // Assert: 既存の試行はトースト表示しないが、lastId は最新を指す
        $response->assertOk()->assertJson(['events' => []]);
        $this->assertGreaterThan(0, $response->json('lastId'));
    }

    /**
     * @test
     */
    public function since_idに0を指定すると全件取得できる(): void
    {
        // Arrange
        $this->tap()->assertOk();

        // Act: since_id=0 を明示的に指定（画面を開いた後の実際のポーリングを想定）
        $response = $this->events(0);

        // Assert: 省略時と異なり、id > 0 の試行はすべて返る
        $this->assertCount(1, $response->json('events'));
    }

    /**
     * @test
     */
    public function 成功した打刻はイベントとして取得できる(): void
    {
        // Arrange
        $before = $this->baseline()->json('lastId');

        // Act
        $this->tap()->assertOk();
        $response = $this->events($before);

        // Assert
        $response->assertOk();
        $events = $response->json('events');
        $this->assertCount(1, $events);
        $this->assertSame('success', $events[0]['status']);
        $this->assertSame('出勤を記録しました。', $events[0]['message']);
        $this->assertSame('テスト太郎', $events[0]['userName']);
        $this->assertSame('0123****cdef', $events[0]['maskedIdm']);
    }

    /**
     * @test
     *
     * 読み取り機の多重発火による重複は、成功直後は警告を出さないよう
     * 抑制される（PublicStampService::suppressCooldownNotificationAfterSuccess）。
     * そのため、直前にFeliCaで成功している場合は、直後のクールダウン拒否は
     * イベントとして残らない。
     */
    public function 成功直後の重複打刻防止はイベントとして残らない(): void
    {
        // Arrange
        config(['attendance.felica_stamp_cooldown_seconds' => 10]);
        $before = $this->baseline()->json('lastId');
        $this->tap()->assertOk();

        // Act: 直後に再度かざして拒否させる（読み取り機の多重発火を模す）
        $this->tap()->assertStatus(429);
        $response = $this->events($before);

        // Assert: 成功イベントのみで、重複防止の警告イベントは残らない
        $events = $response->json('events');
        $this->assertCount(1, $events);
        $this->assertSame('success', $events[0]['status']);
    }

    /**
     * @test
     *
     * 直前の打刻がFeliCa経由の成功ではない場合（スタッフ画面や管理者による
     * 手動登録など、別経路で直近に打刻済みの場合）は抑制フラグが立たない
     * ため、クールダウン拒否は従来どおりイベントとして取得できる。
     */
    public function 別経路で直近に打刻済みの場合はクールダウン拒否がイベントとして取得できる(): void
    {
        // Arrange: FeliCa以外の経路（スタッフ画面等を想定）で直近に出勤済み
        config(['attendance.felica_stamp_cooldown_seconds' => 10]);
        app(\App\Services\PublicStampService::class)->clockIn($this->company->id, $this->user->id);
        $before = $this->baseline()->json('lastId');

        // Act: 直後にFeliCaでかざして拒否させる
        $this->tap()->assertStatus(429);
        $response = $this->events($before);

        // Assert
        $events = $response->json('events');
        $this->assertCount(1, $events);
        $this->assertSame('cooldown', $events[0]['status']);
        $this->assertSame('重複打刻防止のため受け付けませんでした', $events[0]['message']);
        $this->assertStringContainsString('秒後にもう一度カードをかざしてください', $events[0]['detail']);
    }

    /**
     * @test
     */
    public function 未登録カードの試行もイベントとして取得できる(): void
    {
        // Arrange
        $before = $this->baseline()->json('lastId');

        // Act
        $this->tap(['idm' => 'ffffffffffffffff'])->assertNotFound();
        $response = $this->events($before);

        // Assert
        $events = $response->json('events');
        $this->assertCount(1, $events);
        $this->assertSame('unregistered', $events[0]['status']);
        $this->assertNull($events[0]['userName']);
    }

    /**
     * @test
     */
    public function 他社のイベントは取得できない(): void
    {
        // Arrange
        $other = Company::factory()->create(['company_code' => '910102']);
        $this->tap()->assertOk();

        // Act: 他社のuuidでポーリング
        $response = $this->getJson("/stamp/{$other->uuid}/felica-events?since_id=0");

        // Assert
        $response->assertOk()->assertJson(['events' => [], 'lastId' => 0]);
    }

    /**
     * @test
     */
    public function 存在しない会社_uui_dは404を返す(): void
    {
        // Act
        $response = $this->getJson('/stamp/00000000-0000-0000-0000-000000000000/felica-events?since_id=0');

        // Assert
        $response->assertNotFound();
    }

    private const IDM_B = 'abcdef0123456789';

    private function departmentWith(User $user, string $name, bool $primary = true): Department
    {
        $department = Department::query()->create(['company_id' => $this->company->id, 'name' => $name]);
        $user->departments()->attach($department->id, ['is_primary' => $primary]);

        return $department;
    }

    private function staffB(): User
    {
        $user = User::factory()->create(['name' => 'テスト次郎', 'employee_code' => '000202', 'felica_idm' => self::IDM_B]);
        $user->companies()->attach($this->company->id, ['is_primary' => true]);

        return $user;
    }

    /**
     * @return array<int, string|null>
     */
    private function userNames(int $sinceId, ?int $departmentId): array
    {
        $query = $departmentId === null ? '' : "&department_id={$departmentId}";

        return array_column($this->getJson("/stamp/{$this->company->uuid}/felica-events?since_id={$sinceId}{$query}")->assertOk()->json('events'), 'userName');
    }

    /**
     * @test
     */
    public function 事業所を指定すると、その事業所のスタッフの打刻だけが返る(): void
    {
        $honsha = $this->departmentWith($this->user, '本社');
        $other = $this->staffB();
        $jigyosho = $this->departmentWith($other, 'A事業所');
        $since = (int) $this->baseline()->json('lastId');

        $this->tap()->assertOk();
        $this->tap(['idm' => self::IDM_B])->assertOk();

        $this->assertSame(['テスト太郎'], $this->userNames($since, $honsha->id));
        $this->assertSame(['テスト次郎'], $this->userNames($since, $jigyosho->id));
        $this->assertSame(['テスト太郎', 'テスト次郎'], $this->userNames($since, null));
    }

    /**
     * @test
     */
    public function 事業所を指定しても、スタッフを特定できない打刻（未登録のカード）は返る(): void
    {
        $honsha = $this->departmentWith($this->user, '本社');
        $since = (int) $this->baseline()->json('lastId');

        $this->tap(['idm' => 'ffffffffffffffff'])->assertNotFound();

        $this->assertSame([null], $this->userNames($since, $honsha->id));
    }

    /**
     * @test
     */
    public function 主所属でない部署を指定しても、そのスタッフの打刻は返らない(): void
    {
        $this->departmentWith($this->user, '本社');
        $secondary = $this->departmentWith($this->user, 'A事業所', primary: false);
        $since = (int) $this->baseline()->json('lastId');

        $this->tap()->assertOk();

        $this->assertSame([], $this->userNames($since, $secondary->id));
    }

    /**
     * @test
     */
    public function 他の会社の部署を指定した場合は絞り込まずに全て返す(): void
    {
        $this->departmentWith($this->user, '本社');
        $otherCompany = Company::factory()->create();
        $foreign = Department::query()->create(['company_id' => $otherCompany->id, 'name' => '他社の部署']);
        $since = (int) $this->baseline()->json('lastId');

        $this->tap()->assertOk();

        $this->assertSame(['テスト太郎'], $this->userNames($since, $foreign->id));
    }

    /**
     * @test
     */
    public function 絞り込み中でも、他の事業所の打刻のあとに続く自分の事業所の打刻を取りこぼさない(): void
    {
        $honsha = $this->departmentWith($this->user, '本社');
        $other = $this->staffB();
        $this->departmentWith($other, 'A事業所');
        $since = (int) $this->baseline()->json('lastId');

        $this->tap(['idm' => self::IDM_B])->assertOk();
        $first = $this->getJson("/stamp/{$this->company->uuid}/felica-events?since_id={$since}&department_id={$honsha->id}")->json();
        $this->tap()->assertOk();
        $second = $this->getJson("/stamp/{$this->company->uuid}/felica-events?since_id={$first['lastId']}&department_id={$honsha->id}")->json();

        $this->assertSame([], $first['events']);
        $this->assertSame(['テスト太郎'], array_column($second['events'], 'userName'));
    }

    /**
     * @test
     */
    public function 打刻専用画面に事業所の一覧が渡される(): void
    {
        $this->departmentWith($this->user, '本社');

        $this->get("/stamp/{$this->company->uuid}")->assertInertia(
            fn ($page) => $page->component('Public/Stamp')->where('departments.0.name', '本社')
        );
    }

    /**
     * @test
     */
    public function 事業所を複数指定すると、それらの事業所のスタッフの打刻が返る(): void
    {
        $honsha = $this->departmentWith($this->user, '本社');
        $second = $this->staffB();
        $seisan = $this->departmentWith($second, '生産部');
        $third = User::factory()->create(['name' => 'テスト三郎', 'employee_code' => '000203', 'felica_idm' => '1111222233334444']);
        $third->companies()->attach($this->company->id, ['is_primary' => true]);
        $this->departmentWith($third, '貴崎店');
        $since = (int) $this->baseline()->json('lastId');

        $this->tap()->assertOk();
        $this->tap(['idm' => self::IDM_B])->assertOk();
        $this->tap(['idm' => '1111222233334444'])->assertOk();

        $query = "department_ids[]={$honsha->id}&department_ids[]={$seisan->id}";
        $names = array_column(
            $this->getJson("/stamp/{$this->company->uuid}/felica-events?since_id={$since}&{$query}")->assertOk()->json('events'),
            'userName'
        );

        $this->assertSame(['テスト太郎', 'テスト次郎'], $names);
    }

    /**
     * @test
     */
    public function 複数指定の中に他の会社の部署が混ざっていても、有効な部署だけで絞り込む(): void
    {
        $honsha = $this->departmentWith($this->user, '本社');
        $other = $this->staffB();
        $this->departmentWith($other, 'A事業所');
        $foreign = Department::query()->create(['company_id' => Company::factory()->create()->id, 'name' => '他社の部署']);
        $since = (int) $this->baseline()->json('lastId');

        $this->tap()->assertOk();
        $this->tap(['idm' => self::IDM_B])->assertOk();

        $names = array_column(
            $this->getJson("/stamp/{$this->company->uuid}/felica-events?since_id={$since}&department_ids[]={$honsha->id}&department_ids[]={$foreign->id}")->json('events'),
            'userName'
        );

        $this->assertSame(['テスト太郎'], $names);
    }
}

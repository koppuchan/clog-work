<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Company;
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
     */
    public function 重複打刻防止で拒否された試行もイベントとして取得できる(): void
    {
        // Arrange
        config(['attendance.felica_stamp_cooldown_seconds' => 10]);
        $before = $this->baseline()->json('lastId');
        $this->tap()->assertOk();

        // Act: 直後に再度かざして拒否させる
        $this->tap()->assertStatus(429);
        $response = $this->events($before);

        // Assert
        $events = $response->json('events');
        $this->assertCount(2, $events);
        $this->assertSame('success', $events[0]['status']);
        $this->assertSame('cooldown', $events[1]['status']);
        $this->assertSame('重複打刻防止のため受け付けませんでした', $events[1]['message']);
        $this->assertStringContainsString('秒後にもう一度カードをかざしてください', $events[1]['detail']);
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
}

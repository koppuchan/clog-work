<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\PublicStampService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * FeliCa打刻アプリ（常駐アプリ v0.2.6）からの打刻APIの検証。
 *
 * アプリ側の実装:
 *   POST {serverUrl}/stamp/{companyUuid}/felica
 *   Body : { "idm": "0123456789abcdef", "intent": "break-start" }
 *   成功条件: HTTP 2xx かつ レスポンスの success === true
 */
class PublicStampFelicaTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    private const IDM = '0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        // 重複防止のクールダウンは既定で有効だが、打刻種別の判定を検証する
        // テストでは連続して打刻するため無効化する。クールダウン自体の検証は
        // 専用のテストで行う。
        config(['attendance.felica_stamp_cooldown_seconds' => 0]);

        $this->company = Company::factory()->create(['company_code' => '910001']);
        $this->user = User::factory()->create([
            'name' => '打刻 太郎',
            'employee_code' => '000101',
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

    /**
     * @test
     */
    public function 未出勤の状態でカードをかざすと出勤が記録される(): void
    {
        // Act
        $response = $this->tap();

        // Assert
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '出勤を記録しました。',
                'user' => ['id' => $this->user->id, 'name' => '打刻 太郎'],
            ]);
    }

    /**
     * @test
     */
    public function 勤務中にカードをかざすと退勤が記録される(): void
    {
        // Arrange
        app(PublicStampService::class)->clockIn($this->company->id, $this->user->id);

        // Act
        $response = $this->tap();

        // Assert
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '退勤を記録しました。',
        ]);
    }

    /**
     * @test
     */
    public function 勤務中に休憩開始モードでかざすと休憩開始が記録される(): void
    {
        // Arrange
        app(PublicStampService::class)->clockIn($this->company->id, $this->user->id);

        // Act
        $response = $this->tap(['idm' => self::IDM, 'intent' => 'break-start']);

        // Assert
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '休憩開始を記録しました。',
        ]);
    }

    /**
     * @test
     *
     * 休憩開始モードを付けたまま出勤していない状態でカードをかざすと、
     * 以前は intent が無視されて出勤打刻として記録されてしまい、
     * 「休憩の打刻がどうしても入らない」（実際は毎回出勤扱いになっていた）
     * という混乱の原因になっていた。出勤していないことを明確にエラーで
     * 知らせる。
     */
    public function 出勤していない状態で休憩開始モードでかざすとエラーになる(): void
    {
        // Act: 出勤していない状態で休憩開始モードのままかざす
        $response = $this->tap(['idm' => self::IDM, 'intent' => 'break-start']);

        // Assert: 出勤打刻にフォールバックせず、エラーで知らせる
        $response->assertStatus(422)->assertJson([
            'success' => false,
            'message' => '出勤していません。先に出勤打刻を行ってください。',
        ]);
        $this->assertSame(0, TimeRecord::query()->count());
    }

    /**
     * @test
     *
     * 打刻専用画面の「休憩開始」トグルは、これまでブラウザ内の状態のみで
     * FeliCa常駐アプリには一切伝わっていなかった（常駐アプリは自身の
     * ショートカットB/Escでしか休憩開始モードを持たない）。トグルを
     * サーバーに反映すれば、intentなしのタップでも休憩開始として
     * 記録できることを確認する。
     */
    public function 打刻専用画面のトグルで休憩開始モードを有効にするとintentなしでも休憩開始になる(): void
    {
        // Arrange: 出勤中 + 打刻専用画面のトグルで休憩開始モードを有効化
        app(PublicStampService::class)->clockIn($this->company->id, $this->user->id);
        $this->postJson("/stamp/{$this->company->uuid}/felica-break-mode", ['armed' => true])->assertOk();

        // Act: intentを付けずにかざす（常駐アプリ自身のショートカットは使っていない想定）
        $response = $this->tap();

        // Assert
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '休憩開始を記録しました。',
        ]);
    }

    /**
     * @test
     *
     * 休憩開始モードは1回タップしたら消費される（次のタップには影響しない）。
     */
    public function 打刻専用画面のトグルによる休憩開始モードは1回で消費される(): void
    {
        // Arrange
        app(PublicStampService::class)->clockIn($this->company->id, $this->user->id);
        $this->postJson("/stamp/{$this->company->uuid}/felica-break-mode", ['armed' => true])->assertOk();
        $this->tap()->assertOk();

        // Act: 休憩終了してから、休憩開始モードを有効化しないままもう一度かざす
        app(PublicStampService::class)->breakEnd($this->company->id, $this->user->id);
        $response = $this->tap();

        // Assert: 通常どおり退勤になる（休憩開始モードは残っていない）
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '退勤を記録しました。',
        ]);
    }

    /**
     * @test
     */
    public function 打刻専用画面のトグルを_of_fにすると休憩開始モードが解除される(): void
    {
        // Arrange
        app(PublicStampService::class)->clockIn($this->company->id, $this->user->id);
        $this->postJson("/stamp/{$this->company->uuid}/felica-break-mode", ['armed' => true])->assertOk();
        $this->postJson("/stamp/{$this->company->uuid}/felica-break-mode", ['armed' => false])->assertOk();

        // Act: intentなしでかざす
        $response = $this->tap();

        // Assert: 休憩開始モードは解除済みなので通常どおり退勤になる
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '退勤を記録しました。',
        ]);
    }

    /**
     * @test
     */
    public function 休憩中にカードをかざすと休憩終了が記録される(): void
    {
        // Arrange
        $service = app(PublicStampService::class);
        $service->clockIn($this->company->id, $this->user->id);
        $service->breakStart($this->company->id, $this->user->id);

        // Act: 休憩中は intent を問わず休憩終了になる
        $response = $this->tap();

        // Assert
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '休憩終了を記録しました。',
        ]);
    }

    /**
     * @test
     */
    public function 大文字の_i_dmでも同じカードとして扱われる(): void
    {
        // Act
        $response = $this->tap(['idm' => strtoupper(self::IDM)]);

        // Assert
        $response->assertOk()->assertJson(['success' => true]);
    }

    /**
     * @test
     */
    public function 未登録のカードは404を返す(): void
    {
        // Act
        $response = $this->tap(['idm' => 'ffffffffffffffff']);

        // Assert
        $response->assertNotFound()->assertJson(['success' => false]);
    }

    /**
     * @test
     */
    public function 他社の打刻端末からは打刻できない(): void
    {
        // Arrange
        $other = Company::factory()->create(['company_code' => '910002']);

        // Act
        $response = $this->postJson("/stamp/{$other->uuid}/felica", ['idm' => self::IDM]);

        // Assert
        $response->assertNotFound()->assertJson(['success' => false]);
    }

    /**
     * @test
     */
    public function 存在しない会社_uui_dは404を返す(): void
    {
        // Act
        $response = $this->postJson('/stamp/00000000-0000-0000-0000-000000000000/felica', ['idm' => self::IDM]);

        // Assert
        $response->assertNotFound()->assertJson(['success' => false]);
    }

    /**
     * @test
     */
    public function i_dmの形式が不正な場合は422を返す(): void
    {
        // Act
        $response = $this->tap(['idm' => 'not-a-valid-idm']);

        // Assert
        $response->assertStatus(422);
    }

    /**
     * @test
     */
    public function 退職済みのユーザーは打刻できない(): void
    {
        // Arrange
        $this->user->update([
            'is_retired' => true,
            'retirement_date' => now()->subDay()->toDateString(),
        ]);

        // Act
        $response = $this->tap();

        // Assert
        $response->assertStatus(400)->assertJson(['success' => false]);
    }

    /**
     * @test
     */
    public function 短時間に続けてかざすと二度目は受け付けない(): void
    {
        // Arrange
        config(['attendance.felica_stamp_cooldown_seconds' => 10]);
        $this->tap()->assertOk();

        // Act: 直後に再度かざす
        $response = $this->tap();

        // Assert: 出勤の直後に退勤が記録されてしまわないこと
        $response->assertStatus(429)->assertJson(['success' => false]);
        $this->assertTrue(
            app(\App\Services\PublicStampService::class)
                ->getCurrentStatus($this->company->id, $this->user->id)['isWorking']
        );
    }

    /**
     * @test
     *
     * NFCリーダーが1回のタップで複数回イベントを発火すると、クールダウン中の
     * 重複リクエストがサーバーに複数回届くことがある。打刻自体は
     * withFelicaStampLock()により1件しか登録されないが（別テストで担保）、
     * 重複防止の試行ログ(felica_stamp_attempts)が作られると、成功トーストと
     * 並んで「重複打刻防止」の警告トーストが表示され、ユーザーには1回
     * タップしただけなのに何かおかしいように見えてしまう
     * （実際のクライアント報告: 成功表示の直後に重複警告が出た）。
     * 成功直後のクールダウン拒否は読み取り機の多重発火によるものと
     * みなし、警告ログを一切残さない。
     */
    public function クールダウン中に3回かざしても重複防止ログは記録されない(): void
    {
        // Arrange
        config(['attendance.felica_stamp_cooldown_seconds' => 10]);
        $this->tap()->assertOk();

        // Act: クールダウン中にさらに2回かざす（リーダーの多重発火を模す）
        $this->tap()->assertStatus(429);
        $this->tap()->assertStatus(429);

        // Assert: 成功1件のみで、重複防止の警告ログは記録されない
        $this->assertSame(1, \App\Models\FelicaStampAttempt::query()->where('status', 'success')->count());
        $this->assertSame(0, \App\Models\FelicaStampAttempt::query()->where('status', 'cooldown')->count());
    }

    /**
     * @test
     */
    public function クールダウンを過ぎればもう一度打刻できる(): void
    {
        // Arrange
        config(['attendance.felica_stamp_cooldown_seconds' => 10]);
        $this->tap()->assertOk();

        // Act
        $this->travel(11)->seconds();
        $response = $this->tap();

        // Assert
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '退勤を記録しました。',
        ]);
    }

    /**
     * @test
     *
     * カードリーダーの多重起動やドライバの重複イベントで、同一ユーザーの
     * 打刻リクエストがほぼ同時に届くことがある。排他ロックがないと、
     * クールダウン判定(SELECT)から打刻登録(INSERT)までの間に競合し、
     * 本来1回のはずの打刻が2件登録されてしまう不具合の回帰テスト。
     *
     * ここでは「先行リクエストが処理中でロックを保持している」状況を、
     * ロックを直接取得したまま解放しないことで再現する。この状態で
     * かざしても、ロック取得待ちでタイムアウトし、打刻が作成されない
     * ことを確認する。
     */
    public function 処理中に重ねてかざしても打刻が2件登録されない(): void
    {
        // Arrange: 同一ユーザーの打刻ロックを先に取得したまま保持し、
        // 「別のリクエストが処理中」の状態を再現する
        $lock = Cache::lock("felica-stamp-lock:{$this->company->id}:{$this->user->id}", 60);
        $this->assertTrue($lock->get());

        try {
            // Act: ロックが解放されないまま、続けてかざす
            $response = $this->tap();

            // Assert: ロック取得待ちでタイムアウトし、重複防止と同様に拒否される。
            // 打刻は1件も作成されない
            $response->assertStatus(429)->assertJson(['success' => false]);
            $this->assertSame(0, TimeRecord::query()->count());
        } finally {
            $lock->release();
        }

        // Assert: ロック解放後は通常どおり打刻できる
        $this->tap()->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, TimeRecord::query()->count());
    }

    /**
     * @test
     */
    public function 日付をまたいだ退勤は文言で区別される(): void
    {
        // Arrange: 前日に出勤し、日付をまたいで退勤する
        $this->travelTo(now()->subDay()->setTime(22, 0));
        app(PublicStampService::class)->clockIn($this->company->id, $this->user->id);

        // Act: 翌日の早朝にかざす
        $this->travelTo(now()->addHours(8));
        $response = $this->tap();

        // Assert: 通常の退勤と区別できる文言になる
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '退勤（日付越え）を記録しました。',
        ]);
    }

    /**
     * @test
     */
    public function 同日中の退勤は通常の文言になる(): void
    {
        // Arrange
        $this->travelTo(now()->setTime(9, 0));
        app(PublicStampService::class)->clockIn($this->company->id, $this->user->id);

        // Act
        $this->travelTo(now()->addHours(9));
        $response = $this->tap();

        // Assert
        $response->assertOk()->assertJson([
            'success' => true,
            'message' => '退勤を記録しました。',
        ]);
    }
}

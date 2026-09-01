<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\PublicStampService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}

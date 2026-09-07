<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 打刻専用画面のパスワード再検証省略（検証済みトークン）の検証。
 *
 * verify-password と打刻APIの両方でbcryptのHash::checkを行うと体感速度が
 * 悪化するため、verify-password成功直後だけ有効な使い捨てトークンを発行し、
 * 直後の打刻APIではこれを優先してパスワードの再検証を省略する。
 * トークンが無効・期限切れ・対象ユーザー不一致の場合は、従来通り
 * パスワードを検証する（フォールバック）。
 */
class PublicStampVerifyTokenTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['company_code' => '920001']);
        $this->user = User::factory()->create(['name' => '検証 太郎']);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    private function verify(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/stamp/{$this->company->uuid}/verify-password", [
            'user_id' => $this->user->id,
            'password' => 'password',
        ]);
    }

    /**
     * @test
     */
    public function verify_passwordの成功時にトークンが発行される(): void
    {
        // Act
        $response = $this->verify();

        // Assert
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertIsString($response->json('verifyToken'));
        $this->assertNotSame('', $response->json('verifyToken'));
    }

    /**
     * @test
     */
    public function 発行されたトークンがあればパスワードなしでも打刻できる(): void
    {
        // Arrange
        $token = $this->verify()->json('verifyToken');

        // Act: パスワードは渡さず、トークンだけで打刻する
        $response = $this->postJson("/stamp/{$this->company->uuid}/clock-in", [
            'user_id' => $this->user->id,
            'verify_token' => $token,
        ]);

        // Assert
        $response->assertOk()->assertJson(['success' => true]);
    }

    /**
     * @test
     */
    public function トークンは一度使うと無効になる(): void
    {
        // Arrange
        $token = $this->verify()->json('verifyToken');
        $this->postJson("/stamp/{$this->company->uuid}/clock-in", [
            'user_id' => $this->user->id,
            'verify_token' => $token,
        ])->assertOk();

        // Act: 同じトークンをもう一度、誤ったパスワードで使い回そうとする
        $response = $this->postJson("/stamp/{$this->company->uuid}/break-start", [
            'user_id' => $this->user->id,
            'password' => 'wrong-password',
            'verify_token' => $token,
        ]);

        // Assert: トークンは再利用できず、パスワードも誤っているため失敗する
        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    /**
     * @test
     */
    public function トークンが無効な場合はパスワード検証にフォールバックする(): void
    {
        // Act & Assert: 無効なトークン + 正しいパスワード → 成功
        $this->postJson("/stamp/{$this->company->uuid}/clock-in", [
            'user_id' => $this->user->id,
            'password' => 'password',
            'verify_token' => 'invalid-token',
        ])->assertOk()->assertJson(['success' => true]);
    }

    /**
     * @test
     */
    public function トークンが無効かつパスワードも誤っていれば失敗する(): void
    {
        // Act
        $response = $this->postJson("/stamp/{$this->company->uuid}/clock-in", [
            'user_id' => $this->user->id,
            'password' => 'wrong-password',
            'verify_token' => 'invalid-token',
        ]);

        // Assert
        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    /**
     * @test
     */
    public function 他のユーザー宛のトークンは使えない(): void
    {
        // Arrange: 自分のトークンを発行
        $token = $this->verify()->json('verifyToken');

        $otherUser = User::factory()->create(['name' => '検証 花子']);
        $otherUser->companies()->attach($this->company->id, ['is_primary' => false]);

        // Act: 別ユーザーの打刻に、太郎のトークンを流用しようとする
        $response = $this->postJson("/stamp/{$this->company->uuid}/clock-in", [
            'user_id' => $otherUser->id,
            'password' => 'wrong-password',
            'verify_token' => $token,
        ]);

        // Assert: トークンはuser_id不一致で無効、パスワードも誤っているため失敗する
        $response->assertStatus(401)->assertJson(['success' => false]);
    }
}

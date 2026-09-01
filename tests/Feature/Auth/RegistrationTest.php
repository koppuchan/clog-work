<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\RegistrationCompleteMail;
use App\Mail\RegistrationVerificationMail;
use App\Models\Company;
use App\Models\RegistrationToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function registration_email_form_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    /**
     * @test
     */
    public function verification_email_is_sent_for_valid_email(): void
    {
        Mail::fake();

        $response = $this->post('/register/email', [
            'email' => 'newuser@example.com',
        ]);

        $response->assertRedirect(route('register.email-sent'));
        Mail::assertSent(RegistrationVerificationMail::class, function ($mail) {
            return $mail->hasTo('newuser@example.com');
        });
        $this->assertDatabaseHas('registration_tokens', [
            'email' => 'newuser@example.com',
        ]);
    }

    /**
     * @test
     */
    public function verification_email_is_not_sent_for_existing_user_email(): void
    {
        Mail::fake();

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post('/register/email', [
            'email' => 'existing@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
        Mail::assertNothingSent();
    }

    /**
     * @test
     */
    public function email_sent_page_can_be_rendered(): void
    {
        $response = $this->get('/register/email-sent');

        $response->assertStatus(200);
    }

    /**
     * @test
     */
    public function user_setup_page_is_shown_for_valid_token(): void
    {
        $token = RegistrationToken::create([
            'email' => 'test@example.com',
            'token' => 'valid-token-string',
            'expires_at' => CarbonImmutable::now()->addHours(24),
        ]);

        $response = $this->get('/register/verify/valid-token-string');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Register/UserSetup')
            ->has('token')
            ->has('email')
        );

        // トークンが検証済みになっていることを確認
        $this->assertNotNull($token->fresh()->verified_at);
    }

    /**
     * @test
     */
    public function user_setup_page_redirects_for_invalid_token(): void
    {
        $response = $this->get('/register/verify/invalid-token');

        $response->assertRedirect(route('register.email'));
        $response->assertSessionHasErrors(['token']);
    }

    /**
     * @test
     */
    public function user_setup_page_redirects_for_expired_token(): void
    {
        RegistrationToken::create([
            'email' => 'test@example.com',
            'token' => 'expired-token',
            'expires_at' => CarbonImmutable::now()->subHour(),
        ]);

        $response = $this->get('/register/verify/expired-token');

        $response->assertRedirect(route('register.email'));
        $response->assertSessionHasErrors(['token']);
    }

    /**
     * @test
     */
    public function user_info_can_be_stored_and_redirected_to_company_setup(): void
    {
        $token = RegistrationToken::create([
            'email' => 'test@example.com',
            'token' => 'verified-token',
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => CarbonImmutable::now(),
        ]);

        $response = $this->post('/register/user', [
            'token' => 'verified-token',
            'name' => 'テスト ユーザー',
            'name_kana' => 'テスト ユーザー',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Register/CompanySetup')
            ->has('token')
            ->has('email')
            ->has('userName')
        );

        // セッションにユーザー情報が保存されていることを確認
        $this->assertEquals('verified-token', session('registration.token'));
        $this->assertEquals('テスト ユーザー', session('registration.user.name'));
    }

    /**
     * @test
     */
    public function user_info_cannot_be_stored_with_invalid_token(): void
    {
        $response = $this->post('/register/user', [
            'token' => 'invalid-token',
            'name' => 'テスト ユーザー',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.email'));
        $response->assertSessionHasErrors(['token']);
    }

    /**
     * @test
     */
    public function registration_can_be_completed(): void
    {
        Mail::fake();

        $token = RegistrationToken::create([
            'email' => 'newadmin@example.com',
            'token' => 'complete-token',
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => CarbonImmutable::now(),
        ]);

        // セッションにユーザー情報を設定（パスワードはコントローラーと同様にハッシュ済みで保存）
        session([
            'registration.token' => 'complete-token',
            'registration.user' => [
                'name' => '管理者 太郎',
                'name_kana' => 'カンリシャ タロウ',
                'password' => Hash::make('password123'),
            ],
        ]);

        $response = $this->post('/register/company', [
            'token' => 'complete-token',
            'company_name' => 'テスト株式会社',
        ]);

        $response->assertRedirect(route('admin.shifts'));
        $response->assertSessionHas('success', '登録が完了しました。ログイン情報をメールでお送りしました。');

        // ユーザーが作成されていることを確認
        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@example.com',
            'name' => '管理者 太郎',
        ]);

        // 会社が作成されていることを確認
        $this->assertDatabaseHas('companies', [
            'name' => 'テスト株式会社',
        ]);

        // ユーザーが管理者としてログインしていることを確認
        $this->assertAuthenticated('admin');

        // トークンが削除されていることを確認
        $this->assertDatabaseMissing('registration_tokens', [
            'token' => 'complete-token',
        ]);

        // 登録完了メールが送信されていることを確認
        Mail::assertSent(RegistrationCompleteMail::class, function ($mail) {
            return $mail->hasTo('newadmin@example.com');
        });
    }

    /**
     * @test
     */
    public function registration_fails_with_mismatched_session_token(): void
    {
        RegistrationToken::create([
            'email' => 'test@example.com',
            'token' => 'session-token',
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => CarbonImmutable::now(),
        ]);

        // セッションに異なるトークンを設定
        session([
            'registration.token' => 'different-token',
            'registration.user' => [
                'name' => 'テスト ユーザー',
                'password' => Hash::make('password123'),
            ],
        ]);

        $response = $this->post('/register/company', [
            'token' => 'session-token',
            'company_name' => 'テスト株式会社',
        ]);

        $response->assertRedirect(route('register.email'));
        $response->assertSessionHasErrors(['token']);
    }

    /**
     * @test
     */
    public function company_code_is_generated_as_numeric(): void
    {
        Mail::fake();

        $token = RegistrationToken::create([
            'email' => 'test@example.com',
            'token' => 'numeric-test-token',
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => CarbonImmutable::now(),
        ]);

        session([
            'registration.token' => 'numeric-test-token',
            'registration.user' => [
                'name' => 'テスト ユーザー',
                'password' => Hash::make('password123'),
            ],
        ]);

        $this->post('/register/company', [
            'token' => 'numeric-test-token',
            'company_name' => '数値テスト株式会社',
        ]);

        $company = Company::where('name', '数値テスト株式会社')->first();
        $this->assertNotNull($company);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $company->company_code);
    }

    /**
     * @test
     */
    public function employee_code_of_owner_is_fixed_value(): void
    {
        Mail::fake();
        config(['attendance.owner_employee_code' => '009999']);

        // 既存のユーザーを作成（連番採番であれば 000006 が振られる状況）
        User::factory()->create(['employee_code' => '000005']);

        $token = RegistrationToken::create([
            'email' => 'sequential@example.com',
            'token' => 'sequential-token',
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => CarbonImmutable::now(),
        ]);

        session([
            'registration.token' => 'sequential-token',
            'registration.user' => [
                'name' => 'テスト ユーザー',
                'password' => Hash::make('password123'),
            ],
        ]);

        $this->post('/register/company', [
            'token' => 'sequential-token',
            'company_name' => '連番テスト株式会社',
        ]);

        $user = User::where('email', 'sequential@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('009999', $user->employee_code);
    }

    /**
     * @test
     */
    public function old_unverified_tokens_are_deleted_when_new_token_is_created(): void
    {
        Mail::fake();

        // 古いトークンを作成
        RegistrationToken::create([
            'email' => 'same@example.com',
            'token' => 'old-token',
            'expires_at' => CarbonImmutable::now()->addHours(24),
        ]);

        // 同じメールアドレスで新しいトークンをリクエスト
        $this->post('/register/email', [
            'email' => 'same@example.com',
        ]);

        // 古いトークンが削除されていることを確認
        $this->assertDatabaseMissing('registration_tokens', [
            'token' => 'old-token',
        ]);

        // 新しいトークンが作成されていることを確認
        $this->assertDatabaseHas('registration_tokens', [
            'email' => 'same@example.com',
        ]);
    }
}

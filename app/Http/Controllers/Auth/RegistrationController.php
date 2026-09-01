<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCompanyRequest;
use App\Http\Requests\RegisterEmailRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Mail\RegistrationCompleteMail;
use App\Services\MailDispatcher;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly MailDispatcher $mailDispatcher
    ) {}

    /**
     * メールアドレス入力画面を表示
     */
    public function showEmailForm(): Response
    {
        return Inertia::render('Auth/Register/Email');
    }

    /**
     * メールアドレスを送信してトークンを作成
     */
    public function sendVerificationEmail(RegisterEmailRequest $request): RedirectResponse
    {
        try {
            $this->registrationService->createTokenAndSendEmail($request->validated('email'));

            return redirect()->route('register.email-sent');
        } catch (BusinessException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }
    }

    /**
     * メール送信完了画面を表示
     */
    public function showEmailSent(): Response
    {
        return Inertia::render('Auth/Register/EmailSent');
    }

    /**
     * メール内のリンクからトークンを検証してユーザー設定画面へ
     */
    public function verifyEmail(string $token): Response|RedirectResponse
    {
        try {
            $registrationToken = $this->registrationService->markTokenAsVerified($token);

            return Inertia::render('Auth/Register/UserSetup', [
                'token' => $token,
                'email' => $registrationToken->email,
            ]);
        } catch (BusinessException $e) {
            return redirect()->route('register.email')
                ->withErrors(['token' => $e->getMessage()]);
        }
    }

    /**
     * ユーザー情報を保存して会社設定画面へ
     */
    public function storeUser(RegisterUserRequest $request): Response|RedirectResponse
    {
        $validated = $request->validated();
        $registrationToken = $this->registrationService->getVerifiedToken($validated['token']);

        if (! $registrationToken) {
            return redirect()->route('register.email')
                ->withErrors(['token' => '無効なトークンです。最初からやり直してください。']);
        }

        // セッションにユーザー情報を保存（パスワードはハッシュ化して保存）
        session([
            'registration.token' => $validated['token'],
            'registration.user' => [
                'name' => $validated['name'],
                'name_kana' => $validated['name_kana'] ?? null,
                'password' => Hash::make($validated['password']),
            ],
        ]);

        return Inertia::render('Auth/Register/CompanySetup', [
            'token' => $validated['token'],
            'email' => $registrationToken->email,
            'userName' => $validated['name'],
        ]);
    }

    /**
     * 会社情報を保存して登録を完了
     */
    public function storeCompany(RegisterCompanyRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // セッションからユーザー情報を取得
        $sessionToken = session('registration.token');
        $userData = session('registration.user');

        if ($sessionToken !== $validated['token'] || ! $userData) {
            return redirect()->route('register.email')
                ->withErrors(['token' => 'セッションが無効です。最初からやり直してください。']);
        }

        try {
            $result = $this->registrationService->completeRegistration(
                $validated['token'],
                $userData,
                ['name' => $validated['company_name']]
            );
        } catch (BusinessException $e) {
            return redirect()->route('register.email')
                ->withErrors(['error' => $e->getMessage()]);
        }

        // 登録完了メールを送信（失敗しても登録自体はロールバックしない）
        $this->mailDispatcher->send($result['user']->email, new RegistrationCompleteMail(
            user: $result['user'],
            companyCode: $result['company']->company_code,
            loginUrl: url('/admin/login')
        ), ['user_id' => $result['user']->id]);

        // セッションをクリア
        session()->forget(['registration.token', 'registration.user']);

        // 自動ログイン
        Auth::guard('admin')->login($result['user']);

        return redirect()->route('admin.shifts')
            ->with('success', '登録が完了しました。ログイン情報をメールでお送りしました。');
    }
}

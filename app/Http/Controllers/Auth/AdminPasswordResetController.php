<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者パスワードリセットコントローラー
 *
 * 管理者（role_id=1）のみが利用可能なパスワードリセット機能
 */
class AdminPasswordResetController extends Controller
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * パスワードリセット申請画面を表示
     */
    public function create(): Response
    {
        return Inertia::render('Auth/AdminForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * パスワードリセットリンクをメール送信
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'company_code' => ['required', 'string'],
            'employee_code' => ['required', 'string'],
            'email' => ['required', 'email'],
        ], [
            'company_code.required' => '会社コードを入力してください。',
            'employee_code.required' => '個人コードを入力してください。',
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => '正しいメールアドレスを入力してください。',
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        // 会社コードで会社を検索
        $company = $this->companyRepository->findByCompanyCode($request->string('company_code')->toString());

        if (! $company) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => '入力された情報に一致するアカウントが見つかりません。',
            ]);
        }

        // 個人コード+会社IDでユーザーを検索
        $user = $this->userRepository->findByEmployeeCodeAndCompanyId(
            $request->string('employee_code')->toString(),
            $company->id
        );

        // ユーザーが存在しない、管理者でない、メールが一致しない場合は同じエラー
        if (! $user || ! $user->isAdmin() || $user->email !== $request->string('email')->toString()) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => '入力された情報に一致するアカウントが見つかりません。',
            ]);
        }

        // パスワードリセットトークンを生成
        $token = Password::broker()->createToken($user);

        // リセットURLを生成
        $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

        // メール送信
        Mail::to($user->email)->send(new PasswordResetMail(
            user: $user,
            resetUrl: $resetUrl,
            companyCode: $company->company_code
        ));

        RateLimiter::clear($throttleKey);

        return redirect()->route('admin.password.sent');
    }

    /**
     * パスワードリセットメール送信完了画面を表示
     */
    public function showSent(): Response
    {
        return Inertia::render('Auth/PasswordResetSent');
    }

    /**
     * レート制限キーを生成
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            'admin-password-reset|'.$request->string('company_code').'|'.$request->string('employee_code').'|'.$request->ip()
        );
    }
}

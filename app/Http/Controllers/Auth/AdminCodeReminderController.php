<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\CodeReminderMail;
use App\Models\User;
use App\Services\MailDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者コード通知コントローラー
 *
 * 会社コード・個人コードを忘れた管理者向けにメールで通知する
 */
class AdminCodeReminderController extends Controller
{
    public function __construct(
        private readonly MailDispatcher $mailDispatcher
    ) {}

    /**
     * コード通知申請画面を表示
     */
    public function create(): Response
    {
        return Inertia::render('Auth/AdminForgotCode');
    }

    /**
     * コード通知メールを送信
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
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

        // メールアドレスでユーザーを検索
        $user = User::query()
            ->where('email', $request->string('email'))
            ->first();

        // ユーザーが存在し、管理者で、会社に所属している場合のみメール送信
        if ($user && $user->isAdmin()) {
            $company = $user->primaryCompany()->first();

            if ($company) {
                $this->mailDispatcher->send($user->email, new CodeReminderMail(
                    user: $user,
                    companyCode: $company->company_code
                ), ['user_id' => $user->id]);
            }
        }

        RateLimiter::hit($throttleKey);

        // ユーザーが見つからない場合も同じ完了画面へリダイレクト（情報漏洩防止）
        return redirect()->route('admin.code-reminder.sent');
    }

    /**
     * コード通知メール送信完了画面を表示
     */
    public function showSent(): Response
    {
        return Inertia::render('Auth/CodeReminderSent');
    }

    /**
     * レート制限キーを生成
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            'admin-code-reminder|'.$request->string('email').'|'.$request->ip()
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * パスワード変更必須チェックミドルウェア
 *
 * ユーザーがパスワード変更が必要な状態でログインしている場合、
 * パスワード変更画面にリダイレクトする
 */
class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $guard = 'staff'): Response
    {
        $user = Auth::guard($guard)->user();

        // ユーザーがログインしていて、パスワード変更が必要な場合
        if ($user && $user->must_change_password) {
            // パスワード変更ルートへのアクセスは許可
            if ($request->routeIs('staff.password-change', 'staff.password-change.store', 'staff.logout')) {
                return $next($request);
            }

            // その他のルートはパスワード変更画面にリダイレクト
            return redirect()->route('staff.password-change');
        }

        return $next($request);
    }
}

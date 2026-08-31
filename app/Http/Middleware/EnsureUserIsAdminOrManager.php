<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理者・責任者ロールチェックミドルウェア
 *
 * 管理画面へのアクセスを管理者（admin）と責任者（manager）のみに制限する
 */
class EnsureUserIsAdminOrManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $guard = 'admin'): Response
    {
        $user = Auth::guard($guard)->user();

        if (! $user || (! $user->isAdmin() && ! $user->isManager())) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/admin/login')->withErrors([
                'email' => '管理画面へのアクセス権限がありません。',
            ]);
        }

        return $next($request);
    }
}

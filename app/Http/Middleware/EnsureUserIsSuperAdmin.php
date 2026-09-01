<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * スーパー管理画面へのアクセスを制限するミドルウェア
 *
 * スーパー管理画面はSaaS運営者が全事業所を横断して操作するための画面のため、
 * 各事業所の管理者ではなく、運営者として明示的に指定されたユーザーのみ許可する。
 */
class EnsureUserIsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_super_admin) {
            abort(403, 'この画面へのアクセス権限がありません。');
        }

        return $next($request);
    }
}

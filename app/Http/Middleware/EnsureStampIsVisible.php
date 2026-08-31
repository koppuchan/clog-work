<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\StampVisibilityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 打刻画面表示チェックミドルウェア
 *
 * 会社・部署・ユーザーのいずれかで打刻画面が非表示に設定されている場合、
 * 打刻画面へのアクセスを制限しシフト画面にリダイレクトする。
 */
class EnsureStampIsVisible
{
    public function __construct(
        private readonly StampVisibilityService $stampVisibilityService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('staff')->user();

        if ($user && $this->stampVisibilityService->isStampHidden($user)) {
            return redirect()->route('staff.shifts');
        }

        return $next($request);
    }
}

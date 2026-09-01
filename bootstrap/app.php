<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\LogRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // 未認証時のリダイレクト先をガードごとに設定
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('staff/*') || $request->is('staff')) {
                return '/staff/login';
            }

            return '/admin/login';
        });

        // 公開打刻ルートはパスワード認証済みのためCSRF検証を除外
        $middleware->validateCsrfTokens(except: [
            'stamp/*',
        ]);

        // ミドルウェアエイリアス
        $middleware->alias([
            'admin_or_manager' => \App\Http\Middleware\EnsureUserIsAdminOrManager::class,
            'stamp_visible' => \App\Http\Middleware\EnsureStampIsVisible::class,
            'super_admin' => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
        ]);

        // 認証済みユーザーのリダイレクト先（guestミドルウェア用）
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('staff/*') || $request->is('staff')) {
                return '/staff/stamp';
            }

            return '/admin/shifts';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

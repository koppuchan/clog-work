<?php

namespace App\Http\Middleware;

use App\Services\StampVisibilityService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // URLパスから対象ガードを厳密に決定する。
        // admin/staffの両ガードに同時にログインできてしまう状況に備え、
        // /admin/* は admin、/staff/* は staff のみを参照する。
        // それ以外（ログイン画面や / など）は admin → staff の順で参照する。
        if ($request->is('admin', 'admin/*')) {
            $user = $request->user('admin');
        } elseif ($request->is('staff', 'staff/*')) {
            $user = $request->user('staff');
        } else {
            $user = $request->user('admin') ?? $request->user('staff');
        }

        // スタッフ画面の場合のみ、打刻画面の表示判定を共有する
        $isStampHidden = false;
        if ($request->is('staff', 'staff/*')) {
            $staffUser = $request->user('staff');
            if ($staffUser) {
                $stampVisibilityService = app(StampVisibilityService::class);
                $isStampHidden = $stampVisibilityService->isStampHidden($staffUser);
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ],
            'isStampHidden' => $isStampHidden,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
            ],
        ];
    }
}

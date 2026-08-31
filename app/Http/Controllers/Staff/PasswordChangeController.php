<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PasswordChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 初回ログイン時のパスワード変更コントローラー
 */
class PasswordChangeController extends Controller
{
    /**
     * パスワード変更画面を表示
     */
    public function create(): Response
    {
        return Inertia::render('Staff/PasswordChange', [
            'user' => Auth::guard('staff')->user(),
        ]);
    }

    /**
     * パスワードを更新
     */
    public function store(PasswordChangeRequest $request): RedirectResponse
    {
        $user = Auth::guard('staff')->user();

        $user->update([
            'password' => Hash::make($request->validated('password')),
            'must_change_password' => false,
        ]);

        return redirect()->intended('/staff/stamp')->with('status', 'パスワードを変更しました。');
    }
}

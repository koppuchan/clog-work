<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the staff login view.
     */
    public function create(): Response
    {
        return Inertia::render('Staff/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming staff authentication request.
     */
    public function store(StaffLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // intended()はセッションに残った管理者URLへ飛ばす可能性があるため使わない
        return redirect('/staff/stamp');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/staff/login');
    }
}

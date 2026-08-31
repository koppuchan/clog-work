<?php

use App\Http\Controllers\Auth\AdminCodeReminderController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Staff\AuthenticatedSessionController as StaffAuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

// 新規会員登録（認証不要）
Route::middleware('guest')->group(function () {
    Route::get('register', [RegistrationController::class, 'showEmailForm'])
        ->name('register.email');

    Route::post('register/email', [RegistrationController::class, 'sendVerificationEmail'])
        ->name('register.send-email');

    Route::get('register/email-sent', [RegistrationController::class, 'showEmailSent'])
        ->name('register.email-sent');

    Route::get('register/verify/{token}', [RegistrationController::class, 'verifyEmail'])
        ->name('register.verify');

    Route::post('register/user', [RegistrationController::class, 'storeUser'])
        ->name('register.store-user');

    Route::post('register/company', [RegistrationController::class, 'storeCompany'])
        ->name('register.store-company');
});

// 管理者ログイン（adminガードで認証済みならリダイレクト）
Route::middleware('guest:admin')->group(function () {
    Route::get('admin/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('admin/login', [AuthenticatedSessionController::class, 'store']);
});

// 管理者パスワードリセット（認証状態に関わらずアクセス可能）
Route::get('admin/forgot-password', [AdminPasswordResetController::class, 'create'])
    ->name('admin.password.request');

Route::post('admin/forgot-password', [AdminPasswordResetController::class, 'store'])
    ->name('admin.password.email');

Route::get('admin/forgot-password/sent', [AdminPasswordResetController::class, 'showSent'])
    ->name('admin.password.sent');

// 管理者コード通知（認証状態に関わらずアクセス可能）
Route::get('admin/forgot-code', [AdminCodeReminderController::class, 'create'])
    ->name('admin.code-reminder.request');

Route::post('admin/forgot-code', [AdminCodeReminderController::class, 'store'])
    ->name('admin.code-reminder.store');

Route::get('admin/forgot-code/sent', [AdminCodeReminderController::class, 'showSent'])
    ->name('admin.code-reminder.sent');

// スタッフログイン（staffガードで認証済みならリダイレクト）
Route::middleware('guest:staff')->group(function () {
    Route::get('staff/login', [StaffAuthenticatedSessionController::class, 'create'])
        ->name('staff.login');

    Route::post('staff/login', [StaffAuthenticatedSessionController::class, 'store']);
});

// パスワードリセット申請（認証不要・ゲストのみ）
Route::middleware('guest')->group(function () {
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
});

// 管理者パスワードリセット実行（認証状態に関わらずアクセス可能）
// メールのリンクからアクセスするため、ログイン済みでもアクセスできる必要がある
Route::get('admin/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

Route::post('admin/reset-password', [NewPasswordController::class, 'store'])
    ->name('password.store');

// 管理者用認証済みルート
Route::middleware('auth:admin')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

// スタッフ用ログアウト
Route::middleware('auth:staff')->group(function () {
    Route::post('staff/logout', [StaffAuthenticatedSessionController::class, 'destroy'])
        ->name('staff.logout');
});

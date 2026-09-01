<?php

use App\Http\Controllers\Admin\LaborAlertController;
use App\Http\Controllers\Admin\RequestController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShiftController as AdminShiftController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkSummaryController;
use App\Http\Controllers\PublicStampController;
use App\Http\Controllers\Staff\PasswordChangeController;
use App\Http\Controllers\Staff\ReportController as StaffReportController;
use App\Http\Controllers\Staff\RequestController as StaffRequestController;
use App\Http\Controllers\Staff\ShiftController as StaffShiftController;
use App\Http\Controllers\Staff\StampController;
use App\Http\Controllers\SuperAdmin\CompanyController as SuperAdminCompanyController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\UserController as SuperAdminUserController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// テストページ（認証不要）
Route::get('/test', function () {
    return Inertia::render('Test');
});

// 公開打刻ページ（認証不要）
Route::prefix('stamp')->name('public.stamp.')->group(function () {
    Route::get('/{uuid}', [PublicStampController::class, 'index'])->name('index');
    Route::get('/{uuid}/status', [PublicStampController::class, 'status'])->name('status');
    Route::post('/{uuid}/verify-password', [PublicStampController::class, 'verifyPassword'])->name('verify-password');
    Route::post('/{uuid}/clock-in', [PublicStampController::class, 'clockIn'])->name('clock-in');
    Route::post('/{uuid}/clock-out', [PublicStampController::class, 'clockOut'])->name('clock-out');
    Route::post('/{uuid}/break-start', [PublicStampController::class, 'breakStart'])->name('break-start');
    Route::post('/{uuid}/break-end', [PublicStampController::class, 'breakEnd'])->name('break-end');
    // FeliCa打刻アプリ（常駐アプリ）からの打刻
    Route::post('/{uuid}/felica', [PublicStampController::class, 'felica'])->name('felica');
});

// ルートページ - 認証済みならadmin/shiftsへリダイレクト
Route::get('/', function () {
    if (auth()->guard('admin')->check()) {
        return redirect('/admin/shifts');
    }

    if (auth()->guard('staff')->check()) {
        $staffUser = auth()->guard('staff')->user();
        $stampVisibility = app(\App\Services\StampVisibilityService::class);
        if ($stampVisibility->isStampHidden($staffUser)) {
            return redirect('/staff/shifts');
        }

        return redirect('/staff/stamp');
    }

    return redirect('/admin/login');
});

// Admin routes - adminガードで認証 + 管理者・責任者のみアクセス可
Route::middleware(['auth:admin', 'verified', 'admin_or_manager'])->prefix('admin')->name('admin.')->group(function () {
    // シフト管理
    Route::get('/shifts', [AdminShiftController::class, 'index'])->name('shifts');
    Route::post('/shifts/upsert', [AdminShiftController::class, 'upsert'])->name('shifts.upsert');
    Route::delete('/shifts/{shift}', [AdminShiftController::class, 'destroy'])->name('shifts.destroy');
    Route::get('/shifts/users/{user}/info', [AdminShiftController::class, 'getUserShiftInfo'])->name('shifts.user-info');
    Route::get('/shifts/users/bulk/info', [AdminShiftController::class, 'getBulkUsersShiftInfo'])->name('shifts.bulk-user-info');

    // ユーザー管理
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::get('/users/new', [UserController::class, 'create'])->name('users.new');
    Route::post('/users/confirm', [UserController::class, 'confirm'])->name('users.confirm');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/csv-template', [UserController::class, 'downloadCsvTemplate'])->name('users.csv-template');
    Route::get('/users/csv-import', [UserController::class, 'showCsvImport'])->name('users.csv-import.show');
    Route::post('/users/csv-import', [UserController::class, 'importCsv'])->name('users.csv-import');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::post('/users/{user}/confirm', [UserController::class, 'confirmUpdate'])->name('users.confirm-update');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    // 申請管理
    Route::get('/applications', [RequestController::class, 'index'])->name('applications');
    Route::get('/applications/{id}', [RequestController::class, 'show'])->name('applications.show');
    Route::post('/applications/{id}/approve', [RequestController::class, 'approve'])->name('applications.approve');
    Route::post('/applications/{id}/reject', [RequestController::class, 'reject'])->name('applications.reject');
    Route::post('/applications/{id}/revert', [RequestController::class, 'revert'])->name('applications.revert');
    Route::post('/applications/{id}/cancel', [RequestController::class, 'cancel'])->name('applications.cancel');

    Route::get('/alerts', [LaborAlertController::class, 'index'])->name('alerts');

    Route::get('/reports', [WorkSummaryController::class, 'index'])->name('reports');
    Route::get('/reports/export/csv', [WorkSummaryController::class, 'exportCsv'])->name('reports.export.csv');
    Route::get('/reports/export/excel', [WorkSummaryController::class, 'exportExcel'])->name('reports.export.excel');
    Route::post('/reports', [WorkSummaryController::class, 'store'])->name('reports.store');
    Route::put('/reports/{id}', [WorkSummaryController::class, 'update'])->name('reports.update');
    Route::delete('/reports/{id}', [WorkSummaryController::class, 'destroy'])->name('reports.destroy');

    Route::get('/billing', function () {
        return Inertia::render('Admin/Billing');
    })->name('billing');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    // 部署の個別操作API
    Route::post('/settings/departments', [SettingController::class, 'storeDepartment'])->name('settings.departments.store');
    Route::put('/settings/departments/{id}', [SettingController::class, 'updateDepartment'])->name('settings.departments.update');
    Route::delete('/settings/departments/{id}', [SettingController::class, 'destroyDepartment'])->name('settings.departments.destroy');

    // シフトパターンの個別操作API
    Route::post('/settings/shift-patterns', [SettingController::class, 'storeShiftPattern'])->name('settings.shiftPatterns.store');
    Route::put('/settings/shift-patterns/{id}', [SettingController::class, 'updateShiftPattern'])->name('settings.shiftPatterns.update');
    Route::delete('/settings/shift-patterns/{id}', [SettingController::class, 'destroyShiftPattern'])->name('settings.shiftPatterns.destroy');
});

// Staff routes - staffガードで認証
Route::middleware(['auth:staff'])->prefix('staff')->name('staff.')->group(function () {
    // パスワード変更（ミドルウェア適用外）
    Route::get('/password-change', [PasswordChangeController::class, 'create'])->name('password-change');
    Route::post('/password-change', [PasswordChangeController::class, 'store'])->name('password-change.store');
});

// Staff routes - パスワード変更必須チェック付き
Route::middleware(['auth:staff', EnsurePasswordIsChanged::class])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', function () {
        $user = auth()->guard('staff')->user();
        $stampVisibility = app(\App\Services\StampVisibilityService::class);
        if ($stampVisibility->isStampHidden($user)) {
            return redirect('/staff/shifts');
        }

        return redirect('/staff/stamp');
    })->name('index');

    // シフト閲覧
    Route::get('/shifts', [StaffShiftController::class, 'index'])->name('shifts');

    // 打刻（stamp_visibleミドルウェアで非表示設定時はリダイレクト）
    Route::middleware(['stamp_visible'])->group(function () {
        Route::get('/stamp', [StampController::class, 'index'])->name('stamp');
        Route::post('/stamp/clock-in', [StampController::class, 'clockIn'])->name('stamp.clock-in');
        Route::post('/stamp/clock-out', [StampController::class, 'clockOut'])->name('stamp.clock-out');
        Route::post('/stamp/break-start', [StampController::class, 'breakStart'])->name('stamp.break-start');
        Route::post('/stamp/break-end', [StampController::class, 'breakEnd'])->name('stamp.break-end');
        Route::get('/stamp/status', [StampController::class, 'status'])->name('stamp.status');
    });

    // 勤務実績
    Route::get('/reports', [StaffReportController::class, 'index'])->name('reports');

    // 申請
    Route::post('/requests', [StaffRequestController::class, 'store'])->name('requests.store');
});

// SuperAdmin routes - SaaS運営者用。adminガードで認証したうえでスーパー管理者のみ許可
Route::middleware(['auth:admin', 'verified', 'super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/companies', [SuperAdminCompanyController::class, 'index'])->name('companies');
    Route::get('/companies/new', [SuperAdminCompanyController::class, 'create'])->name('companies.new');
    Route::post('/companies', [SuperAdminCompanyController::class, 'store'])->name('companies.store');

    Route::get('/users', [SuperAdminUserController::class, 'index'])->name('users');
});

// Breezeの認証ルートを読み込む
require __DIR__.'/auth.php';

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\ApplicationTypeRepository;
use App\Repositories\CompanyLaborAlertSettingRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CompanyShiftRoundingSettingRepository;
use App\Repositories\Contracts\ApplicationTypeRepositoryInterface;
use App\Repositories\Contracts\CompanyLaborAlertSettingRepositoryInterface;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\CompanyShiftRoundingSettingRepositoryInterface;
use App\Repositories\Contracts\CorrectionRequestDetailRepositoryInterface;
use App\Repositories\Contracts\DailyWorkSummaryRepositoryInterface;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Contracts\LaborAlertRepositoryInterface;
use App\Repositories\Contracts\PermissionResourceRepositoryInterface;
use App\Repositories\Contracts\PermissionScopeRepositoryInterface;
use App\Repositories\Contracts\RegistrationTokenRepositoryInterface;
use App\Repositories\Contracts\RequestRepositoryInterface;
use App\Repositories\Contracts\RolePermissionRepositoryInterface;
use App\Repositories\Contracts\ShiftColorRepositoryInterface;
use App\Repositories\Contracts\ShiftPatternRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Repositories\Contracts\TimeRecordCorrectionRepositoryInterface;
use App\Repositories\Contracts\TimeRecordCorrectionRequestRepositoryInterface;
use App\Repositories\Contracts\TimeRecordRepositoryInterface;
use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\CorrectionRequestDetailRepository;
use App\Repositories\DailyWorkSummaryRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\LaborAlertRepository;
use App\Repositories\PermissionResourceRepository;
use App\Repositories\PermissionScopeRepository;
use App\Repositories\RegistrationTokenRepository;
use App\Repositories\RequestRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\ShiftColorRepository;
use App\Repositories\ShiftPatternRepository;
use App\Repositories\ShiftRepository;
use App\Repositories\TimeRecordCorrectionRepository;
use App\Repositories\TimeRecordCorrectionRequestRepository;
use App\Repositories\TimeRecordRepository;
use App\Repositories\UserPermissionRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(CompanyRepositoryInterface::class, CompanyRepository::class);
        $this->app->bind(DailyWorkSummaryRepositoryInterface::class, DailyWorkSummaryRepository::class);
        $this->app->bind(DepartmentRepositoryInterface::class, DepartmentRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(UserPermissionRepositoryInterface::class, UserPermissionRepository::class);
        $this->app->bind(PermissionResourceRepositoryInterface::class, PermissionResourceRepository::class);
        $this->app->bind(PermissionScopeRepositoryInterface::class, PermissionScopeRepository::class);
        $this->app->bind(RolePermissionRepositoryInterface::class, RolePermissionRepository::class);
        $this->app->bind(ShiftRepositoryInterface::class, ShiftRepository::class);
        $this->app->bind(ShiftPatternRepositoryInterface::class, ShiftPatternRepository::class);
        $this->app->bind(ShiftColorRepositoryInterface::class, ShiftColorRepository::class);
        $this->app->bind(RequestRepositoryInterface::class, RequestRepository::class);
        $this->app->bind(TimeRecordCorrectionRepositoryInterface::class, TimeRecordCorrectionRepository::class);
        $this->app->bind(TimeRecordCorrectionRequestRepositoryInterface::class, TimeRecordCorrectionRequestRepository::class);
        $this->app->bind(TimeRecordRepositoryInterface::class, TimeRecordRepository::class);
        $this->app->bind(LaborAlertRepositoryInterface::class, LaborAlertRepository::class);
        $this->app->bind(CompanyLaborAlertSettingRepositoryInterface::class, CompanyLaborAlertSettingRepository::class);
        $this->app->bind(RegistrationTokenRepositoryInterface::class, RegistrationTokenRepository::class);
        $this->app->bind(ApplicationTypeRepositoryInterface::class, ApplicationTypeRepository::class);
        $this->app->bind(CompanyShiftRoundingSettingRepositoryInterface::class, CompanyShiftRoundingSettingRepository::class);
        $this->app->bind(CorrectionRequestDetailRepositoryInterface::class, CorrectionRequestDetailRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}

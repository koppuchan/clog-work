<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * スーパー管理画面のユーザー一覧
 */
class UserController extends Controller
{
    public function __construct(
        private readonly SuperAdminService $superAdminService
    ) {}

    /**
     * 全事業所を横断したユーザー一覧を表示
     */
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Users', [
            'users' => $this->superAdminService->getUserSummaries(),
        ]);
    }
}

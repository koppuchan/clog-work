<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportUserCsvRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\CompanyService;
use App\Services\DepartmentService;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\ShiftPatternService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 管理者向けユーザー管理コントローラー
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly RoleService $roleService,
        private readonly ShiftPatternService $shiftPatternService,
        private readonly CompanyService $companyService,
        private readonly DepartmentService $departmentService,
        private readonly PermissionService $permissionService
    ) {}

    /**
     * 管理者権限を確認
     */
    private function authorizeAdmin(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'この操作は管理者のみ実行できます。');
        }
    }

    /**
     * ユーザー一覧を表示
     */
    public function index(): Response
    {
        // 認証ユーザーの会社IDを取得
        $authUser = auth()->user();
        $companyId = $authUser->company_id;
        $authUserId = $authUser->id;

        // 同じ会社に所属しているユーザーのみを取得（部署もeager load）
        $users = $this->userService->findByCompanyId($companyId);
        $users->load('departments');

        // 会社内の管理者数を取得（唯一の管理者判定用）
        $adminCount = $users->filter(fn ($user) => $user->isAdmin())->count();

        // ユーザーデータをロールと共に取得
        $usersWithRoles = $users->map(function ($user) use ($adminCount, $authUserId) {
            $primaryDepartment = $user->departments->where('pivot.is_primary', true)->first();
            $isAdmin = $user->isAdmin();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'name_kana' => $user->name_kana,
                'employee_code' => $user->employee_code,
                'email' => $user->email,
                'department' => $primaryDepartment?->name ?? '',
                'is_retired' => $user->is_retired ?? false,
                'retirement_date' => $user->retirement_date,
                'roles' => $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]),
                // ロールIDから権限を判定（簡易版）
                'role' => $isAdmin ? 'admin' : ($user->isManager() ? 'manager' : 'employee'),
                // 唯一の管理者かどうか
                'isLastAdmin' => $isAdmin && $adminCount <= 1,
                // オーナー（新規登録で作成された最初のユーザー）かどうか
                'isOwner' => $user->isOwner(),
                // ログイン中のユーザー本人かどうか
                'isSelf' => $user->id === $authUserId,
            ];
        });

        $isAdmin = auth()->user()->isAdmin();

        // CSV説明用に部署一覧を取得
        $departments = $this->departmentService->findByCompanyId($companyId)->map(fn ($dept) => [
            'id' => $dept->id,
            'name' => $dept->name,
        ]);

        return Inertia::render('Admin/Users', [
            'users' => $usersWithRoles,
            'departments' => $departments,
            'canManageUsers' => $isAdmin,
            'canResetPassword' => $isAdmin,
        ]);
    }

    /**
     * ユーザー新規作成フォームを表示
     */
    public function create(): Response
    {
        $this->authorizeAdmin();
        $companyId = auth()->user()->company_id;
        $roles = $this->roleService->getAll()->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
        ]);
        $departments = $this->departmentService->findByCompanyId($companyId)->map(fn ($dept) => [
            'id' => $dept->id,
            'name' => $dept->name,
        ]);
        $shiftPatterns = $this->shiftPatternService->getShiftPatternsByCompanyId($companyId);
        $companyRegularHolidays = $this->companyService->getRegularHolidays($companyId);
        $company = $this->companyService->findById($companyId);

        // 権限マスターデータを取得
        $permissionResources = $this->permissionService->getAllResources();
        $permissionScopes = $this->permissionService->getAllScopes();

        return Inertia::render('Admin/UserNew', [
            'roles' => $roles,
            'departments' => $departments,
            'shiftPatterns' => $shiftPatterns,
            'companyRegularHolidays' => $companyRegularHolidays,
            'defaultShiftPatternId' => $company->default_shift_pattern_id,
            'permissionResources' => $permissionResources,
            'permissionScopes' => $permissionScopes,
        ]);
    }

    /**
     * ユーザー作成確認画面を表示
     */
    public function confirm(StoreUserRequest $request): Response
    {
        $this->authorizeAdmin();
        $companyId = auth()->user()->company_id;
        $data = $request->validated();

        // 初期パスワードを生成
        if (empty($data['password'])) {
            $initialPassword = $this->userService->generateInitialPassword();
            $data['password'] = $initialPassword;
            $data['initial_password_display'] = $initialPassword;
        }

        // permissions配列を個別フィールドに戻す（フロントエンド用）
        if (! empty($data['permissions'])) {
            $scopeIdMap = [
                1 => 'self',
                2 => 'department',
                3 => 'company',
            ];

            foreach ($data['permissions'] as $permission) {
                $resourceId = $permission['resource_id'];
                $scopeId = $permission['scope_id'];
                $scopeCode = $scopeIdMap[$scopeId] ?? 'self';

                match ($resourceId) {
                    1 => $data['shift_view_permission'] = $scopeCode,
                    2 => $data['attendance_view_permission'] = $scopeCode,
                    3 => $data['approval_permission'] = $scopeCode,
                    4 => $data['shift_edit_permission'] = $scopeCode,
                    default => null,
                };
            }
        }

        // 確認画面に必要なマスターデータを取得
        $roles = $this->roleService->getAll()->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
        ]);
        $departments = $this->departmentService->findByCompanyId($companyId)->map(fn ($dept) => [
            'id' => $dept->id,
            'name' => $dept->name,
        ]);
        $shiftPatterns = $this->shiftPatternService->getShiftPatternsByCompanyId($companyId);
        $companyRegularHolidays = $this->companyService->getRegularHolidays($companyId);

        // 権限マスターデータを取得
        $permissionResources = $this->permissionService->getAllResources();
        $permissionScopes = $this->permissionService->getAllScopes();

        return Inertia::render('Admin/UserNew', [
            'step' => 'confirm',
            'validatedData' => $data,
            'roles' => $roles,
            'departments' => $departments,
            'shiftPatterns' => $shiftPatterns,
            'companyRegularHolidays' => $companyRegularHolidays,
            'permissionResources' => $permissionResources,
            'permissionScopes' => $permissionScopes,
        ]);
    }

    /**
     * ユーザーを作成
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $data = $request->validated();

            // 認証ユーザーと同じ会社に所属させる
            $data['company_id'] = auth()->user()->company_id;

            $this->userService->create($data);

            return redirect()
                ->route('admin.users')
                ->with('success', 'ユーザーを作成しました。');
        } catch (\Exception $e) {

            return redirect()
                ->route('admin.users')
                ->with('error', 'ユーザーの作成に失敗しました: '.$e->getMessage());
        }
    }

    /**
     * ユーザー編集フォームを表示
     */
    public function edit(int $id): Response
    {
        $this->authorizeAdmin();
        try {
            $companyId = auth()->user()->company_id;
            $user = $this->userService->findByIdWithRelations($id);

            // 同じ会社に所属しているかチェック
            if ($user->company_id !== auth()->user()->company_id) {
                abort(403, 'このユーザーを編集する権限がありません。');
            }

            $roles = $this->roleService->getAll()->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ]);
            $departments = $this->departmentService->findByCompanyId($companyId)->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
            ]);
            $shiftPatterns = $this->shiftPatternService->getShiftPatternsByCompanyId($companyId);
            $companyRegularHolidays = $this->companyService->getRegularHolidays($companyId);

            // 権限マスターデータを取得
            $permissionResources = $this->permissionService->getAllResources();
            $permissionScopes = $this->permissionService->getAllScopes();

            // ユーザーのシフトパターンを取得
            $userShiftPatterns = $this->userService->getShiftPatterns($user->id);

            // ユーザーの権限をフロントエンド用の形式で取得
            $userPermissions = $this->userService->getPermissionsForFrontend($user->id);

            // ユーザーデータをフロントエンド用に変換
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'name_kana' => $user->name_kana,
                'employee_code' => $user->employee_code,
                'email' => $user->email,
                'department_id' => $user->primaryDepartment()?->first()?->id,
                'is_stamp_hidden' => $user->is_stamp_hidden,
                'is_shift_hidden' => $user->is_shift_hidden,
                'is_retired' => $user->is_retired,
                'retirement_date' => $user->retirement_date,
                'shift_patterns' => $userShiftPatterns,
                'shift_view_permission' => $userPermissions['shift_view_permission'],
                'attendance_view_permission' => $userPermissions['attendance_view_permission'],
                'approval_permission' => $userPermissions['approval_permission'],
                'shift_edit_permission' => $userPermissions['shift_edit_permission'],
                'roles' => $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->toArray(),
            ];

            // 唯一の管理者かどうかを判定
            $isLastAdmin = $this->userService->isLastAdminInCompany($id, $companyId);

            return Inertia::render('Admin/UserEdit', [
                'id' => $id,
                'user' => $userData,
                'roles' => $roles,
                'departments' => $departments,
                'shiftPatterns' => $shiftPatterns,
                'companyRegularHolidays' => $companyRegularHolidays,
                'permissionResources' => $permissionResources,
                'permissionScopes' => $permissionScopes,
                'isLastAdmin' => $isLastAdmin,
                'isOwner' => $user->isOwner(),
            ]);
        } catch (\Exception $e) {
            return Inertia::render('Admin/UserEdit', [
                'id' => $id,
                'error' => 'ユーザーが見つかりません。',
            ]);
        }
    }

    /**
     * ユーザー編集確認画面を表示
     */
    public function confirmUpdate(UpdateUserRequest $request, int $id): Response
    {
        $this->authorizeAdmin();
        $data = $request->validated();
        $companyId = auth()->user()->company_id;

        // 既存のユーザー情報を取得してマージ
        $user = $this->userService->findByIdWithRelations($id);
        if ($user->company_id !== $companyId) {
            abort(403, 'このユーザーを編集する権限がありません。');
        }

        // permissions配列を個別フィールドに戻す（フロントエンド用）
        if (! empty($data['permissions'])) {
            $scopeIdMap = [
                1 => 'self',
                2 => 'department',
                3 => 'company',
            ];

            foreach ($data['permissions'] as $permission) {
                $resourceId = $permission['resource_id'];
                $scopeId = $permission['scope_id'];
                $scopeCode = $scopeIdMap[$scopeId] ?? 'self';

                match ($resourceId) {
                    1 => $data['shift_view_permission'] = $scopeCode,
                    2 => $data['attendance_view_permission'] = $scopeCode,
                    3 => $data['approval_permission'] = $scopeCode,
                    4 => $data['shift_edit_permission'] = $scopeCode,
                    default => null,
                };
            }
        }

        $roles = $this->roleService->getAll()->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
        ]);
        $departments = $this->departmentService->findByCompanyId($companyId)->map(fn ($dept) => [
            'id' => $dept->id,
            'name' => $dept->name,
        ]);
        $shiftPatterns = $this->shiftPatternService->getShiftPatternsByCompanyId($companyId);
        $companyRegularHolidays = $this->companyService->getRegularHolidays($companyId);

        // 権限マスターデータを取得
        $permissionResources = $this->permissionService->getAllResources();
        $permissionScopes = $this->permissionService->getAllScopes();

        $isLastAdmin = $this->userService->isLastAdminInCompany($id, $companyId);

        return Inertia::render('Admin/UserEdit', [
            'id' => $id,
            'step' => 'confirm',
            'validatedData' => $data,
            'user' => $data,
            'roles' => $roles,
            'departments' => $departments,
            'shiftPatterns' => $shiftPatterns,
            'companyRegularHolidays' => $companyRegularHolidays,
            'permissionResources' => $permissionResources,
            'permissionScopes' => $permissionScopes,
            'isLastAdmin' => $isLastAdmin,
            'isOwner' => $user->isOwner(),
        ]);
    }

    /**
     * ユーザー情報を更新
     */
    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $user = $this->userService->findById($id);

            // 同じ会社に所属しているかチェック
            if ($user->company_id !== auth()->user()->company_id) {
                abort(403, 'このユーザーを更新する権限がありません。');
            }

            $this->userService->update($id, $request->validated());

            return redirect()
                ->route('admin.users')
                ->with('success', 'ユーザー情報を更新しました。');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'ユーザー情報の更新に失敗しました: '.$e->getMessage());
        }
    }

    /**
     * ユーザーを削除
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $user = $this->userService->findById($id);

            // 同じ会社に所属しているかチェック
            if ($user->company_id !== auth()->user()->company_id) {
                abort(403, 'このユーザーを削除する権限がありません。');
            }

            // 自分自身は削除不可
            if ($user->id === auth()->user()->id) {
                return redirect()
                    ->route('admin.users')
                    ->with('error', '自分自身を削除することはできません。');
            }

            $this->userService->delete($id);

            return redirect()
                ->route('admin.users')
                ->with('success', 'ユーザーを削除しました。');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.users')
                ->with('error', 'ユーザーの削除に失敗しました: '.$e->getMessage());
        }
    }

    /**
     * CSVテンプレートをダウンロード
     */
    public function downloadCsvTemplate(): StreamedResponse
    {
        $companyId = auth()->user()->company_id;
        $departments = $this->departmentService->findByCompanyId($companyId)->map(fn ($dept) => [
            'id' => $dept->id,
            'name' => $dept->name,
        ])->toArray();

        $csv = $this->userService->generateCsvTemplate($departments);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'user_import_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * CSVインポートページを表示
     */
    public function showCsvImport(): Response
    {
        $this->authorizeAdmin();

        $companyId = auth()->user()->company_id;
        $departments = $this->departmentService->findByCompanyId($companyId)
            ->map(fn ($dept) => ['id' => $dept->id, 'name' => $dept->name])
            ->values()
            ->toArray();

        return Inertia::render('Admin/UserCsvImport', [
            'departments' => $departments,
            'importSuccess' => session('import_success'),
            'importErrors' => session('import_errors', []),
            'importAutoAssigned' => session('import_auto_assigned', []),
        ]);
    }

    /**
     * CSVからユーザーを一括インポート
     *
     * インポート結果は常にCSVインポート画面に表示する：
     * - 全件成功: session('import_success')
     * - 一部成功/一部失敗: session('import_success') + session('import_errors')
     * - 全件失敗: session('import_errors')
     *
     * 個人コード空欄で自動採番されたユーザーは session('import_auto_assigned') で別途通知する。
     */
    public function importCsv(ImportUserCsvRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $companyId = auth()->user()->company_id;
            $file = $request->file('csv_file');

            $result = $this->userService->importFromCsv($file, $companyId);

            $errorMessages = collect($result['errors'])
                ->map(fn ($e) => '行'.$e['row'].': '.$e['message'])
                ->values()
                ->all();

            $autoAssigned = $result['auto_assigned'] ?? [];

            // 全件成功
            if ($result['success'] > 0 && $result['failed'] === 0) {
                return redirect()
                    ->back()
                    ->with('import_success', "{$result['success']}件のユーザーをインポートしました。")
                    ->with('import_auto_assigned', $autoAssigned);
            }

            // 一部成功／一部失敗
            if ($result['success'] > 0 && $result['failed'] > 0) {
                return redirect()
                    ->back()
                    ->with('import_success', "{$result['success']}件成功、{$result['failed']}件失敗しました。")
                    ->with('import_errors', $errorMessages)
                    ->with('import_auto_assigned', $autoAssigned);
            }

            // 全件失敗
            if ($result['failed'] > 0) {
                return redirect()
                    ->back()
                    ->with('import_errors', $errorMessages);
            }

            return redirect()
                ->back()
                ->withErrors(['csv_file' => 'インポートするデータがありません。']);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['csv_file' => 'CSVインポートに失敗しました: '.$e->getMessage()]);
        }
    }

    /**
     * ユーザーのパスワードを初期化
     *
     * 管理者のみが実行可能。ランダムなパスワードを生成し、
     * 次回ログイン時にパスワード変更を強制する。
     */
    public function resetPassword(int $id): RedirectResponse
    {
        try {
            // ログインユーザーが管理者かチェック
            $authUser = auth()->user();
            if (! $authUser->isAdmin()) {
                abort(403, 'パスワード初期化は管理者のみ実行できます。');
            }

            $user = $this->userService->findById($id);

            // 同じ会社に所属しているかチェック
            if ($user->company_id !== $authUser->company_id) {
                abort(403, 'このユーザーのパスワードを初期化する権限がありません。');
            }

            $result = $this->userService->resetPassword($id);

            if ($result['email_sent']) {
                // メール送信した場合
                return redirect()
                    ->route('admin.users')
                    ->with('success', "パスワードを初期化しました。\n新しいパスワードをメールで送信しました。");
            } else {
                // メールアドレスがない場合は画面に表示
                return redirect()
                    ->route('admin.users')
                    ->with('success', "パスワードを初期化しました。\n新しいパスワード: {$result['password']}\n※このパスワードはこの画面を閉じると再表示できません。");
            }
        } catch (\App\Exceptions\BusinessException $e) {
            return redirect()
                ->route('admin.users')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.users')
                ->with('error', 'パスワードの初期化に失敗しました: '.$e->getMessage());
        }
    }
}

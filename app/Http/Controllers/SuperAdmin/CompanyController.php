<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreCompanyRequest;
use App\Services\SuperAdminService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * スーパー管理画面の事業所管理
 */
class CompanyController extends Controller
{
    public function __construct(
        private readonly SuperAdminService $superAdminService
    ) {}

    /**
     * 事業所一覧を表示
     */
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Companies', [
            'companies' => $this->superAdminService->getCompanySummaries(),
        ]);
    }

    /**
     * 事業所の新規作成フォームを表示
     */
    public function create(): Response
    {
        return Inertia::render('SuperAdmin/NewCompany');
    }

    /**
     * 事業所と、その管理者を作成
     */
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->superAdminService->createCompanyWithOwner(
            $validated['name'],
            $validated['owner_name'],
            $validated['owner_email'],
            $validated['owner_password'],
        );

        return redirect()
            ->route('super-admin.companies')
            ->with('success', sprintf(
                '事業所「%s」を作成しました。会社コード: %s / 管理者の個人コード: %s / 打刻URL: %s',
                $result['company']->name,
                $result['company']->company_code,
                $result['owner']->employee_code,
                rtrim((string) config('attendance.public_stamp_base_url'), '/')
                    .'/stamp/'.$result['company']->uuid,
            ));
    }

    /**
     * 事業所を削除
     *
     * 関連データはカスケードで削除され、その事業所にしか所属していない
     * ユーザーもあわせて削除される（メールアドレスの再利用を可能にするため）。
     */
    public function destroy(int $company): RedirectResponse
    {
        try {
            $result = $this->superAdminService->deleteCompany($company);
        } catch (BusinessException $e) {
            return redirect()
                ->route('super-admin.companies')
                ->with('error', $e->getMessage());
        }

        $message = sprintf(
            '事業所「%s」を削除しました。スタッフ %d 名を削除し、メールアドレスが再利用可能になりました。',
            $result['company_name'],
            $result['deleted_user_count'],
        );

        return redirect()
            ->route('super-admin.companies')
            ->with('success', $message);
    }
}

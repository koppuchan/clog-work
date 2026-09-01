<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\HasLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * スーパー管理画面（SaaS運営者向け）のサービス
 *
 * 全事業所を横断した参照と、事業所の作成を扱う。
 */
class SuperAdminService
{
    use HasLogService;

    private const ADMIN_ROLE_ID = 1;

    private const CODE_DIGITS = 6;

    private const INITIAL_CODE = '000001';

    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * 全事業所の概要を取得
     *
     * @return array<int, array{id: int, company_code: string, name: string, user_count: int, owner_name: string|null, owner_email: string|null, created_at: string|null}>
     */
    public function getCompanySummaries(): array
    {
        return $this->companyRepository->getAll()
            ->map(function (Company $company): array {
                $owner = $company->users()->where('is_owner', true)->first();

                return [
                    'id' => $company->id,
                    'company_code' => $company->company_code,
                    'name' => $company->name,
                    'user_count' => $company->users()->count(),
                    'owner_name' => $owner?->name,
                    'owner_email' => $owner?->email,
                    'created_at' => $company->created_at?->format('Y-m-d'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 全事業所を横断したユーザー一覧を取得
     *
     * @return array<int, array{id: int, name: string, employee_code: string|null, email: string|null, company_name: string|null, company_code: string|null, is_owner: bool, is_retired: bool}>
     */
    public function getUserSummaries(): array
    {
        return User::query()
            ->with('companies')
            ->orderBy('name')
            ->get()
            ->map(function (User $user): array {
                $company = $user->companies->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_code' => $user->employee_code,
                    'email' => $user->email,
                    'company_name' => $company?->name,
                    'company_code' => $company?->company_code,
                    'is_owner' => (bool) $user->is_owner,
                    'is_retired' => (bool) $user->is_retired,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * ダッシュボード用の集計値を取得
     *
     * @return array{company_count: int, user_count: int, retired_user_count: int}
     */
    public function getStatistics(): array
    {
        return [
            'company_count' => Company::query()->count(),
            'user_count' => User::query()->where('is_retired', false)->count(),
            'retired_user_count' => User::query()->where('is_retired', true)->count(),
        ];
    }

    /**
     * 事業所と、その管理者を作成
     *
     * 管理者の個人コードは、事業所ごとにコード体系が不揃いにならないよう
     * 全事業所共通の固定値を割り当てる（新規登録フローと同じ扱い）。
     *
     * @return array{company: Company, owner: User}
     */
    public function createCompanyWithOwner(
        string $companyName,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
    ): array {
        return DB::transaction(function () use ($companyName, $ownerName, $ownerEmail, $ownerPassword): array {
            $company = $this->companyRepository->create([
                'name' => $companyName,
                'company_code' => $this->generateCompanyCode(),
                'is_closed_on_holidays' => true,
            ]);

            $owner = $this->userRepository->create([
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => Hash::make($ownerPassword),
                'employee_code' => $this->ownerEmployeeCode(),
                'must_change_password' => true,
                'is_owner' => true,
            ]);

            $this->userRepository->attachCompany($owner->id, $company->id, isPrimary: true);
            $this->userRepository->attachRole($owner->id, self::ADMIN_ROLE_ID);
            $this->userRepository->update($owner->id, ['email_verified_at' => CarbonImmutable::now()]);

            $this->logInfo('Company created from super admin', [
                'company_id' => $company->id,
                'owner_id' => $owner->id,
            ]);

            return ['company' => $company, 'owner' => $owner->fresh()];
        }, 3);
    }

    /**
     * ユニークな会社コードを生成
     */
    private function generateCompanyCode(): string
    {
        $maxCode = $this->companyRepository->getMaxCompanyCode();

        if ($maxCode === null) {
            return self::INITIAL_CODE;
        }

        $nextNumber = (int) $maxCode + 1;

        if ($nextNumber > 999999) {
            throw new \RuntimeException('利用可能な会社コードがありません。');
        }

        return str_pad((string) $nextNumber, self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * 事業所作成時に管理者へ割り当てる個人コードを返す
     */
    private function ownerEmployeeCode(): string
    {
        $code = (string) config('attendance.owner_employee_code', '009999');

        return str_pad($code, self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }
}

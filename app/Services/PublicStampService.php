<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * 公開打刻サービス
 *
 * ログイン不要の打刻ページで使用するビジネスロジック
 */
class PublicStampService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly StampService $stampService
    ) {}

    /**
     * UUIDから会社を取得
     *
     * @param  string  $uuid  会社UUID
     */
    public function findCompanyByUuid(string $uuid): ?Company
    {
        return $this->companyRepository->findByUuid($uuid);
    }

    /**
     * 会社の全ユーザー（退職者以外）を取得
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, array{id: int, name: string, employee_code: string|null}>
     */
    public function getActiveUsersByCompanyId(int $companyId): Collection
    {
        return $this->userRepository->findByCompanyId($companyId)
            ->filter(fn (User $user) => ! $user->is_retired)
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $user->employee_code,
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * FeliCa の IDm から、指定した会社に所属するユーザーを取得
     *
     * IDm は全社で一意（uk_users_felica_idm）だが、打刻端末は会社ごとに
     * 設置されるため、会社への所属も条件に含めて取得する。
     *
     * @param  string  $idm  FeliCa IDm（16進数16桁）
     * @param  int  $companyId  会社ID
     */
    public function findUserByFelicaIdm(string $idm, int $companyId): ?User
    {
        return $this->userRepository->findByFelicaIdm($idm, $companyId);
    }

    /**
     * ユーザーが指定した会社に所属しているか確認
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $companyId  会社ID
     */
    public function isUserInCompany(int $userId, int $companyId): bool
    {
        $user = $this->userRepository->findById($userId);

        if (! $user) {
            return false;
        }

        return $user->companies()->where('company_id', $companyId)->exists();
    }

    /**
     * ユーザーが退職済みかどうか確認
     *
     * @param  int  $userId  ユーザーID
     */
    public function isUserRetired(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);

        if (! $user) {
            return true;
        }

        return (bool) $user->is_retired;
    }

    /**
     * ユーザーのパスワードを検証
     *
     * 打刻専用パスワードが設定されている場合はそちらで認証。
     * 未設定の場合はログインパスワードで認証（フォールバック）。
     *
     * @param  int  $userId  ユーザーID
     * @param  string  $password  入力パスワード
     */
    public function verifyPassword(int $userId, string $password): bool
    {
        $user = $this->userRepository->findById($userId);

        if (! $user) {
            return false;
        }

        // 打刻専用パスワードが設定されている場合はそちらで認証
        if ($user->stamp_password) {
            return Hash::check($password, $user->stamp_password);
        }

        // 未設定の場合はログインパスワードでフォールバック
        return Hash::check($password, $user->password);
    }

    /**
     * ユーザーの現在の勤務状態を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @return array{isWorking: bool, isOnBreak: bool, clockInTime: string|null, breakCount: int}
     */
    public function getCurrentStatus(int $companyId, int $userId): array
    {
        return $this->stampService->getCurrentStatus($companyId, $userId);
    }

    /**
     * 本日の打刻履歴を取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @return Collection<int, array{id: int, type: string, typeLabel: string, time: string, source: string}>
     */
    public function getTodayRecords(int $companyId, int $userId): Collection
    {
        return $this->stampService->getTodayRecords($companyId, $userId);
    }

    /**
     * 出勤打刻
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     *
     * @throws \App\Exceptions\BusinessException
     */
    public function clockIn(int $companyId, int $userId): \App\Models\TimeRecord
    {
        return $this->stampService->clockIn($companyId, $userId);
    }

    /**
     * 退勤打刻
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     *
     * @throws \App\Exceptions\BusinessException
     */
    public function clockOut(int $companyId, int $userId): \App\Models\TimeRecord
    {
        return $this->stampService->clockOut($companyId, $userId);
    }

    /**
     * 休憩開始打刻
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     *
     * @throws \App\Exceptions\BusinessException
     */
    public function breakStart(int $companyId, int $userId): \App\Models\TimeRecord
    {
        return $this->stampService->breakStart($companyId, $userId);
    }

    /**
     * 休憩終了打刻
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     *
     * @throws \App\Exceptions\BusinessException
     */
    public function breakEnd(int $companyId, int $userId): \App\Models\TimeRecord
    {
        return $this->stampService->breakEnd($companyId, $userId);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\FelicaStampAttemptRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * 公開打刻サービス
 *
 * ログイン不要の打刻ページで使用するビジネスロジック
 */
class PublicStampService
{
    /**
     * リクエスト内で取得済みのユーザー
     *
     * 打刻処理は所属確認・退職確認・パスワード照合で同じユーザーを引くため、
     * 同一リクエスト内では取得結果を使い回す。
     *
     * @var array<int, User|null>
     */
    private array $userCache = [];

    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly StampService $stampService,
        private readonly FelicaStampAttemptRepositoryInterface $felicaStampAttemptRepository
    ) {}

    /**
     * ユーザーを取得する（同一リクエスト内では再問い合わせしない）
     */
    private function user(int $userId): ?User
    {
        return $this->userCache[$userId] ??= $this->userRepository->findById($userId);
    }

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
        return $this->userRepository->findActiveForStampByCompanyId($companyId)
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $user->employee_code,
            ])
            ->toBase();
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
     * 直前の打刻からの経過が短すぎないか判定する
     *
     * FeliCa打刻は「かざす」操作ひとつで打刻種別が決まるため、続けて2回
     * かざすと出勤の直後に退勤が記録されてしまう。直前の打刻から所定の
     * 秒数が経過するまでは受け付けない。
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @return int|null 待機が必要な場合は残り秒数、不要な場合は null
     */
    public function secondsUntilStampAllowed(int $companyId, int $userId): ?int
    {
        $cooldown = (int) config('attendance.felica_stamp_cooldown_seconds', 10);

        if ($cooldown <= 0) {
            return null;
        }

        $latest = $this->stampService->findLatestRecord($companyId, $userId);

        if (! $latest) {
            return null;
        }

        $elapsed = CarbonImmutable::now()->diffInSeconds($latest->record_time, absolute: true);

        return $elapsed < $cooldown ? (int) ceil($cooldown - $elapsed) : null;
    }

    /**
     * ユーザーが指定した会社に所属しているか確認
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $companyId  会社ID
     */
    public function isUserInCompany(int $userId, int $companyId): bool
    {
        $user = $this->user($userId);

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
        $user = $this->user($userId);

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
        $user = $this->user($userId);

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

    /**
     * FeliCa打刻の試行結果をログに記録する
     *
     * 常駐アプリはサーバーに直接POSTするため、打刻専用画面（ブラウザ）は
     * この結果を知る手段を持たない。ここに記録した内容を
     * getFelicaEventsSince() でポーリングし、トースト表示に使う。
     *
     * @param  int  $companyId  会社ID
     * @param  int|null  $userId  打刻したユーザーID（未登録カードの場合は null）
     * @param  string  $idm  FeliCa IDm（16進数16桁）
     * @param  string  $status  success / cooldown / unregistered / retired / error
     * @param  string  $message  打刻専用画面に表示する見出しメッセージ
     * @param  string|null  $detail  打刻専用画面に表示する補足メッセージ
     * @param  int|null  $timeRecordId  成功時に記録された打刻レコードID
     */
    public function logFelicaAttempt(
        int $companyId,
        ?int $userId,
        string $idm,
        string $status,
        string $message,
        ?string $detail = null,
        ?int $timeRecordId = null
    ): void {
        $this->felicaStampAttemptRepository->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'felica_idm' => $idm,
            'status' => $status,
            'message' => $message,
            'detail' => $detail,
            'time_record_id' => $timeRecordId,
        ]);
    }

    /**
     * 会社の最新の打刻試行ログIDを取得する
     *
     * 打刻専用画面を開いた直後に呼び、以降のポーリングの起点にする。
     * これより過去の試行はトースト表示しない。
     *
     * @param  int  $companyId  会社ID
     */
    public function getLatestFelicaAttemptId(int $companyId): int
    {
        return $this->felicaStampAttemptRepository->getLatestIdByCompanyId($companyId);
    }

    /**
     * 指定したIDより新しいFeliCa打刻試行を取得する（ポーリング用）
     *
     * @param  int  $companyId  会社ID
     * @param  int  $sinceId  この ID より大きい試行のみ取得
     * @return array<int, array{id: int, status: string, message: string, detail: string|null, userName: string|null, time: string, maskedIdm: string}>
     */
    public function getFelicaEventsSince(int $companyId, int $sinceId): array
    {
        return $this->felicaStampAttemptRepository
            ->findRecentByCompanyId($companyId, $sinceId)
            ->map(fn (\App\Models\FelicaStampAttempt $attempt) => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'message' => $attempt->message,
                'detail' => $attempt->detail,
                'userName' => $attempt->user?->name,
                'time' => $attempt->created_at->format('H:i'),
                'maskedIdm' => $attempt->maskedIdm(),
            ])
            ->all();
    }
}

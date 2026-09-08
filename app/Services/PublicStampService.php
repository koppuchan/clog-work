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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 公開打刻サービス
 *
 * ログイン不要の打刻ページで使用するビジネスロジック
 */
class PublicStampService
{
    /**
     * パスワード検証済みトークンの有効期限（秒）
     *
     * パスワード確認（/verify-password）の直後に打刻APIを呼ぶ通常の
     * 操作フローを想定した猶予時間。bcryptのHash::checkは意図的に重い
     * （数百ms）ため、直前に確認済みであれば打刻API側では再検証せず
     * このトークンで済ませ、体感速度を改善する。
     */
    private const VERIFY_TOKEN_TTL_SECONDS = 30;

    /**
     * FeliCa打刻の排他ロックの保持時間（秒）
     *
     * クールダウン秒数と同程度に設定し、ロック解放後に再度競合しても
     * クールダウン判定で弾けるようにしている。
     */
    private const FELICA_STAMP_LOCK_TTL_SECONDS = 10;

    /**
     * FeliCa打刻の排他ロック取得を待つ最大秒数
     */
    private const FELICA_STAMP_LOCK_WAIT_SECONDS = 5;

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
     * 重複打刻防止のトースト表示を、クールダウン中は1回だけに抑える
     *
     * NFCリーダー（特にRC-S300）は1回の物理タップでも card イベントを
     * 数秒間にわたり複数回発火することがある常駐アプリ側の既知の癖で、
     * その重複イベントはサーバーへ複数回POSTされてくる。データとしては
     * withFelicaStampLock()により二重登録されないが、届いたリクエストの
     * 数だけ「重複打刻防止」の試行ログが作られ、打刻専用画面にその数だけ
     * 警告トーストが積み重なって表示されてしまっていた。
     *
     * 同一ユーザーについて、クールダウンが明けるまでの間は最初の1回だけ
     * 試行ログを記録し、以降の重複リクエストは打刻自体は同様に拒否しつつ
     * ログには残さない（画面に警告を増やさない）ようにする。
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $waitSeconds  クールダウンの残り秒数
     * @return bool 今回のログを記録してよい場合はtrue（既に通知済みならfalse）
     */
    public function shouldNotifyCooldown(int $userId, int $waitSeconds): bool
    {
        $key = $this->cooldownNotifiedCacheKey($userId);

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, $waitSeconds);

        return true;
    }

    /**
     * 打刻成功の直後に、クールダウン期間中の重複防止警告を先回りで抑制する
     *
     * NFCリーダーが1回のタップで複数回イベントを発火すると、成功の直後に
     * 同じユーザーのクールダウン拒否リクエストが届く。これは
     * 「もう一度カードをかざした」正規の操作ではなく読み取り機の癖なので、
     * shouldNotifyCooldown()が最初の1回として警告を出してしまう前に、
     * 成功時点で抑制フラグを立てておく。
     *
     * @param  int  $userId  ユーザーID
     */
    public function suppressCooldownNotificationAfterSuccess(int $userId): void
    {
        $cooldown = (int) config('attendance.felica_stamp_cooldown_seconds', 10);

        if ($cooldown <= 0) {
            return;
        }

        Cache::put($this->cooldownNotifiedCacheKey($userId), true, $cooldown);
    }

    private function cooldownNotifiedCacheKey(int $userId): string
    {
        return "felica-cooldown-notified:{$userId}";
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
     * パスワード検証成功直後に、短時間だけ有効な検証済みトークンを発行する
     *
     * 打刻専用画面は「パスワード確認 → 直後に打刻」の2リクエストで完結するが、
     * 両方でbcryptのHash::checkを行うと打刻の体感速度が悪化する。
     * ユーザーIDだけを鍵にせず乱数トークンを介するのは、ユーザーIDが
     * 推測可能な連番のため、それだけでは「直前に別人が検証した」ことを
     * 悪用したなりすまし打刻を防げないため。
     *
     * @param  int  $userId  ユーザーID
     * @return string 打刻APIへ渡す検証済みトークン
     */
    public function issueVerifiedToken(int $userId): string
    {
        $token = Str::random(40);
        Cache::put($this->verifiedTokenCacheKey($token), $userId, self::VERIFY_TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * 検証済みトークンを消費する（1回限り有効）
     *
     * @param  string|null  $token  打刻APIに渡された検証済みトークン
     * @param  int  $userId  打刻対象のユーザーID
     * @return bool トークンが有効（発行直後・対象ユーザー一致）だったか
     */
    public function consumeVerifiedToken(?string $token, int $userId): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $key = $this->verifiedTokenCacheKey($token);
        $cachedUserId = Cache::pull($key);

        return $cachedUserId !== null && (int) $cachedUserId === $userId;
    }

    private function verifiedTokenCacheKey(string $token): string
    {
        return "stamp-verified-token:{$token}";
    }

    /**
     * FeliCa打刻の判定〜記録を、同一ユーザーに対する排他ロックの中で行う
     *
     * カードリーダーの多重起動やドライバの重複イベントにより、同一ユーザーの
     * 打刻リクエストがごく短い間隔で同時に届くことがある。ロックなしでは
     * クールダウン判定（SELECT）から打刻登録（INSERT）までの間に競合し、
     * 本来1回のはずの打刻が2件登録されてしまう。ロックの中で実行することで
     * 後続のリクエストは先行リクエストの完了を待ってから判定するようになり、
     * 通常どおりクールダウンとして弾かれるようになる。
     *
     * @template TReturn
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  callable(): TReturn  $callback  ロック内で実行する処理
     * @return TReturn
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException 規定時間内にロックを取得できなかった場合
     */
    public function withFelicaStampLock(int $companyId, int $userId, callable $callback): mixed
    {
        return Cache::lock(
            $this->felicaStampLockKey($companyId, $userId),
            self::FELICA_STAMP_LOCK_TTL_SECONDS
        )->block(self::FELICA_STAMP_LOCK_WAIT_SECONDS, $callback);
    }

    private function felicaStampLockKey(int $companyId, int $userId): string
    {
        return "felica-stamp-lock:{$companyId}:{$userId}";
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

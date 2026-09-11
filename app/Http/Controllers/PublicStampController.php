<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Services\FelicaCardRegistrationService;
use App\Services\PublicStampService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 公開打刻コントローラー
 *
 * ログイン不要で打刻できる公開ページ用
 */
class PublicStampController extends Controller
{
    public function __construct(
        private readonly PublicStampService $publicStampService,
        private readonly FelicaCardRegistrationService $felicaCardRegistrationService
    ) {}

    /**
     * 公開打刻画面を表示
     *
     * @param  string  $uuid  会社UUID
     */
    public function index(string $uuid): Response
    {
        $company = $this->publicStampService->findCompanyByUuid($uuid);

        if (! $company) {
            abort(404, '会社が見つかりません。');
        }

        $users = $this->publicStampService->getActiveUsersByCompanyId($company->id);

        return Inertia::render('Public/Stamp', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'uuid' => $company->uuid,
            ],
            'users' => $users->toArray(),
        ]);
    }

    /**
     * FeliCa打刻の結果イベントを取得（ポーリング用）
     *
     * 常駐アプリから /felica にPOSTされた打刻結果は、常駐アプリ自身にしか
     * 返せない。打刻専用画面（ブラウザ）はこのエンドポイントを数秒おきに
     * ポーリングし、新しい試行があればトースト表示する。
     */
    public function felicaEvents(Request $request, string $uuid): JsonResponse
    {
        $company = $this->publicStampService->findCompanyByUuid($uuid);

        if (! $company) {
            return response()->json(['error' => '会社が見つかりません。'], 404);
        }

        if (! $request->has('since_id')) {
            // since_id 省略時（初回アクセス）はトースト表示せず、
            // 以降のポーリングの起点だけを返す。
            // ここで 0 を起点にしてしまうと、画面を開く前からあった
            // 過去の試行まで新着として表示されてしまうため区別している。
            return response()->json([
                'events' => [],
                'lastId' => $this->publicStampService->getLatestFelicaAttemptId($company->id),
            ]);
        }

        $sinceId = (int) $request->query('since_id', 0);
        $events = $this->publicStampService->getFelicaEventsSince($company->id, $sinceId);
        $lastId = $events === [] ? $sinceId : (int) end($events)['id'];

        return response()->json([
            'events' => $events,
            'lastId' => $lastId,
        ]);
    }

    /**
     * ユーザーの現在の勤務状態を取得
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        $company = $this->publicStampService->findCompanyByUuid($uuid);

        if (! $company) {
            return response()->json(['error' => '会社が見つかりません。'], 404);
        }

        $userId = (int) $request->query('user_id');
        if (! $userId) {
            return response()->json(['error' => 'ユーザーIDが必要です。'], 400);
        }

        if (! $this->publicStampService->isUserInCompany($userId, $company->id)) {
            return response()->json(['error' => 'ユーザーが見つかりません。'], 404);
        }

        return response()->json([
            'currentStatus' => $this->publicStampService->getCurrentStatus($company->id, $userId),
            'todayRecords' => $this->publicStampService->getTodayRecords($company->id, $userId),
        ]);
    }

    /**
     * パスワード検証
     */
    public function verifyPassword(Request $request, string $uuid): JsonResponse
    {
        $company = $this->publicStampService->findCompanyByUuid($uuid);

        if (! $company) {
            return response()->json(['success' => false, 'message' => '会社が見つかりません。'], 404);
        }

        $userId = (int) $request->input('user_id');
        $password = (string) $request->input('password');

        if (! $userId || ! $password) {
            return response()->json(['success' => false, 'message' => 'ユーザーIDとパスワードは必須です。'], 400);
        }

        if (! $this->publicStampService->isUserInCompany($userId, $company->id)) {
            return response()->json(['success' => false, 'message' => 'パスワードが正しくありません。'], 401);
        }

        // レート制限（IPベース、5回/分）
        $throttleKey = Str::transliterate('stamp-verify|'.$userId.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'message' => "試行回数が上限に達しました。{$seconds}秒後に再試行してください。",
            ], 429);
        }

        if (! $this->publicStampService->verifyPassword($userId, $password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json(['success' => false, 'message' => 'パスワードが正しくありません。'], 401);
        }

        RateLimiter::clear($throttleKey);

        return response()->json([
            'success' => true,
            'verifyToken' => $this->publicStampService->issueVerifiedToken($userId),
            'currentStatus' => $this->publicStampService->getCurrentStatus($company->id, $userId),
            'todayRecords' => $this->publicStampService->getTodayRecords($company->id, $userId),
        ]);
    }

    /**
     * 出勤打刻を行う
     */
    public function clockIn(Request $request, string $uuid): JsonResponse
    {
        return $this->performStamp($request, $uuid, 'clockIn');
    }

    /**
     * 退勤打刻を行う
     */
    public function clockOut(Request $request, string $uuid): JsonResponse
    {
        return $this->performStamp($request, $uuid, 'clockOut');
    }

    /**
     * 休憩開始打刻を行う
     */
    public function breakStart(Request $request, string $uuid): JsonResponse
    {
        return $this->performStamp($request, $uuid, 'breakStart');
    }

    /**
     * 休憩終了打刻を行う
     */
    public function breakEnd(Request $request, string $uuid): JsonResponse
    {
        return $this->performStamp($request, $uuid, 'breakEnd');
    }

    /**
     * 打刻専用画面の休憩開始モードのON/OFFをサーバー側にも反映する
     *
     * ブラウザの「休憩開始」トグルはこれまでブラウザ内の状態のみで、
     * FeliCa常駐アプリのタップには一切影響しなかった。ここで会社単位の
     * フラグとして保存し、felica()での打刻種別判定にも使う。
     */
    public function setFelicaBreakMode(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'armed' => ['required', 'boolean'],
        ]);

        $company = $this->publicStampService->findCompanyByUuid($uuid);

        if (! $company) {
            return response()->json(['success' => false, 'message' => '会社が見つかりません。'], 404);
        }

        $this->publicStampService->setFelicaBreakMode($company->id, $validated['armed']);

        return response()->json(['success' => true]);
    }

    /**
     * FeliCaカードによる打刻を行う
     *
     * 常駐アプリ（FeliCa打刻）から呼ばれる。カードをかざすだけの操作のため
     * パスワード認証は行わず、IDm とカードリーダー設置会社の組み合わせで
     * ユーザーを特定する。
     *
     * リクエスト:
     *   { "idm": "0123456789abcdef", "intent": "break-start" }
     *   intent は常駐アプリ自身のショートカット（B/Esc）で休憩開始モードに
     *   した場合のみ付与され、通常は省略される。打刻専用画面のトグルから
     *   有効化された場合はintentが付かないため、setFelicaBreakMode()で
     *   立てたサーバー側フラグ（consumeFelicaBreakMode()）でも判定する。
     *
     * 打刻種別は現在の勤務状態から決定する。
     *   休憩中           → 休憩終了
     *   勤務中 + break-start → 休憩開始
     *   勤務中           → 退勤
     *   未出勤           → 出勤
     */
    public function felica(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'idm' => ['required', 'string', 'regex:/^[0-9a-fA-F]{16}$/'],
            'intent' => ['nullable', 'string', 'in:break-start'],
        ]);

        $company = $this->publicStampService->findCompanyByUuid($uuid);

        if (! $company) {
            return response()->json(['success' => false, 'message' => '会社が見つかりません。'], 404);
        }

        $idm = strtolower($validated['idm']);
        $user = $this->publicStampService->findUserByFelicaIdm($idm, $company->id);

        if (! $user) {
            // IDmはカードに印字されていないため、登録画面から選べるよう覚えておく
            $this->felicaCardRegistrationService->remember($company->id, $idm);

            // 管理者がスタッフ編集画面でカード登録を待ち受けている間は、
            // 意図的に未登録カードをかざしている最中なので警告を出さない
            if (! $this->felicaCardRegistrationService->isRegistrationModeArmed($company->id)) {
                $this->publicStampService->logFelicaAttempt(
                    $company->id,
                    null,
                    $idm,
                    'unregistered',
                    '登録されていないカードです。管理者にカードの登録を依頼してください。'
                );
            }

            return response()->json([
                'success' => false,
                'message' => '登録されていないカードです。管理者にカードの登録を依頼してください。',
            ], 404);
        }

        if ($this->publicStampService->isUserRetired($user->id)) {
            $this->publicStampService->logFelicaAttempt(
                $company->id,
                $user->id,
                $idm,
                'retired',
                '退職済みのユーザーです。'
            );

            return response()->json(['success' => false, 'message' => '退職済みのユーザーです。'], 400);
        }

        // カードリーダーの多重起動やドライバの重複イベントで、同一ユーザーの
        // 打刻リクエストがほぼ同時に届くことがある。ロックなしではクールダウン
        // 判定(SELECT)から打刻登録(INSERT)までの間に競合し、本来1回のはずの
        // 打刻が2件登録されてしまうため、ユーザー単位の排他ロックの中で行う。
        try {
            return $this->publicStampService->withFelicaStampLock(
                $company->id,
                $user->id,
                function () use ($company, $user, $idm, $validated): JsonResponse {
                    // 続けてかざした場合に、出勤の直後へ退勤が記録されるのを防ぐ
                    $wait = $this->publicStampService->secondsUntilStampAllowed($company->id, $user->id);

                    if ($wait !== null) {
                        // クールダウン中の重複リクエストは、最初の1回だけ警告を記録する。
                        // 毎回記録すると、読み取り機が同一タップで複数回発火した分だけ
                        // 警告トーストが積み重なって表示されてしまう。
                        if ($this->publicStampService->shouldNotifyCooldown($user->id, $wait)) {
                            $this->publicStampService->logFelicaAttempt(
                                $company->id,
                                $user->id,
                                $idm,
                                'cooldown',
                                '重複打刻防止のため受け付けませんでした',
                                sprintf('%d秒後にもう一度カードをかざしてください', $wait)
                            );
                        }

                        return response()->json([
                            'success' => false,
                            'message' => sprintf('打刻を受け付けました。あと %d 秒お待ちください。', $wait),
                            'user' => ['id' => $user->id, 'name' => $user->name],
                        ], 429);
                    }

                    $status = $this->publicStampService->getCurrentStatus($company->id, $user->id);

                    // 休憩中は常に休憩終了として扱う（intentに関わらず）
                    if ($status['isOnBreak']) {
                        $method = 'breakEnd';
                    } elseif (($validated['intent'] ?? null) === 'break-start'
                        || $this->publicStampService->isFelicaBreakModeArmed($company->id)) {
                        // 休憩開始はintent（常駐アプリ自身のショートカット）または、
                        // 打刻専用画面のトグルから有効化されたサーバー側フラグの
                        // どちらでも成立する。フラグは有効時間内なら消費せず、
                        // 複数の従業員を続けてかざしても全員が休憩開始として扱われる
                        // （常駐アプリ自身の休憩開始モードと同じ挙動）。タップは常に
                        // breakStart()に委ね、出勤していない場合はそちらのビジネス
                        // ルールでエラーにする。ここでisWorkingがfalseだからと出勤
                        // 打刻にフォールバックすると、休憩開始モードを選んだのに
                        // 何も知らせずに出勤が記録されてしまい、「休憩の打刻が
                        // 入らない」という混乱の原因になる。
                        $method = 'breakStart';
                    } elseif ($status['isWorking']) {
                        $method = 'clockOut';
                    } else {
                        $method = 'clockIn';
                    }

                    try {
                        $record = $this->publicStampService->$method($company->id, $user->id);
                    } catch (BusinessException $e) {
                        // 読み取り機の多重発火で同じエラーが短時間に何度も届いても、
                        // 最初の1回だけ試行ログを記録する（警告トーストの積み重ね防止）
                        if ($this->publicStampService->shouldNotifyError($user->id, $e->getMessage())) {
                            $this->publicStampService->logFelicaAttempt(
                                $company->id,
                                $user->id,
                                $idm,
                                'error',
                                $e->getMessage()
                            );
                        }

                        return response()->json([
                            'success' => false,
                            'message' => $e->getMessage(),
                        ], 422);
                    }

                    $this->publicStampService->logFelicaAttempt(
                        $company->id,
                        $user->id,
                        $idm,
                        'success',
                        $record->record_type->stampedMessage(),
                        null,
                        $record->id
                    );

                    // 読み取り機の多重発火で直後にクールダウン拒否のリクエストが
                    // 届いても、成功直後は重複防止の警告を出さないようにする
                    $this->publicStampService->suppressCooldownNotificationAfterSuccess($user->id);

                    return response()->json([
                        'success' => true,
                        'message' => $record->record_type->stampedMessage(),
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'employee_code' => $user->employee_code,
                        ],
                        'record' => [
                            'id' => $record->id,
                            'type' => $record->record_type->name,
                            'typeLabel' => $record->record_type->label(),
                            'time' => $record->record_time->format('H:i'),
                        ],
                        'currentStatus' => $this->publicStampService->getCurrentStatus($company->id, $user->id),
                    ]);
                }
            );
        } catch (LockTimeoutException) {
            // 規定時間内にロックを取得できなかった。処理が詰まっている状況と
            // みなし、重複防止と同様の扱いでいったん受け付けない。
            $this->publicStampService->logFelicaAttempt(
                $company->id,
                $user->id,
                $idm,
                'cooldown',
                '重複打刻防止のため受け付けませんでした',
                'もう一度カードをかざしてください'
            );

            return response()->json([
                'success' => false,
                'message' => '打刻処理が混み合っています。もう一度カードをかざしてください。',
                'user' => ['id' => $user->id, 'name' => $user->name],
            ], 429);
        }
    }

    /**
     * 打刻処理の共通メソッド
     */
    private function performStamp(Request $request, string $uuid, string $method): JsonResponse
    {
        $company = $this->publicStampService->findCompanyByUuid($uuid);

        if (! $company) {
            return response()->json(['success' => false, 'message' => '会社が見つかりません。'], 404);
        }

        $userId = (int) $request->input('user_id');
        $password = (string) $request->input('password');
        $verifyToken = $request->input('verify_token');

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'ユーザーを選択してください。'], 400);
        }

        if (! $this->publicStampService->isUserInCompany($userId, $company->id)) {
            return response()->json(['success' => false, 'message' => 'ユーザーが見つかりません。'], 404);
        }

        // 直前の /verify-password 成功時に発行したトークンが有効なら、
        // bcryptでの再検証を省略する（体感速度対策）。
        // トークンがない・期限切れの場合は通常通りパスワードを検証する。
        if (! $this->publicStampService->consumeVerifiedToken($verifyToken, $userId)) {
            if (! $password) {
                return response()->json(['success' => false, 'message' => 'パスワードが必要です。'], 400);
            }

            if (! $this->publicStampService->verifyPassword($userId, $password)) {
                return response()->json(['success' => false, 'message' => 'パスワードが正しくありません。'], 401);
            }
        }

        if ($this->publicStampService->isUserRetired($userId)) {
            return response()->json(['success' => false, 'message' => '退職済みのユーザーです。'], 400);
        }

        try {
            $record = $this->publicStampService->$method($company->id, $userId);

            return response()->json([
                'success' => true,
                'message' => $record->record_type->stampedMessage(),
                'record' => [
                    'id' => $record->id,
                    'type' => $record->record_type->name,
                    'typeLabel' => $record->record_type->label(),
                    'time' => $record->record_time->format('H:i'),
                ],
                'currentStatus' => $this->publicStampService->getCurrentStatus($company->id, $userId),
            ]);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

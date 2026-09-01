<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Services\PublicStampService;
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
        private readonly PublicStampService $publicStampService
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
            'currentStatus' => $this->publicStampService->getCurrentStatus($company->id, $userId),
            'todayRecords' => $this->publicStampService->getTodayRecords($company->id, $userId),
        ]);
    }

    /**
     * 出勤打刻を行う
     */
    public function clockIn(Request $request, string $uuid): JsonResponse
    {
        return $this->performStamp($request, $uuid, 'clockIn', '出勤を記録しました。');
    }

    /**
     * 退勤打刻を行う
     */
    public function clockOut(Request $request, string $uuid): JsonResponse
    {
        return $this->performStamp($request, $uuid, 'clockOut', '退勤を記録しました。');
    }

    /**
     * 休憩開始打刻を行う
     */
    public function breakStart(Request $request, string $uuid): JsonResponse
    {
        return $this->performStamp($request, $uuid, 'breakStart', '休憩開始を記録しました。');
    }

    /**
     * 休憩終了打刻を行う
     */
    public function breakEnd(Request $request, string $uuid): JsonResponse
    {
        return $this->performStamp($request, $uuid, 'breakEnd', '休憩終了を記録しました。');
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
     *   intent は休憩開始モード時のみ付与され、通常は省略される。
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
            return response()->json([
                'success' => false,
                'message' => '登録されていないカードです。管理者にカードの登録を依頼してください。',
            ], 404);
        }

        if ($this->publicStampService->isUserRetired($user->id)) {
            return response()->json(['success' => false, 'message' => '退職済みのユーザーです。'], 400);
        }

        // 続けてかざした場合に、出勤の直後へ退勤が記録されるのを防ぐ
        $wait = $this->publicStampService->secondsUntilStampAllowed($company->id, $user->id);

        if ($wait !== null) {
            return response()->json([
                'success' => false,
                'message' => sprintf('打刻を受け付けました。あと %d 秒お待ちください。', $wait),
                'user' => ['id' => $user->id, 'name' => $user->name],
            ], 429);
        }

        $status = $this->publicStampService->getCurrentStatus($company->id, $user->id);

        if ($status['isOnBreak']) {
            $method = 'breakEnd';
            $successMessage = '休憩終了を記録しました。';
        } elseif ($status['isWorking']) {
            $isBreakStart = ($validated['intent'] ?? null) === 'break-start';
            $method = $isBreakStart ? 'breakStart' : 'clockOut';
            $successMessage = $isBreakStart ? '休憩開始を記録しました。' : '退勤を記録しました。';
        } else {
            $method = 'clockIn';
            $successMessage = '出勤を記録しました。';
        }

        try {
            $record = $this->publicStampService->$method($company->id, $user->id);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $successMessage,
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

    /**
     * 打刻処理の共通メソッド
     */
    private function performStamp(Request $request, string $uuid, string $method, string $successMessage): JsonResponse
    {
        $company = $this->publicStampService->findCompanyByUuid($uuid);

        if (! $company) {
            return response()->json(['success' => false, 'message' => '会社が見つかりません。'], 404);
        }

        $userId = (int) $request->input('user_id');
        $password = (string) $request->input('password');

        if (! $userId) {
            return response()->json(['success' => false, 'message' => 'ユーザーを選択してください。'], 400);
        }

        if (! $password) {
            return response()->json(['success' => false, 'message' => 'パスワードが必要です。'], 400);
        }

        if (! $this->publicStampService->isUserInCompany($userId, $company->id)) {
            return response()->json(['success' => false, 'message' => 'ユーザーが見つかりません。'], 404);
        }

        if (! $this->publicStampService->verifyPassword($userId, $password)) {
            return response()->json(['success' => false, 'message' => 'パスワードが正しくありません。'], 401);
        }

        if ($this->publicStampService->isUserRetired($userId)) {
            return response()->json(['success' => false, 'message' => '退職済みのユーザーです。'], 400);
        }

        try {
            $record = $this->publicStampService->$method($company->id, $userId);

            return response()->json([
                'success' => true,
                'message' => $successMessage,
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

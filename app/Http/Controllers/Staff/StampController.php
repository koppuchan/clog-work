<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Services\StampService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * スタッフ向け打刻コントローラー
 */
class StampController extends Controller
{
    public function __construct(
        private readonly StampService $stampService
    ) {}

    /**
     * 打刻画面を表示
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $userId = $user->id;

        // 現在の勤務状態を取得
        $currentStatus = $this->stampService->getCurrentStatus($companyId, $userId);

        // 本日の打刻履歴を取得
        $todayRecords = $this->stampService->getTodayRecords($companyId, $userId);

        return Inertia::render('Staff/Stamp', [
            'currentStatus' => $currentStatus,
            'todayRecords' => $todayRecords,
        ]);
    }

    /**
     * 出勤打刻を行う
     */
    public function clockIn(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $record = $this->stampService->clockIn($user->company_id, $user->id);

            return response()->json([
                'success' => true,
                'message' => '出勤を記録しました。',
                'record' => [
                    'id' => $record->id,
                    'type' => $record->record_type->name,
                    'typeLabel' => $record->record_type->label(),
                    'time' => $record->record_time->format('H:i'),
                ],
                'currentStatus' => $this->stampService->getCurrentStatus($user->company_id, $user->id),
            ]);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 退勤打刻を行う
     */
    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $record = $this->stampService->clockOut($user->company_id, $user->id);

            return response()->json([
                'success' => true,
                'message' => '退勤を記録しました。',
                'record' => [
                    'id' => $record->id,
                    'type' => $record->record_type->name,
                    'typeLabel' => $record->record_type->label(),
                    'time' => $record->record_time->format('H:i'),
                ],
                'currentStatus' => $this->stampService->getCurrentStatus($user->company_id, $user->id),
            ]);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 休憩開始打刻を行う
     */
    public function breakStart(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $record = $this->stampService->breakStart($user->company_id, $user->id);

            return response()->json([
                'success' => true,
                'message' => '休憩開始を記録しました。',
                'record' => [
                    'id' => $record->id,
                    'type' => $record->record_type->name,
                    'typeLabel' => $record->record_type->label(),
                    'time' => $record->record_time->format('H:i'),
                ],
                'currentStatus' => $this->stampService->getCurrentStatus($user->company_id, $user->id),
            ]);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 休憩終了打刻を行う
     */
    public function breakEnd(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $record = $this->stampService->breakEnd($user->company_id, $user->id);

            return response()->json([
                'success' => true,
                'message' => '休憩終了を記録しました。',
                'record' => [
                    'id' => $record->id,
                    'type' => $record->record_type->name,
                    'typeLabel' => $record->record_type->label(),
                    'time' => $record->record_time->format('H:i'),
                ],
                'currentStatus' => $this->stampService->getCurrentStatus($user->company_id, $user->id),
            ]);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 現在の勤務状態を取得（API）
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'currentStatus' => $this->stampService->getCurrentStatus($user->company_id, $user->id),
            'todayRecords' => $this->stampService->getTodayRecords($user->company_id, $user->id),
        ]);
    }
}

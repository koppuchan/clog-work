<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LaborAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者向け労務アラートコントローラー
 */
class LaborAlertController extends Controller
{
    public function __construct(
        private readonly LaborAlertService $laborAlertService
    ) {}

    /**
     * 労務アラート一覧を表示
     */
    public function index(Request $request): Response
    {
        $companyId = auth()->user()->company_id;

        // リクエストから年月を取得（デフォルトは当月）
        $now = CarbonImmutable::now();
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        // アラートを取得（DB設定に基づく）
        $alerts = $this->laborAlertService->getAlerts($companyId, $year, $month);

        // サマリーを取得
        $summary = $this->laborAlertService->getAlertSummary($alerts);

        return Inertia::render('Admin/Alerts', [
            'alerts' => $alerts->toArray(),
            'summary' => $summary,
            'selectedYear' => $year,
            'selectedMonth' => $month,
        ]);
    }

    /**
     * アラートを既読にする
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $this->laborAlertService->markAsRead($id);

        return response()->json(['success' => true]);
    }

    /**
     * 全アラートを既読にする
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $companyId = auth()->user()->company_id;
        $this->laborAlertService->markAllAsRead($companyId);

        return response()->json(['success' => true]);
    }
}

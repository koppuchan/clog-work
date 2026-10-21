<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LaborAlertService;
use App\Services\PayrollPeriodService;
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
        private readonly LaborAlertService $laborAlertService,
        private readonly PayrollPeriodService $payrollPeriodService
    ) {}

    /**
     * 労務アラート一覧を表示
     */
    public function index(Request $request): Response
    {
        $companyId = auth()->user()->company_id;

        // リクエストから月度を取得（デフォルトは、今日が属する締め期間の月度）。
        // 締日が15日なら10/16以降は「11月度」（10/16〜11/15）で、進行中の期間の
        // アラートをそのまま見られるようにする（クライアント要望: 最新の月度の
        // リアルタイムのアラートも表示してほしい）。
        $now = CarbonImmutable::now();
        $current = $this->payrollPeriodService->periodMonthContaining($companyId, $now);
        $year = (int) $request->input('year', $current['year']);
        $month = (int) $request->input('month', $current['month']);

        // アラートを取得（DB設定に基づく）
        $alerts = $this->laborAlertService->getAlerts($companyId, $year, $month);

        // サマリーを取得
        $summary = $this->laborAlertService->getAlertSummary($alerts);

        return Inertia::render('Admin/Alerts', [
            'alerts' => $alerts->toArray(),
            'summary' => $summary,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'periodOptions' => $this->payrollPeriodService->recentPeriods($companyId, $now),
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

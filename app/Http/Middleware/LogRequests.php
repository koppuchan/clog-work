<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RouteNameEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * リクエストログミドルウェア
 *
 * 全てのHTTPリクエストを自動的にログに記録します
 * 出力形式: 【開始】シフト一覧取得 / 【終了】シフト一覧取得
 */
class LogRequests
{
    /**
     * リクエストを処理
     *
     * @param  Request  $request  HTTPリクエスト
     * @param  Closure  $next  次のミドルウェア
     */
    public function handle(Request $request, Closure $next): Response
    {
        // リクエストIDを生成
        $requestId = (string) Str::uuid();
        $startTime = microtime(true);

        // ルート名から日本語ラベルを取得
        $routeName = $request->route()?->getName();
        $label = RouteNameEnum::getLabelForRoute($routeName);

        // ログコンテキストを設定（このリクエスト中の全ログに適用される）
        Log::withContext([
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
            'company_id' => $request->user()?->company_id ?? null,
        ]);

        // リクエスト開始ログ
        Log::info("【開始】{$label}", [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $routeName,
        ]);

        // 次のミドルウェアを実行
        $response = $next($request);

        // 実行時間を計算（ミリ秒）
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        // リクエスト完了ログ
        Log::info("【終了】{$label}", [
            'status_code' => $response->getStatusCode(),
            'execution_time_ms' => $executionTime,
        ]);

        // レスポンスヘッダーにリクエストIDを追加（トレーシング用）
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}

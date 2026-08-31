<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ErrorCodeEnum;
use App\Services\LogService;

/**
 * ログサービス連携トレイト
 *
 * サービスやリポジトリでエラーコード付きログ出力を簡潔に行うためのトレイト
 */
trait HasLogService
{
    /**
     * エラーログを出力
     *
     * コンテキストは自動的に呼び出し元のクラス名::メソッド名で設定される
     *
     * @param  ErrorCodeEnum  $errorCode  エラーコード
     * @param  array<string, mixed>  $data  追加データ
     * @param  \Throwable|null  $exception  例外（存在する場合）
     */
    protected function logError(
        ErrorCodeEnum $errorCode,
        array $data = [],
        ?\Throwable $exception = null
    ): void {
        $context = $this->getLogContext();
        app(LogService::class)->error($errorCode, $context, $data, $exception);
    }

    /**
     * 警告ログを出力
     *
     * @param  ErrorCodeEnum  $errorCode  エラーコード
     * @param  array<string, mixed>  $data  追加データ
     */
    protected function logWarning(ErrorCodeEnum $errorCode, array $data = []): void
    {
        $context = $this->getLogContext();
        app(LogService::class)->warning($errorCode, $context, $data);
    }

    /**
     * 情報ログを出力
     *
     * @param  string  $message  メッセージ
     * @param  array<string, mixed>  $data  追加データ
     */
    protected function logInfo(string $message, array $data = []): void
    {
        $context = $this->getLogContext();
        app(LogService::class)->info($context, $message, $data);
    }

    /**
     * ログコンテキストを取得（ClassName::methodName形式）
     */
    private function getLogContext(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[2] ?? $backtrace[1] ?? [];

        $class = isset($caller['class']) ? class_basename($caller['class']) : 'Unknown';
        $method = $caller['function'] ?? 'unknown';

        return "{$class}::{$method}";
    }
}

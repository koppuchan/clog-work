<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCodeEnum;
use Illuminate\Support\Facades\Log;

/**
 * ログ出力サービス
 *
 * エラーコード付きログ出力を一元管理する
 * 出力形式: 【E1001】エラー内容 [Context]
 */
class LogService
{
    /**
     * エラーコード付きエラーログを出力
     *
     * @param  ErrorCodeEnum  $errorCode  エラーコード
     * @param  string  $context  コンテキスト（例: "UserService::create"）
     * @param  array<string, mixed>  $data  追加データ
     * @param  \Throwable|null  $exception  例外（存在する場合）
     */
    public function error(
        ErrorCodeEnum $errorCode,
        string $context,
        array $data = [],
        ?\Throwable $exception = null
    ): void {
        $message = $this->buildMessage($errorCode, $context);

        $logData = [
            'error_code' => $errorCode->code(),
            'category' => $errorCode->category(),
            ...$data,
        ];

        if ($exception !== null) {
            $logData['exception'] = $exception->getMessage();
            $logData['trace'] = $exception->getTraceAsString();
        }

        Log::error($message, $logData);
    }

    /**
     * エラーコード付き警告ログを出力
     *
     * @param  ErrorCodeEnum  $errorCode  エラーコード
     * @param  string  $context  コンテキスト
     * @param  array<string, mixed>  $data  追加データ
     */
    public function warning(
        ErrorCodeEnum $errorCode,
        string $context,
        array $data = []
    ): void {
        $message = $this->buildMessage($errorCode, $context);

        Log::warning($message, [
            'error_code' => $errorCode->code(),
            'category' => $errorCode->category(),
            ...$data,
        ]);
    }

    /**
     * 情報ログを出力（エラーコードなし）
     *
     * @param  string  $context  コンテキスト
     * @param  string  $message  メッセージ
     * @param  array<string, mixed>  $data  追加データ
     */
    public function info(string $context, string $message, array $data = []): void
    {
        Log::info("{$context} - {$message}", $data);
    }

    /**
     * ログメッセージを構築
     */
    private function buildMessage(ErrorCodeEnum $errorCode, string $context): string
    {
        return "{$errorCode->formatted()} [{$context}]";
    }
}

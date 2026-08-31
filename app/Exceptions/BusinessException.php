<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;
use Exception;

/**
 * ビジネスロジック違反の例外
 */
class BusinessException extends Exception
{
    private ?ErrorCodeEnum $errorCode = null;

    /**
     * @param  string|ErrorCodeEnum  $messageOrCode  エラーメッセージまたはエラーコード
     * @param  int  $code  HTTPステータスコード（ErrorCodeEnum使用時は自動設定）
     * @param  \Throwable|null  $previous  前の例外
     */
    public function __construct(
        string|ErrorCodeEnum $messageOrCode = '',
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        if ($messageOrCode instanceof ErrorCodeEnum) {
            $this->errorCode = $messageOrCode;
            $message = $messageOrCode->label();
            $code = $messageOrCode->httpStatus();
        } else {
            $message = $messageOrCode;
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * エラーコードを取得
     */
    public function getErrorCode(): ?ErrorCodeEnum
    {
        return $this->errorCode;
    }

    /**
     * フォーマット済みメッセージを取得
     * エラーコードがある場合: 【E1001】メッセージ
     * エラーコードがない場合: メッセージのみ
     */
    public function getFormattedMessage(): string
    {
        if ($this->errorCode !== null) {
            return $this->errorCode->withMessage($this->getMessage());
        }

        return $this->getMessage();
    }

    /**
     * ユーザーに表示可能なメッセージかどうか
     */
    public function isUserFriendly(): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;
use Exception;

/**
 * 権限がない例外
 */
class UnauthorizedException extends Exception
{
    private ?ErrorCodeEnum $errorCode = null;

    /**
     * @param  string|ErrorCodeEnum  $messageOrCode  エラーメッセージまたはエラーコード
     * @param  int  $code  HTTPステータスコード（ErrorCodeEnum使用時は自動設定）
     * @param  \Throwable|null  $previous  前の例外
     */
    public function __construct(
        string|ErrorCodeEnum $messageOrCode = '権限がありません。',
        int $code = 403,
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
     */
    public function getFormattedMessage(): string
    {
        if ($this->errorCode !== null) {
            return $this->errorCode->withMessage($this->getMessage());
        }

        return $this->getMessage();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCodeEnum;
use App\Traits\HasLogService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * メール送信の成否をログに残す
 *
 * 送信は同期で行われるため、失敗すると例外がそのまま画面まで上がり、
 * 何が起きたのかがログから追えなかった。送信の入口をここに一本化して、
 * 成功・失敗の両方を同じ形で記録する。
 *
 * 使用中のメーラーも一緒に記録する。MAIL_MAILER が log のままだと
 * 送信は成功として扱われるが実際には届かないため、
 * 設定の取り違えをログから気づけるようにする。
 */
class MailDispatcher
{
    use HasLogService;

    /**
     * メールを送信する
     *
     * @param  string  $to  宛先
     * @param  Mailable  $mailable  送信するメール
     * @param  array<string, mixed>  $context  ログに残す追加情報
     * @return bool 送信できたか
     */
    public function send(string $to, Mailable $mailable, array $context = []): bool
    {
        $mailer = (string) config('mail.default');

        $logData = array_merge($context, [
            'to' => $to,
            'mail' => class_basename($mailable),
            'mailer' => $mailer,
        ]);

        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::EXTERNAL_MAIL_FAILED, $logData, $e);

            return false;
        }

        if ($this->isNonDeliveringMailer($mailer)) {
            // 送信自体は成功扱いになるが、相手には届いていない
            $this->logWarning(ErrorCodeEnum::EXTERNAL_MAIL_FAILED, array_merge($logData, [
                'reason' => 'メーラーが log のため実際には送信されていません',
            ]));

            return true;
        }

        $this->logInfo('メールを送信しました', $logData);

        return true;
    }

    /**
     * 実際には配信しないメーラーか
     */
    private function isNonDeliveringMailer(string $mailer): bool
    {
        return in_array($mailer, ['log', 'array', 'null'], true);
    }
}

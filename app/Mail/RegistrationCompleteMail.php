<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 新規会員登録完了メール
 *
 * 自分で登録したユーザー向け。会社コード・個人コードを通知する。
 * 管理者が追加したユーザー向けの WelcomeUserMail とは異なり、
 * パスワードは自分で設定済みのため含まない。
 */
class RegistrationCompleteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $companyCode,
        public string $loginUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【勤怠管理システム】会員登録完了のお知らせ',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-complete',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

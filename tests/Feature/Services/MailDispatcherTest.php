<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\MailDispatcher;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * メール送信の成否がログから追えることの検証。
 *
 * 送信は同期で行われるため、失敗すると例外がそのまま画面まで上がり、
 * 何が起きたのかがログに残らなかった。
 */
class MailDispatcherTest extends TestCase
{
    private MailDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = app(MailDispatcher::class);
    }

    private function mailable(): Mailable
    {
        return new class extends Mailable
        {
            public function build(): self
            {
                return $this->subject('テスト')->html('本文');
            }
        };
    }

    /**
     * @test
     */
    public function 送信できたらtrueを返す(): void
    {
        Mail::fake();

        $this->assertTrue($this->dispatcher->send('staff@example.com', $this->mailable()));
    }

    /**
     * @test
     */
    public function 送信に失敗してもfalseを返し例外は投げない(): void
    {
        // 送信時に例外が起きる状況を作る
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('接続できません'));
        Log::shouldReceive('error')->atLeast()->once();
        Log::shouldReceive('warning', 'info')->zeroOrMoreTimes();

        $this->assertFalse($this->dispatcher->send('staff@example.com', $this->mailable()));
    }

    /**
     * @test
     */
    public function 失敗の理由がログに残る(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('接続できません'));

        $logged = [];
        Log::shouldReceive('error')->andReturnUsing(function (string $message, array $context = []) use (&$logged) {
            $logged[] = $context;
        });
        Log::shouldReceive('warning', 'info')->zeroOrMoreTimes();

        $this->dispatcher->send('staff@example.com', $this->mailable(), ['user_id' => 42]);

        $this->assertNotEmpty($logged);

        $flattened = json_encode($logged, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('staff@example.com', (string) $flattened);
        $this->assertStringContainsString('接続できません', (string) $flattened);
    }

    /**
     * @test
     */
    public function メーラーがlogのときは届いていないことを警告する(): void
    {
        // MAIL_MAILER=log は送信成功として扱われるが、実際には届かない
        Mail::fake();
        config(['mail.default' => 'log']);

        $warned = false;
        Log::shouldReceive('warning')->andReturnUsing(function () use (&$warned) {
            $warned = true;
        });
        Log::shouldReceive('info', 'error')->zeroOrMoreTimes();

        $this->assertTrue($this->dispatcher->send('staff@example.com', $this->mailable()));
        $this->assertTrue($warned);
    }
}

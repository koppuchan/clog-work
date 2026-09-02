<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * リクエストログ出力ミドルウェアの検証。
 *
 * 1リクエストにつき「開始」と「終了」の2件を出力し、
 * レスポンスに追跡用のリクエストIDを付ける。
 */
class LogRequestsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 記録された info ログを集める
     *
     * @return array<int, array{message: string, context: array<string, mixed>}>
     */
    private function captureInfoLogs(): array
    {
        $logs = [];

        Log::shouldReceive('withContext')->zeroOrMoreTimes();
        Log::shouldReceive('error', 'warning', 'debug')->zeroOrMoreTimes();
        Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context = []) use (&$logs) {
            $logs[] = ['message' => $message, 'context' => $context];
        });

        return $logs;
    }

    /**
     * @test
     */
    public function 開始と終了のログを出力する(): void
    {
        $logs = [];

        Log::shouldReceive('withContext')->zeroOrMoreTimes();
        Log::shouldReceive('error', 'warning', 'debug')->zeroOrMoreTimes();
        Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context = []) use (&$logs) {
            $logs[] = $message;
        });

        $this->get('/admin/login')->assertOk();

        $started = array_filter($logs, fn (string $m) => str_contains($m, '【開始】'));
        $finished = array_filter($logs, fn (string $m) => str_contains($m, '【終了】'));

        $this->assertCount(1, $started);
        $this->assertCount(1, $finished);
    }

    /**
     * @test
     */
    public function レスポンスに追跡用のリクエスト_i_dを付ける(): void
    {
        $this->get('/admin/login')->assertHeader('X-Request-ID');
    }

    /**
     * @test
     */
    public function 開始ログにメソッドと_ur_lを含める(): void
    {
        $contexts = [];

        Log::shouldReceive('withContext')->zeroOrMoreTimes();
        Log::shouldReceive('error', 'warning', 'debug')->zeroOrMoreTimes();
        Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context = []) use (&$contexts) {
            if (str_contains($message, '【開始】')) {
                $contexts[] = $context;
            }
        });

        $this->get('/admin/login');

        $this->assertSame('GET', $contexts[0]['method']);
        $this->assertStringContainsString('/admin/login', $contexts[0]['url']);
    }

    /**
     * @test
     */
    public function 終了ログに状態コードと実行時間を含める(): void
    {
        $contexts = [];

        Log::shouldReceive('withContext')->zeroOrMoreTimes();
        Log::shouldReceive('error', 'warning', 'debug')->zeroOrMoreTimes();
        Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context = []) use (&$contexts) {
            if (str_contains($message, '【終了】')) {
                $contexts[] = $context;
            }
        });

        $this->get('/admin/login');

        $this->assertSame(200, $contexts[0]['status_code']);
        $this->assertIsFloat($contexts[0]['execution_time_ms']);
    }

    /**
     * @test
     */
    public function 認証済みならログの文脈に利用者を含める(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->forCompany($company->id)->admin()->create(['is_retired' => false]);

        $contexts = [];

        Log::shouldReceive('info', 'error', 'warning', 'debug')->zeroOrMoreTimes();
        Log::shouldReceive('withContext')->andReturnUsing(function (array $context) use (&$contexts) {
            $contexts[] = $context;
        });

        $this->actingAs($admin, 'admin')->get('/admin/shifts');

        $this->assertSame($admin->id, $contexts[0]['user_id']);
        $this->assertArrayHasKey('request_id', $contexts[0]);
    }
}

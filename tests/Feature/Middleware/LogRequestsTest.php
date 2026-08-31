<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogRequestsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function middleware_logs_incoming_request(): void
    {
        // Arrange
        Log::shouldReceive('withContext')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->with('Incoming request', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('Request completed', \Mockery::type('array'))
            ->once();

        // Act
        $response = $this->get('/');

        // Assert - ミドルウェアが実行されたことを確認
        $response->assertOk();
    }

    /**
     * @test
     */
    public function middleware_adds_request_id_header(): void
    {
        // Act
        $response = $this->get('/');

        // Assert
        $response->assertHeader('X-Request-ID');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $response->headers->get('X-Request-ID')
        );
    }

    /**
     * @test
     */
    public function middleware_logs_user_information_when_authenticated(): void
    {
        // Arrange
        $user = User::factory()->create(['id' => 123]);

        Log::shouldReceive('withContext')
            ->once()
            ->with(\Mockery::on(function ($context) {
                return isset($context['user_id']) && $context['user_id'] === 123;
            }))
            ->andReturnSelf();

        Log::shouldReceive('info')->twice();

        // Act
        $response = $this->actingAs($user)->get('/');

        // Assert
        $response->assertOk();
    }

    /**
     * @test
     */
    public function middleware_logs_execution_time(): void
    {
        // Arrange
        Log::shouldReceive('withContext')->once()->andReturnSelf();
        Log::shouldReceive('info')->once();

        Log::shouldReceive('info')
            ->once()
            ->with('Request completed', \Mockery::on(function ($context) {
                return isset($context['execution_time_ms']) &&
                       is_numeric($context['execution_time_ms']) &&
                       $context['execution_time_ms'] >= 0;
            }));

        // Act
        $response = $this->get('/');

        // Assert
        $response->assertOk();
    }

    /**
     * @test
     */
    public function middleware_logs_status_code(): void
    {
        // Arrange
        Log::shouldReceive('withContext')->once()->andReturnSelf();
        Log::shouldReceive('info')->once();

        Log::shouldReceive('info')
            ->once()
            ->with('Request completed', \Mockery::on(function ($context) {
                return isset($context['status_code']) && $context['status_code'] === 200;
            }));

        // Act
        $response = $this->get('/');

        // Assert
        $response->assertOk();
    }
}

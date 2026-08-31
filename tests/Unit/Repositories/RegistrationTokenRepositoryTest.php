<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\RegistrationToken;
use App\Repositories\RegistrationTokenRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/RegistrationTokenRepositoryTest.php
 */
class RegistrationTokenRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RegistrationTokenRepository(new RegistrationToken);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeTokenData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'token' => Str::random(64),
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => null,
        ], $overrides);
    }

    public function test_find_by_tokenでトークンを取得_存在する場合はトークンを返す(): void
    {
        // Arrange
        $tokenString = Str::random(64);
        RegistrationToken::query()->create($this->makeTokenData(['token' => $tokenString]));

        // Act
        $result = $this->repository->findByToken($tokenString);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($tokenString, $result->token);
    }

    public function test_find_by_tokenでトークンを取得_存在しない場合はnullを返す(): void
    {
        // Act
        $result = $this->repository->findByToken('non-existent-token');

        // Assert
        $this->assertNull($result);
    }

    public function test_find_valid_by_emailで有効なトークンを取得_有効なトークンが存在する場合(): void
    {
        // Arrange
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'valid@example.com',
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => null,
        ]));

        // Act
        $result = $this->repository->findValidByEmail('valid@example.com');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('valid@example.com', $result->email);
    }

    public function test_find_valid_by_emailで有効なトークンを取得_期限切れの場合はnullを返す(): void
    {
        // Arrange
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'expired@example.com',
            'expires_at' => CarbonImmutable::now()->subHours(1),
            'verified_at' => null,
        ]));

        // Act
        $result = $this->repository->findValidByEmail('expired@example.com');

        // Assert
        $this->assertNull($result);
    }

    public function test_find_valid_by_emailで有効なトークンを取得_検証済みの場合はnullを返す(): void
    {
        // Arrange
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'verified@example.com',
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => CarbonImmutable::now(),
        ]));

        // Act
        $result = $this->repository->findValidByEmail('verified@example.com');

        // Assert
        $this->assertNull($result);
    }

    public function test_find_valid_by_emailで有効なトークンを取得_メールが一致しない場合はnullを返す(): void
    {
        // Arrange
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'other@example.com',
            'expires_at' => CarbonImmutable::now()->addHours(24),
            'verified_at' => null,
        ]));

        // Act
        $result = $this->repository->findValidByEmail('nonexistent@example.com');

        // Assert
        $this->assertNull($result);
    }

    public function test_createでトークンを作成_正しいデータが保存される(): void
    {
        // Arrange
        $tokenString = Str::random(64);
        $data = $this->makeTokenData([
            'email' => 'new@example.com',
            'token' => $tokenString,
        ]);

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('new@example.com', $result->email);
        $this->assertEquals($tokenString, $result->token);
        $this->assertNull($result->verified_at);
        $this->assertDatabaseHas('registration_tokens', [
            'id' => $result->id,
            'email' => 'new@example.com',
        ]);
    }

    public function test_updateでトークンを更新_verified_atを設定できる(): void
    {
        // Arrange
        $token = RegistrationToken::query()->create($this->makeTokenData());
        $verifiedAt = CarbonImmutable::now();

        // Act
        $result = $this->repository->update($token->id, ['verified_at' => $verifiedAt]);

        // Assert
        $this->assertNotNull($result->verified_at);
        $this->assertEquals(
            $verifiedAt->format('Y-m-d H:i:s'),
            $result->verified_at->format('Y-m-d H:i:s')
        );
    }

    public function test_updateでトークンを更新_expires_atを変更できる(): void
    {
        // Arrange
        $token = RegistrationToken::query()->create($this->makeTokenData());
        $newExpiry = CarbonImmutable::now()->addHours(48);

        // Act
        $result = $this->repository->update($token->id, ['expires_at' => $newExpiry]);

        // Assert
        $this->assertEquals(
            $newExpiry->format('Y-m-d H:i:s'),
            $result->expires_at->format('Y-m-d H:i:s')
        );
    }

    public function test_updateでトークンを更新_存在しない_i_dの場合は例外を投げる(): void
    {
        // Assert
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // Act
        $this->repository->update(9999, ['verified_at' => CarbonImmutable::now()]);
    }

    public function test_deleteでトークンを削除_正常に削除される(): void
    {
        // Arrange
        $token = RegistrationToken::query()->create($this->makeTokenData());

        // Act
        $result = $this->repository->delete($token->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('registration_tokens', ['id' => $token->id]);
    }

    public function test_deleteでトークンを削除_存在しない場合はfalseを返す(): void
    {
        // Act
        $result = $this->repository->delete(9999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_delete_unverified_by_emailで未検証トークンを削除_未検証のみ削除される(): void
    {
        // Arrange
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'target@example.com',
            'token' => Str::random(64),
            'verified_at' => null,
        ]));
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'target@example.com',
            'token' => Str::random(64),
            'verified_at' => null,
        ]));
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'target@example.com',
            'token' => Str::random(64),
            'verified_at' => CarbonImmutable::now(),
        ]));

        // Act
        $deletedCount = $this->repository->deleteUnverifiedByEmail('target@example.com');

        // Assert
        $this->assertEquals(2, $deletedCount);
        $this->assertDatabaseHas('registration_tokens', [
            'email' => 'target@example.com',
        ]);
    }

    public function test_delete_unverified_by_emailで未検証トークンを削除_別メールのデータは削除しない(): void
    {
        // Arrange
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'target@example.com',
            'token' => Str::random(64),
            'verified_at' => null,
        ]));
        RegistrationToken::query()->create($this->makeTokenData([
            'email' => 'other@example.com',
            'token' => Str::random(64),
            'verified_at' => null,
        ]));

        // Act
        $deletedCount = $this->repository->deleteUnverifiedByEmail('target@example.com');

        // Assert
        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseHas('registration_tokens', [
            'email' => 'other@example.com',
        ]);
    }

    public function test_delete_unverified_by_emailで未検証トークンを削除_該当なしの場合は0を返す(): void
    {
        // Act
        $deletedCount = $this->repository->deleteUnverifiedByEmail('nonexistent@example.com');

        // Assert
        $this->assertEquals(0, $deletedCount);
    }

    public function test_delete_expiredで期限切れトークンを削除_期限切れのみ削除される(): void
    {
        // Arrange
        RegistrationToken::query()->create($this->makeTokenData([
            'token' => Str::random(64),
            'expires_at' => CarbonImmutable::now()->subHours(1),
        ]));
        RegistrationToken::query()->create($this->makeTokenData([
            'token' => Str::random(64),
            'expires_at' => CarbonImmutable::now()->subDays(1),
        ]));
        RegistrationToken::query()->create($this->makeTokenData([
            'token' => Str::random(64),
            'expires_at' => CarbonImmutable::now()->addHours(24),
        ]));

        // Act
        $deletedCount = $this->repository->deleteExpired();

        // Assert
        $this->assertEquals(2, $deletedCount);
    }

    public function test_delete_expiredで期限切れトークンを削除_該当なしの場合は0を返す(): void
    {
        // Arrange
        RegistrationToken::query()->create($this->makeTokenData([
            'token' => Str::random(64),
            'expires_at' => CarbonImmutable::now()->addHours(24),
        ]));

        // Act
        $deletedCount = $this->repository->deleteExpired();

        // Assert
        $this->assertEquals(0, $deletedCount);
    }
}

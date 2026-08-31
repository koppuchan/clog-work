<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use DatabaseTransactions;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

    /**
     * @test
     */
    public function find_by_id_returns_user_when_exists(): void
    {
        // Arrange
        $user = User::factory()->create(['name' => 'Test User']);

        // Act
        $result = $this->service->findById($user->id);

        // Assert
        $this->assertEquals('Test User', $result->name);
    }

    /**
     * @test
     */
    public function find_by_id_throws_exception_when_not_found(): void
    {
        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('ユーザーが見つかりません。');

        // Act
        $this->service->findById(9999);
    }

    /**
     * @test
     */
    public function find_by_id_with_relations_returns_user_with_relations(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $result = $this->service->findByIdWithRelations($user->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('roles'));
    }

    /**
     * @test
     */
    public function create_creates_new_user_with_hashed_password(): void
    {
        // Arrange
        $data = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertEquals('New User', $result->name);
        $this->assertEquals('newuser@example.com', $result->email);
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);

        // パスワードがハッシュ化されているか確認
        $this->assertTrue(Hash::check('password123', $result->password));
    }

    /**
     * @test
     */
    public function create_generates_random_password_when_not_provided(): void
    {
        // Arrange
        $data = [
            'name' => 'User Without Password',
            'email' => 'nopassword@example.com',
        ];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertNotNull($result->password);
        $this->assertNotEmpty($result->password);
    }

    /**
     * @test
     */
    public function create_throws_exception_when_name_is_empty(): void
    {
        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('氏名は必須です。');

        // Act
        $this->service->create(['email' => 'test@example.com']);
    }

    /**
     * @test
     */
    public function create_throws_exception_when_email_is_empty(): void
    {
        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('メールアドレスは必須です。');

        // Act
        $this->service->create(['name' => 'Test User']);
    }

    /**
     * @test
     */
    public function create_throws_exception_when_email_already_exists(): void
    {
        // Arrange
        User::factory()->create(['email' => 'duplicate@example.com']);

        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('このメールアドレスは既に使用されています。');

        // Act
        $this->service->create([
            'name' => 'Another User',
            'email' => 'duplicate@example.com',
        ]);
    }

    /**
     * @test
     */
    public function update_updates_user_successfully(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $data = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        // Act
        $result = $this->service->update($user->id, $data);

        // Assert
        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('updated@example.com', $result->email);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /**
     * @test
     */
    public function update_hashes_password_when_provided(): void
    {
        // Arrange
        $user = User::factory()->create();
        $data = ['password' => 'newpassword123'];

        // Act
        $result = $this->service->update($user->id, $data);

        // Assert
        $this->assertTrue(Hash::check('newpassword123', $result->password));
    }

    /**
     * @test
     */
    public function update_does_not_change_password_when_empty(): void
    {
        // Arrange
        $oldPassword = Hash::make('oldpassword');
        $user = User::factory()->create(['password' => $oldPassword]);
        $data = ['password' => '', 'name' => 'Updated Name'];

        // Act
        $result = $this->service->update($user->id, $data);

        // Assert
        $this->assertEquals($oldPassword, $result->password);
    }

    /**
     * @test
     */
    public function update_throws_exception_when_email_already_exists_for_different_user(): void
    {
        // Arrange
        User::factory()->create(['email' => 'existing@example.com']);
        $user = User::factory()->create(['email' => 'user@example.com']);

        // Assert
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('このメールアドレスは既に使用されています。');

        // Act
        $this->service->update($user->id, ['email' => 'existing@example.com']);
    }

    /**
     * @test
     */
    public function update_allows_keeping_same_email(): void
    {
        // Arrange
        $user = User::factory()->create(['email' => 'user@example.com']);

        // Act
        $result = $this->service->update($user->id, [
            'name' => 'Updated Name',
            'email' => 'user@example.com',
        ]);

        // Assert
        $this->assertEquals('user@example.com', $result->email);
    }

    /**
     * @test
     */
    public function delete_removes_user_successfully(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $this->service->delete($user->id);

        // Assert
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * @test
     */
    public function delete_throws_exception_when_user_not_found(): void
    {
        // Assert
        $this->expectException(NotFoundException::class);

        // Act
        $this->service->delete(9999);
    }
}

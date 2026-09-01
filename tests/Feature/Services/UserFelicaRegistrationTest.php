<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * スタッフ編集画面からの FeliCa カード登録の検証。
 *
 * 登録日時（felica_registered_at）は、カードが変わったときだけ更新し、
 * 他の項目のみを編集した場合は据え置く。
 */
class UserFelicaRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private UserService $service;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserService::class);
        $this->company = Company::factory()->create(['company_code' => '920001']);
        $this->user = User::factory()->create(['name' => '登録 太郎']);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    /**
     * @test
     */
    public function カードを登録すると登録日時が記録される(): void
    {
        // Act
        $this->service->update($this->user->id, ['felica_idm' => 'aaaabbbbccccdddd']);

        // Assert
        $user = $this->user->fresh();
        $this->assertSame('aaaabbbbccccdddd', $user->felica_idm);
        $this->assertNotNull($user->felica_registered_at);
    }

    /**
     * @test
     */
    public function カードを変更すると登録日時が更新される(): void
    {
        // Arrange
        $this->service->update($this->user->id, ['felica_idm' => 'aaaabbbbccccdddd']);
        $first = $this->user->fresh()->felica_registered_at;

        // Act
        $this->travel(1)->minutes();
        $this->service->update($this->user->id, ['felica_idm' => '1111222233334444']);

        // Assert
        $user = $this->user->fresh();
        $this->assertSame('1111222233334444', $user->felica_idm);
        $this->assertTrue($user->felica_registered_at->greaterThan($first));
    }

    /**
     * @test
     */
    public function カードを変えずに他の項目を更新しても登録日時は変わらない(): void
    {
        // Arrange
        $this->service->update($this->user->id, ['felica_idm' => 'aaaabbbbccccdddd']);
        $registeredAt = $this->user->fresh()->felica_registered_at;

        // Act
        $this->travel(1)->minutes();
        $this->service->update($this->user->id, [
            'name' => '登録 次郎',
            'felica_idm' => 'aaaabbbbccccdddd',
        ]);

        // Assert
        $user = $this->user->fresh();
        $this->assertSame('登録 次郎', $user->name);
        $this->assertTrue($user->felica_registered_at->equalTo($registeredAt));
    }

    /**
     * @test
     */
    public function 登録を解除すると登録日時もクリアされる(): void
    {
        // Arrange
        $this->service->update($this->user->id, ['felica_idm' => 'aaaabbbbccccdddd']);

        // Act
        $this->service->update($this->user->id, ['felica_idm' => null]);

        // Assert
        $user = $this->user->fresh();
        $this->assertNull($user->felica_idm);
        $this->assertNull($user->felica_registered_at);
    }

    /**
     * @test
     */
    public function 同じカードを複数のユーザーに登録することはできない(): void
    {
        // Arrange
        $this->service->update($this->user->id, ['felica_idm' => 'aaaabbbbccccdddd']);

        $other = User::factory()->create(['name' => '登録 花子']);
        $other->companies()->attach($this->company->id, ['is_primary' => true]);

        // Assert: DB の一意制約（uk_users_felica_idm）で弾かれる
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Act
        $this->service->update($other->id, ['felica_idm' => 'aaaabbbbccccdddd']);
    }
}

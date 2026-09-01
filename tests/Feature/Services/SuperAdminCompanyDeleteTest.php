<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\BusinessException;
use App\Models\Company;
use App\Models\User;
use App\Services\SuperAdminService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 事業所の削除の検証。
 *
 * 削除の目的は関連データの除去だけでなく、管理者のメールアドレスを
 * 再登録に使えるようにすること。users は user_companies 経由の多対多のため、
 * 会社を消すだけではユーザー本体と email の一意制約が残ってしまう。
 */
class SuperAdminCompanyDeleteTest extends TestCase
{
    use DatabaseTransactions;

    private SuperAdminService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SuperAdminService::class);
    }

    private function makeCompany(string $code, string $name): Company
    {
        return Company::factory()->create(['company_code' => $code, 'name' => $name]);
    }

    private function attach(User $user, Company $company): void
    {
        $user->companies()->attach($company->id, ['is_primary' => true]);
    }

    /**
     * @test
     */
    public function 事業所を削除できる(): void
    {
        // Arrange
        $company = $this->makeCompany('940001', '削除テスト株式会社');

        // Act
        $result = $this->service->deleteCompany($company->id);

        // Assert
        $this->assertSame('削除テスト株式会社', $result['company_name']);
        $this->assertNull(Company::find($company->id));
    }

    /**
     * @test
     */
    public function 削除後にその事業所のメールアドレスを再登録できる(): void
    {
        // Arrange
        $company = $this->makeCompany('940002', '解放テスト株式会社');
        $owner = User::factory()->create([
            'email' => 'release-me@example.com',
            'is_owner' => true,
        ]);
        $this->attach($owner, $company);

        // Act
        $result = $this->service->deleteCompany($company->id);

        // Assert
        $this->assertSame(1, $result['deleted_user_count']);
        $this->assertContains('release-me@example.com', $result['released_emails']);
        $this->assertNull(User::find($owner->id));

        // 同じメールアドレスで新しい事業所を作成できる
        $created = $this->service->createCompanyWithOwner(
            '再登録 株式会社',
            '再登録 太郎',
            'release-me@example.com',
            'password1234',
        );
        $this->assertSame('release-me@example.com', $created['owner']->email);
    }

    /**
     * @test
     */
    public function 複数の事業所に所属するユーザーは削除されない(): void
    {
        // Arrange
        $target = $this->makeCompany('940003', '削除対象株式会社');
        $other = $this->makeCompany('940004', '存続株式会社');

        $shared = User::factory()->create(['email' => 'shared@example.com']);
        $this->attach($shared, $target);
        $shared->companies()->attach($other->id, ['is_primary' => false]);

        // Act
        $result = $this->service->deleteCompany($target->id);

        // Assert: アカウントは残り、所属だけが外れる
        $this->assertSame(0, $result['deleted_user_count']);
        $this->assertNotNull(User::find($shared->id));
        $this->assertFalse($shared->fresh()->companies->contains($target->id));
        $this->assertTrue($shared->fresh()->companies->contains($other->id));
    }

    /**
     * @test
     */
    public function 関連データもあわせて削除される(): void
    {
        // Arrange
        $company = $this->makeCompany('940005', 'カスケード株式会社');
        $user = User::factory()->create();
        $this->attach($user, $company);

        $departmentId = $company->departments()->create(['name' => '営業部'])->id;

        // Act
        $this->service->deleteCompany($company->id);

        // Assert: companies を参照する外部キーは ON DELETE CASCADE
        $this->assertDatabaseMissing('departments', ['id' => $departmentId]);
        $this->assertDatabaseMissing('user_companies', ['company_id' => $company->id]);
    }

    /**
     * @test
     */
    public function スーパー管理者が所属する事業所は削除できない(): void
    {
        // Arrange
        $company = $this->makeCompany('940006', '運営株式会社');
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->attach($superAdmin, $company);

        // Assert
        $this->expectException(BusinessException::class);

        // Act
        $this->service->deleteCompany($company->id);
    }

    /**
     * @test
     */
    public function 存在しない事業所を削除しようとすると例外になる(): void
    {
        // Assert
        $this->expectException(\App\Exceptions\NotFoundException::class);

        // Act
        $this->service->deleteCompany(999999);
    }
}

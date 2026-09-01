<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 個人コードの変更の検証。
 *
 * 事業所内での一意性はアプリ側で担保しているため、
 * 変更を許すなら更新時にも重複を弾く必要がある。
 */
class EmployeeCodeUpdateTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create(['name' => '管理者', 'employee_code' => '100001', 'is_retired' => false]);

        $this->staff = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create(['name' => '一般 太郎', 'employee_code' => '100002', 'is_retired' => false]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function update(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'admin')->put(
            "/admin/users/{$this->staff->id}",
            array_merge([
                'name' => $this->staff->name,
                'employee_code' => '100002',
            ], $overrides)
        );
    }

    /**
     * @test
     */
    public function 個人コードを変更できる(): void
    {
        $this->update(['employee_code' => '200005']);

        $this->assertSame('200005', $this->staff->fresh()->employee_code);
    }

    /**
     * @test
     */
    public function 事業所内で重複する個人コードは保存できない(): void
    {
        // 管理者が使っているコードへは変更させない
        $response = $this->update(['employee_code' => '100001']);

        $response->assertSessionHasErrors('employee_code');
        $this->assertSame('100002', $this->staff->fresh()->employee_code);
    }

    /**
     * @test
     */
    public function 自分自身の現在のコードは重複として扱わない(): void
    {
        // 個人コードを変えずに他の項目だけ更新する場合
        $response = $this->update(['name' => '一般 次郎']);

        $response->assertSessionHasNoErrors();
        $this->assertSame('一般 次郎', $this->staff->fresh()->name);
        $this->assertSame('100002', $this->staff->fresh()->employee_code);
    }

    /**
     * @test
     */
    public function 別の事業所の個人コードとは重複しても構わない(): void
    {
        $otherCompany = Company::factory()->create();
        User::factory()
            ->forCompany($otherCompany->id)
            ->employee()
            ->create(['employee_code' => '300007', 'is_retired' => false]);

        $this->update(['employee_code' => '300007']);

        $this->assertSame('300007', $this->staff->fresh()->employee_code);
    }

    /**
     * @test
     */
    public function 六桁の数字以外は保存できない(): void
    {
        $response = $this->update(['employee_code' => 'abc']);

        $response->assertSessionHasErrors('employee_code');
        $this->assertSame('100002', $this->staff->fresh()->employee_code);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 位置情報を記録する対象を、部署ごと・スタッフごとに指定できること
 */
class StampLocationTargetSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->forCompany($this->company->id)->admin()->create(['is_retired' => false]);
    }

    /**
     * @test
     */
    public function 部署を作るときに位置情報を記録する設定を付けられる(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/settings/departments', ['name' => '加古川店', 'records_stamp_location' => true]);

        $response->assertSuccessful()->assertJsonPath('recordsStampLocation', true);
        $this->assertTrue(Department::query()->where('name', '加古川店')->firstOrFail()->records_stamp_location);
    }

    /**
     * @test
     */
    public function 部署の初期状態では位置情報を記録しない(): void
    {
        $this->actingAs($this->admin, 'admin')->postJson('/admin/settings/departments', ['name' => '芦屋店'])->assertSuccessful();

        $this->assertFalse(Department::query()->where('name', '芦屋店')->firstOrFail()->records_stamp_location);
    }

    /**
     * @test
     */
    public function 部署の設定を更新でき他の部署には影響しない(): void
    {
        $target = Department::query()->create(['company_id' => $this->company->id, 'name' => '加古川店']);
        $other = Department::query()->create(['company_id' => $this->company->id, 'name' => '芦屋店']);

        $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/settings/departments/{$target->id}", ['name' => '加古川店', 'records_stamp_location' => true])
            ->assertSuccessful()
            ->assertJsonPath('recordsStampLocation', true);

        $this->assertTrue($target->fresh()->records_stamp_location);
        $this->assertFalse($other->fresh()->records_stamp_location);
    }

    /**
     * @test
     */
    public function 部署の項目を送らない更新では設定を変えない(): void
    {
        $target = Department::query()->create(['company_id' => $this->company->id, 'name' => '加古川店', 'records_stamp_location' => true]);

        $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/settings/departments/{$target->id}", ['name' => '加古川店（改）'])
            ->assertSuccessful();

        $this->assertTrue($target->fresh()->records_stamp_location);
    }

    /**
     * @test
     */
    public function スタッフの設定として位置情報を記録する指定を保存できる(): void
    {
        $target = User::factory()->forCompany($this->company->id)->employee()->create(['name' => '対象スタッフ', 'email' => 'target-location@example.com']);

        $this->actingAs($this->admin, 'admin')
            ->put('/admin/users/'.$target->id, [
                'name' => '対象スタッフ',
                'email' => 'target-location@example.com',
                'role_id' => 3,
                'department_id' => null,
                'records_stamp_location' => true,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue($target->fresh()->records_stamp_location);
    }
}

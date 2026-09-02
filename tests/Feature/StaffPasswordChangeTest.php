<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * スタッフの自己パスワード変更の検証。
 *
 * 初回ログイン時に変更を求める仕組みのため、変更後は
 * must_change_password が下りることが要点。
 */
class StaffPasswordChangeTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();

        $this->staff = User::factory()
            ->forCompany($company->id)
            ->employee()
            ->create([
                'password' => Hash::make('old-password'),
                'must_change_password' => true,
                'is_retired' => false,
            ]);
    }

    /**
     * @test
     */
    public function パスワード変更画面を表示できる(): void
    {
        $this->actingAs($this->staff, 'staff')
            ->get('/staff/password-change')
            ->assertStatus(200);
    }

    /**
     * @test
     */
    public function パスワードを変更できる(): void
    {
        $response = $this->actingAs($this->staff, 'staff')->post('/staff/password-change', [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasNoErrors();

        $this->staff->refresh();
        $this->assertTrue(Hash::check('new-password-123', $this->staff->password));
    }

    /**
     * @test
     */
    public function 変更するとパスワード変更の要求が解除される(): void
    {
        $this->actingAs($this->staff, 'staff')->post('/staff/password-change', [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $this->assertFalse($this->staff->fresh()->must_change_password);
    }

    /**
     * @test
     */
    public function 確認用と一致しなければ変更できない(): void
    {
        $response = $this->actingAs($this->staff, 'staff')->post('/staff/password-change', [
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('old-password', $this->staff->fresh()->password));
    }

    /**
     * @test
     */
    public function 未ログインでは変更画面に入れない(): void
    {
        $this->get('/staff/password-change')->assertRedirect();
    }
}

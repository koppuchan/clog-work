<?php

namespace Tests\Feature\Auth;

use App\Mail\CodeReminderMail;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminCodeReminderTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdminUser(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['employee_code' => '999901']);

        $user->companies()->attach($company->id, ['is_primary' => true]);

        $adminRole = Role::query()->where('id', 1)->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole->id);
        } else {
            $adminRole = Role::query()->create(['id' => 1, 'name' => 'admin']);
            $user->roles()->attach($adminRole->id);
        }

        return ['user' => $user, 'company' => $company];
    }

    public function test_forgot_code_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/forgot-code');

        $response->assertStatus(200);
    }

    public function test_code_reminder_email_is_sent_for_valid_admin_user(): void
    {
        Mail::fake();

        ['user' => $user] = $this->createAdminUser();

        $response = $this->post('/admin/forgot-code', [
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('admin.code-reminder.sent'));

        Mail::assertSent(CodeReminderMail::class, function (CodeReminderMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->user->id === $user->id;
        });
    }

    public function test_code_reminder_redirects_even_for_non_existent_email(): void
    {
        Mail::fake();

        $response = $this->post('/admin/forgot-code', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect(route('admin.code-reminder.sent'));

        Mail::assertNothingSent();
    }

    public function test_code_reminder_is_not_sent_for_non_admin_user(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['employee_code' => '999902']);
        $user->companies()->attach($company->id, ['is_primary' => true]);

        $response = $this->post('/admin/forgot-code', [
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('admin.code-reminder.sent'));

        Mail::assertNothingSent();
    }

    public function test_code_reminder_sent_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/forgot-code/sent');

        $response->assertStatus(200);
    }

    public function test_email_is_required_for_code_reminder(): void
    {
        $response = $this->post('/admin/forgot-code', [
            'email' => '',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_email_must_be_valid_for_code_reminder(): void
    {
        $response = $this->post('/admin/forgot-code', [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}

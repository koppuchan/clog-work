<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * トップページは認証状態に応じて振り分ける。
     * 未ログインなら管理者ログインへ送る。
     */
    public function test_the_application_redirects_guests_to_admin_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin/login');
    }
}

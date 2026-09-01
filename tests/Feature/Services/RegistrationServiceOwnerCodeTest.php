<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\RegistrationToken;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 新規事業所の登録時に、管理者へ割り当てられる個人コードの検証。
 *
 * 以前は全事業所を通した連番で採番していたため、事業所ごとに
 * 管理者だけが飛び番になっていた。全事業所で共通の固定値を割り当てる。
 */
class RegistrationServiceOwnerCodeTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RegistrationService::class);
    }

    /**
     * 検証済みのトークンを用意する
     */
    private function verifiedToken(string $email): RegistrationToken
    {
        return RegistrationToken::create([
            'email' => $email,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => Carbon::now()->addHours(24),
            'verified_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{user: User, company: \App\Models\Company}
     */
    private function register(string $email, string $companyName): array
    {
        $token = $this->verifiedToken($email);

        return $this->service->completeRegistration(
            $token->token,
            [
                'name' => '管理 太郎',
                'name_kana' => 'カンリ タロウ',
                'password' => 'password1234',
            ],
            ['name' => $companyName],
        );
    }

    /**
     * @test
     */
    public function 管理者には設定された固定の個人コードが割り当てられる(): void
    {
        // Arrange
        config(['attendance.owner_employee_code' => '009999']);

        // Act
        $result = $this->register('owner-a@example.com', 'テスト事業所A');

        // Assert
        $this->assertSame('009999', $result['user']->employee_code);
        $this->assertTrue((bool) $result['user']->is_owner);
    }

    /**
     * @test
     */
    public function 事業所が異なっても管理者の個人コードは同じ固定値になる(): void
    {
        // Arrange
        config(['attendance.owner_employee_code' => '009999']);

        // Act
        $first = $this->register('owner-b@example.com', 'テスト事業所B');
        $second = $this->register('owner-c@example.com', 'テスト事業所C');

        // Assert
        $this->assertSame('009999', $first['user']->employee_code);
        $this->assertSame('009999', $second['user']->employee_code);
        $this->assertNotSame($first['company']->id, $second['company']->id);
    }

    /**
     * @test
     */
    public function 既存スタッフの個人コードに影響されない(): void
    {
        // Arrange: 全社横断の最大値が大きくなるようスタッフを作っておく
        config(['attendance.owner_employee_code' => '009999']);
        User::factory()->create(['employee_code' => '000500']);

        // Act
        $result = $this->register('owner-d@example.com', 'テスト事業所D');

        // Assert: 連番採番であれば 000501 になるはずのところ、固定値が入る
        $this->assertSame('009999', $result['user']->employee_code);
    }

    /**
     * @test
     */
    public function 設定値は6桁にゼロ埋めされる(): void
    {
        // Arrange
        config(['attendance.owner_employee_code' => '9999']);

        // Act
        $result = $this->register('owner-e@example.com', 'テスト事業所E');

        // Assert
        $this->assertSame('009999', $result['user']->employee_code);
    }
}

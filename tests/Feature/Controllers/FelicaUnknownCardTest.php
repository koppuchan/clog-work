<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Company;
use App\Services\FelicaCardRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 打刻端末で未登録カードをかざしたときに、そのIDmが登録の候補として
 * 残ることの検証。打刻アプリ側は変更せず、既存の打刻経路をそのまま使う。
 */
class FelicaUnknownCardTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->company = Company::factory()->create();
    }

    private function tap(string $idm): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/stamp/{$this->company->uuid}/felica", ['idm' => $idm]);
    }

    /**
     * @test
     */
    public function 未登録カードをかざすと登録の候補に残る(): void
    {
        $response = $this->tap('0123456789abcdef');

        $response->assertStatus(404);

        $cards = app(FelicaCardRegistrationService::class)->recentUnregistered($this->company->id);

        $this->assertCount(1, $cards);
        $this->assertSame('0123456789abcdef', $cards[0]['idm']);
    }

    /**
     * @test
     */
    public function 形式が正しくない_i_dmは記録しない(): void
    {
        $this->postJson("/stamp/{$this->company->uuid}/felica", ['idm' => 'zzz'])
            ->assertStatus(422);

        $this->assertSame([], app(FelicaCardRegistrationService::class)->recentUnregistered($this->company->id));
    }

    /**
     * @test
     */
    public function 会社が見つからない場合は記録しない(): void
    {
        $this->postJson('/stamp/00000000-0000-0000-0000-000000000000/felica', ['idm' => '0123456789abcdef'])
            ->assertStatus(404);

        $this->assertSame([], app(FelicaCardRegistrationService::class)->recentUnregistered($this->company->id));
    }
}

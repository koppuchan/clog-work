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

    /**
     * @test
     *
     * 管理者がスタッフ編集画面でカード登録を待ち受けている間は、
     * 未登録カードをかざしても打刻専用画面向けの警告ログを残さない
     * （No.52: カード登録モード中でも未登録カードの表示が出るという報告）。
     * ただし登録候補としては引き続き記録され、選べる状態は維持する。
     */
    public function 登録待ち状態の間は未登録カードの警告ログを残さない(): void
    {
        app(FelicaCardRegistrationService::class)->setRegistrationMode($this->company->id, true);

        $response = $this->tap('0123456789abcdef');

        $response->assertStatus(404);
        $this->assertSame(
            0,
            \App\Models\FelicaStampAttempt::query()->where('status', 'unregistered')->count()
        );

        $cards = app(FelicaCardRegistrationService::class)->recentUnregistered($this->company->id);
        $this->assertCount(1, $cards);
        $this->assertSame('0123456789abcdef', $cards[0]['idm']);
    }

    /**
     * @test
     */
    public function 登録待ち状態を解除すれば通常どおり警告ログが残る(): void
    {
        $service = app(FelicaCardRegistrationService::class);
        $service->setRegistrationMode($this->company->id, true);
        $service->setRegistrationMode($this->company->id, false);

        $this->tap('0123456789abcdef')->assertStatus(404);

        $this->assertSame(
            1,
            \App\Models\FelicaStampAttempt::query()->where('status', 'unregistered')->count()
        );
    }
}

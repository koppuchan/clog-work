<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\FelicaCardRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 未登録カードのIDmを覚えておく機能の検証。
 *
 * IDmはカードに印字されていないため、登録するには16桁の英数字を
 * どこかから読み取って手で入力する必要があった。
 * かざしたカードを画面から選べるようにして、その手間をなくす。
 */
class FelicaCardRegistrationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private FelicaCardRegistrationService $service;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = app(FelicaCardRegistrationService::class);
        $this->company = Company::factory()->create();
    }

    /**
     * @test
     */
    public function かざした未登録カードを覚えている(): void
    {
        $this->service->remember($this->company->id, '0123456789abcdef');

        $cards = $this->service->recentUnregistered($this->company->id);

        $this->assertCount(1, $cards);
        $this->assertSame('0123456789abcdef', $cards[0]['idm']);
    }

    /**
     * @test
     */
    public function 大文字で送られても小文字で覚える(): void
    {
        // 打刻アプリからは大文字で届くことがある
        $this->service->remember($this->company->id, '0123456789ABCDEF');

        $this->assertSame('0123456789abcdef', $this->service->recentUnregistered($this->company->id)[0]['idm']);
    }

    /**
     * @test
     */
    public function 新しくかざした順に並ぶ(): void
    {
        $this->service->remember($this->company->id, 'aaaaaaaaaaaaaaaa');
        $this->service->remember($this->company->id, 'bbbbbbbbbbbbbbbb');

        $cards = $this->service->recentUnregistered($this->company->id);

        $this->assertSame('bbbbbbbbbbbbbbbb', $cards[0]['idm']);
        $this->assertSame('aaaaaaaaaaaaaaaa', $cards[1]['idm']);
    }

    /**
     * @test
     */
    public function 同じカードを続けてかざしても重複しない(): void
    {
        $this->service->remember($this->company->id, 'aaaaaaaaaaaaaaaa');
        $this->service->remember($this->company->id, 'aaaaaaaaaaaaaaaa');

        $this->assertCount(1, $this->service->recentUnregistered($this->company->id));
    }

    /**
     * @test
     */
    public function 登録済みになったカードは候補から外れる(): void
    {
        $this->service->remember($this->company->id, '0123456789abcdef');

        User::factory()->forCompany($this->company->id)->create([
            'felica_idm' => '0123456789abcdef',
            'is_retired' => false,
        ]);

        $this->assertSame([], $this->service->recentUnregistered($this->company->id));
    }

    /**
     * @test
     */
    public function 別の会社のカードは混ざらない(): void
    {
        $other = Company::factory()->create();

        $this->service->remember($this->company->id, 'aaaaaaaaaaaaaaaa');
        $this->service->remember($other->id, 'bbbbbbbbbbbbbbbb');

        $cards = $this->service->recentUnregistered($this->company->id);

        $this->assertCount(1, $cards);
        $this->assertSame('aaaaaaaaaaaaaaaa', $cards[0]['idm']);
    }

    /**
     * @test
     */
    public function 覚えている件数には上限がある(): void
    {
        // カードの識別子を際限なく保持しない
        for ($i = 0; $i < 25; $i++) {
            $this->service->remember($this->company->id, sprintf('%016x', $i));
        }

        $this->assertCount(20, $this->service->recentUnregistered($this->company->id));
    }

    /**
     * @test
     */
    public function 何もかざしていなければ空になる(): void
    {
        $this->assertSame([], $this->service->recentUnregistered($this->company->id));
    }

    /**
     * @test
     */
    public function 記録を消せる(): void
    {
        $this->service->remember($this->company->id, 'aaaaaaaaaaaaaaaa');
        $this->service->forget($this->company->id);

        $this->assertSame([], $this->service->recentUnregistered($this->company->id));
    }
}

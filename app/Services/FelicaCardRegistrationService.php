<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * 未登録カードのIDmを一時的に覚えておく
 *
 * カードのIDmはカード自体に印字されていないため、登録するには
 * 16桁の英数字をどこかから読み取って手で入力する必要があった。
 * 全員分を登録し直す場面ではこれが大きな手間になる。
 *
 * 打刻アプリは未登録のカードでもIDmを送ってくるので、その値を覚えておき、
 * スタッフの編集画面から選べるようにする。打刻アプリ側の変更は不要で、
 * カードをかざしてから画面で選ぶだけで登録できる。
 *
 * IDmはカードの識別子のため、登録作業の間だけ保持して自動的に消す。
 */
class FelicaCardRegistrationService
{
    /** 覚えておく件数 */
    private const MAX_CARDS = 20;

    /** 保持する時間（分） */
    private const RETENTION_MINUTES = 10;

    /** 登録モード中とみなす時間（秒）。管理者画面を閉じ忘れても自動的に解除される */
    private const REGISTRATION_MODE_TTL_SECONDS = 300;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * 未登録のカードがかざされたことを記録する
     */
    public function remember(int $companyId, string $idm): void
    {
        $idm = strtolower($idm);
        $now = CarbonImmutable::now();

        $cards = $this->stored($companyId);

        // 同じカードを続けてかざした場合は、新しい方の時刻に寄せる
        $cards = array_values(array_filter($cards, fn (array $card) => $card['idm'] !== $idm));

        array_unshift($cards, [
            'idm' => $idm,
            'tapped_at' => $now->format('Y-m-d H:i:s'),
        ]);

        Cache::put(
            $this->cacheKey($companyId),
            array_slice($cards, 0, self::MAX_CARDS),
            $now->addMinutes(self::RETENTION_MINUTES),
        );
    }

    /**
     * 直近にかざされた未登録カードを新しい順に返す
     *
     * 記録したあとで別のスタッフに登録されたカードは対象から外す。
     *
     * @return array<int, array{idm: string, tapped_at: string}>
     */
    public function recentUnregistered(int $companyId): array
    {
        $cards = $this->stored($companyId);

        if ($cards === []) {
            return [];
        }

        return array_values(array_filter(
            $cards,
            fn (array $card) => $this->userRepository->findByFelicaIdm($card['idm'], $companyId) === null,
        ));
    }

    /**
     * 記録を消す
     */
    public function forget(int $companyId): void
    {
        Cache::forget($this->cacheKey($companyId));
    }

    /**
     * スタッフ編集画面でのカード登録待ち状態をON/OFFする
     *
     * 常駐アプリ側にも同名の「カード登録モード」があり、ON時はIDmを
     * 打刻APIへ送らず画面表示のみに留める設計になっているが、そちらの
     * 切り替え漏れ・タイミングのずれがあっても、管理者がスタッフ編集
     * 画面でカードを待ち受けている間は打刻専用画面に「登録されていない
     * カードです」という警告を出さないようにする（No.52報告）。
     *
     * @param  int  $companyId  会社ID
     * @param  bool  $armed  ONにするか
     */
    public function setRegistrationMode(int $companyId, bool $armed): void
    {
        if ($armed) {
            Cache::put($this->registrationModeCacheKey($companyId), true, self::REGISTRATION_MODE_TTL_SECONDS);

            return;
        }

        Cache::forget($this->registrationModeCacheKey($companyId));
    }

    /**
     * カード登録待ち状態かどうか
     */
    public function isRegistrationModeArmed(int $companyId): bool
    {
        return Cache::has($this->registrationModeCacheKey($companyId));
    }

    private function registrationModeCacheKey(int $companyId): string
    {
        return "felica:registration-mode:{$companyId}";
    }

    /**
     * @return array<int, array{idm: string, tapped_at: string}>
     */
    private function stored(int $companyId): array
    {
        $cards = Cache::get($this->cacheKey($companyId), []);

        return is_array($cards) ? $cards : [];
    }

    private function cacheKey(int $companyId): string
    {
        return "felica:unregistered:{$companyId}";
    }
}

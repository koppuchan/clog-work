<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RegistrationToken;
use App\Repositories\Contracts\RegistrationTokenRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * 登録トークンリポジトリ実装
 */
class RegistrationTokenRepository implements RegistrationTokenRepositoryInterface
{
    public function __construct(
        private readonly RegistrationToken $registrationToken
    ) {}

    /**
     * トークンで登録トークンを取得
     *
     * @param  string  $token  トークン文字列
     */
    public function findByToken(string $token): ?RegistrationToken
    {
        return $this->registrationToken->query()
            ->where('token', $token)
            ->first();
    }

    /**
     * メールアドレスで有効な登録トークンを取得
     *
     * @param  string  $email  メールアドレス
     */
    public function findValidByEmail(string $email): ?RegistrationToken
    {
        return $this->registrationToken->query()
            ->where('email', $email)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->whereNull('verified_at')
            ->first();
    }

    /**
     * 登録トークンを作成
     *
     * @param  array<string, mixed>  $data  トークンデータ
     */
    public function create(array $data): RegistrationToken
    {
        return $this->registrationToken->query()->create($data);
    }

    /**
     * 登録トークンを更新
     *
     * @param  int  $id  トークンID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): RegistrationToken
    {
        $token = $this->registrationToken->query()->findOrFail($id);
        $token->update($data);

        return $token->fresh();
    }

    /**
     * 登録トークンを削除
     *
     * @param  int  $id  トークンID
     */
    public function delete(int $id): bool
    {
        $token = $this->registrationToken->query()->find($id);

        if (! $token) {
            return false;
        }

        return $token->delete();
    }

    /**
     * メールアドレスで未検証のトークンを削除
     *
     * @param  string  $email  メールアドレス
     */
    public function deleteUnverifiedByEmail(string $email): int
    {
        return $this->registrationToken->query()
            ->where('email', $email)
            ->whereNull('verified_at')
            ->delete();
    }

    /**
     * 期限切れのトークンを削除
     */
    public function deleteExpired(): int
    {
        return $this->registrationToken->query()
            ->where('expires_at', '<', CarbonImmutable::now())
            ->delete();
    }
}

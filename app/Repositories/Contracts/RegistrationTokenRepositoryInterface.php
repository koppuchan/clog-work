<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\RegistrationToken;

/**
 * 登録トークンリポジトリインターフェース
 */
interface RegistrationTokenRepositoryInterface
{
    /**
     * トークンで登録トークンを取得
     *
     * @param  string  $token  トークン文字列
     */
    public function findByToken(string $token): ?RegistrationToken;

    /**
     * メールアドレスで有効な登録トークンを取得
     *
     * @param  string  $email  メールアドレス
     */
    public function findValidByEmail(string $email): ?RegistrationToken;

    /**
     * 登録トークンを作成
     *
     * @param  array<string, mixed>  $data  トークンデータ
     */
    public function create(array $data): RegistrationToken;

    /**
     * 登録トークンを更新
     *
     * @param  int  $id  トークンID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): RegistrationToken;

    /**
     * 登録トークンを削除
     *
     * @param  int  $id  トークンID
     */
    public function delete(int $id): bool;

    /**
     * メールアドレスで未検証のトークンを削除
     *
     * @param  string  $email  メールアドレス
     */
    public function deleteUnverifiedByEmail(string $email): int;

    /**
     * 期限切れのトークンを削除
     */
    public function deleteExpired(): int;
}

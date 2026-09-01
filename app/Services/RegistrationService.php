<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Mail\RegistrationVerificationMail;
use App\Models\Company;
use App\Models\RegistrationToken;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\RegistrationTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\HasLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 新規登録サービス
 */
class RegistrationService
{
    use HasLogService;

    private const TOKEN_EXPIRY_HOURS = 24;

    private const TOKEN_LENGTH = 64;

    private const DEFAULT_PAYROLL_CLOSING_DAY = 25;

    private const ADMIN_ROLE_ID = 1;

    private const CODE_DIGITS = 6;

    private const INITIAL_CODE = '000001';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly RegistrationTokenRepositoryInterface $registrationTokenRepository,
        private readonly MailDispatcher $mailDispatcher
    ) {}

    /**
     * 登録トークンを作成してメールを送信
     *
     * @param  string  $email  メールアドレス
     * @return RegistrationToken 作成されたトークン
     *
     * @throws BusinessException メールアドレスが既に登録済みの場合
     */
    public function createTokenAndSendEmail(string $email): RegistrationToken
    {
        // メールアドレスが既に登録済みかチェック
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser) {
            throw new BusinessException('このメールアドレスは既に登録されています。');
        }

        // 既存の有効なトークンを無効化
        $this->registrationTokenRepository->deleteUnverifiedByEmail($email);

        // 新しいトークンを作成
        $token = $this->registrationTokenRepository->create([
            'email' => $email,
            'token' => Str::random(self::TOKEN_LENGTH),
            'expires_at' => CarbonImmutable::now()->addHours(self::TOKEN_EXPIRY_HOURS),
        ]);

        // 確認メールを送信
        $verificationUrl = route('register.verify', ['token' => $token->token]);

        $sent = $this->mailDispatcher->send(
            $email,
            new RegistrationVerificationMail($verificationUrl),
            ['token_id' => $token->id],
        );

        // 送信できないまま登録が進むと、利用者は届かないメールを待ち続けることになる
        if (! $sent) {
            throw new BusinessException('確認メールを送信できませんでした。時間をおいて再度お試しください。');
        }

        return $token;
    }

    /**
     * トークンを検証（未検証のトークンのみ）
     *
     * @param  string  $token  トークン文字列
     * @return RegistrationToken|null 有効なトークン、または無効な場合はnull
     */
    public function verifyToken(string $token): ?RegistrationToken
    {
        $registrationToken = $this->registrationTokenRepository->findByToken($token);

        if (! $registrationToken || ! $registrationToken->isValid()) {
            return null;
        }

        return $registrationToken;
    }

    /**
     * 検証済みトークンを取得
     *
     * @param  string  $token  トークン文字列
     * @return RegistrationToken|null 検証済みのトークン、または無効な場合はnull
     */
    public function getVerifiedToken(string $token): ?RegistrationToken
    {
        $registrationToken = $this->registrationTokenRepository->findByToken($token);

        if (! $registrationToken || ! $registrationToken->isVerified()) {
            return null;
        }

        // 有効期限チェック
        if ($registrationToken->expires_at <= CarbonImmutable::now()) {
            return null;
        }

        return $registrationToken;
    }

    /**
     * トークンを検証済みにしてメールアドレスを確認
     *
     * @param  string  $token  トークン文字列
     * @return RegistrationToken 検証済みのトークン
     *
     * @throws BusinessException トークンが無効な場合
     */
    public function markTokenAsVerified(string $token): RegistrationToken
    {
        $registrationToken = $this->registrationTokenRepository->findByToken($token);

        if (! $registrationToken) {
            throw new BusinessException('無効または期限切れのトークンです。');
        }

        // 有効期限チェック
        if ($registrationToken->expires_at <= CarbonImmutable::now()) {
            throw new BusinessException('無効または期限切れのトークンです。');
        }

        // 未確認の場合のみ確認済みにする（2回目以降はそのまま返す）
        if (! $registrationToken->isVerified()) {
            $registrationToken->markAsVerified();
        }

        return $registrationToken;
    }

    /**
     * ユーザーと会社を作成して登録を完了
     *
     * @param  string  $token  トークン文字列
     * @param  array<string, mixed>  $userData  ユーザーデータ
     * @param  array<string, mixed>  $companyData  会社データ
     * @return array{user: User, company: Company}
     *
     * @throws BusinessException トークンが無効な場合
     */
    public function completeRegistration(string $token, array $userData, array $companyData): array
    {
        $registrationToken = $this->registrationTokenRepository->findByToken($token);

        if (! $registrationToken || ! $registrationToken->isVerified()) {
            throw new BusinessException('無効なトークンです。最初からやり直してください。');
        }

        // 有効期限チェック
        if ($registrationToken->expires_at <= CarbonImmutable::now()) {
            throw new BusinessException('トークンの有効期限が切れています。最初からやり直してください。');
        }

        return DB::transaction(function () use ($registrationToken, $userData, $companyData): array {
            // コード生成の競合時は DB::transaction の自動リトライで再試行される
            // 会社を作成
            $company = $this->companyRepository->create([
                'name' => $companyData['name'],
                'company_code' => $this->generateCompanyCode(),
                'is_closed_on_holidays' => true,
                'payroll_closing_day' => self::DEFAULT_PAYROLL_CLOSING_DAY,
            ]);

            // ユーザーを作成（employee_codeは後で設定）
            $user = $this->userRepository->create([
                'name' => $userData['name'],
                'name_kana' => $userData['name_kana'] ?? null,
                'email' => $registrationToken->email,
                'password' => $userData['password'],
                'employee_code' => $this->ownerEmployeeCode(),
                'must_change_password' => false,
                'is_owner' => true,
            ]);

            // ユーザーを会社に紐付け（管理者として）
            $this->userRepository->attachCompany($user->id, $company->id, isPrimary: true);

            // 管理者ロールを付与
            $this->userRepository->attachRole($user->id, self::ADMIN_ROLE_ID);

            // メール認証済みにする
            $this->userRepository->update($user->id, ['email_verified_at' => CarbonImmutable::now()]);

            // トークンを削除
            $this->registrationTokenRepository->delete($registrationToken->id);

            $this->logInfo('Registration completed', [
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);

            return [
                'user' => $user->fresh(),
                'company' => $company,
            ];
        }, 3);
    }

    /**
     * ユニークな会社コードを生成
     *
     * @return string 6桁の数値会社コード
     */
    private function generateCompanyCode(): string
    {
        $maxCode = $this->companyRepository->getMaxCompanyCode();

        if ($maxCode === null) {
            return self::INITIAL_CODE;
        }

        $nextNumber = (int) $maxCode + 1;

        if ($nextNumber > 999999) {
            throw new \RuntimeException('利用可能な会社コードがありません。');
        }

        return str_pad((string) $nextNumber, self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * 事業所作成時に管理者へ割り当てる個人コードを返す
     *
     * 一般スタッフのような連番ではなく、全事業所で共通の固定値を割り当てる。
     * 個人コードは会社ごとの一意性のみをアプリ層で担保しており DB 側に
     * UNIQUE 制約がないため、事業所をまたいで同じ値を使用できる。
     *
     * @return string 6桁の個人コード
     */
    private function ownerEmployeeCode(): string
    {
        $code = (string) config('attendance.owner_employee_code', '009999');

        return str_pad($code, self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * 期限切れのトークンを削除
     */
    public function cleanupExpiredTokens(): int
    {
        return $this->registrationTokenRepository->deleteExpired();
    }
}

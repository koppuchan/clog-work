<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Mail\NewPasswordMail;
use App\Mail\WelcomeUserMail;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\HasLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * ユーザーサービス
 */
class UserService
{
    use HasLogService;

    private const DEFAULT_STAMP_PASSWORD = '1111';

    private const ADMIN_ROLE_ID = 1;

    private const MANAGER_ROLE_ID = 2;

    private const EMPLOYEE_CODE_DIGITS = 6;

    private const INITIAL_EMPLOYEE_CODE = '000001';

    private const PASSWORD_CHARACTERS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPermissionRepositoryInterface $userPermissionRepository,
        private readonly MailDispatcher $mailDispatcher
    ) {}

    /**
     * 指定ユーザーが会社内で唯一の管理者かどうかを判定
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $companyId  会社ID
     */
    public function isLastAdminInCompany(int $userId, int $companyId): bool
    {
        $user = $this->userRepository->findById($userId);

        if (! $user || ! $user->isAdmin()) {
            return false;
        }

        return $this->userRepository->countAdminsByCompanyId($companyId) <= 1;
    }

    /**
     * IDでユーザーを取得
     *
     * @param  int  $id  ユーザーID
     *
     * @throws NotFoundException ユーザーが見つからない場合
     */
    public function findById(int $id): User
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            throw new NotFoundException(ErrorCodeEnum::USER_NOT_FOUND);
        }

        return $user;
    }

    /**
     * IDでユーザーをリレーションと共に取得
     *
     * @param  int  $id  ユーザーID
     *
     * @throws NotFoundException ユーザーが見つからない場合
     */
    public function findByIdWithRelations(int $id): User
    {
        $user = $this->userRepository->findByIdWithRelations($id);

        if (! $user) {
            throw new NotFoundException(ErrorCodeEnum::USER_NOT_FOUND);
        }

        return $user;
    }

    /**
     * 会社IDでユーザーを取得
     *
     * @param  int  $companyId  会社ID
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function findByCompanyId(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->userRepository->findByCompanyId($companyId);
    }

    /**
     * シフト表示用に会社IDと対象日付でユーザーを取得
     * 対象日付時点で在籍しているユーザーのみを返す
     *
     * @param  int  $companyId  会社ID
     * @param  string  $targetDate  対象日付（Y-m-d形式）
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function findByCompanyIdForShift(int $companyId, string $targetDate): \Illuminate\Database\Eloquent\Collection
    {
        return $this->userRepository->findByCompanyIdForShift($companyId, $targetDate);
    }

    /**
     * ユーザーを作成
     *
     * @param  array<string, mixed>  $data  ユーザーデータ
     * @return User 作成されたユーザー
     *
     * @throws BusinessException ビジネスルール違反時
     */
    public function create(array $data): User
    {
        try {
            // パスワードの処理
            // パスワードが提供されていない場合はランダムなパスワードを生成
            $plainPassword = null;
            if (empty($data['password'])) {
                $plainPassword = $this->generateRandomPassword();
                $data['password'] = $plainPassword;
            } else {
                $plainPassword = $data['password'];
            }

            // 新規ユーザーは初回ログイン時にパスワード変更を必須にする
            $data['must_change_password'] = true;

            // メールアドレスが設定されているか確認
            $hasEmail = ! empty($data['email']);

            // パスワードをハッシュ化（Laravel Breezeの認証で使用される）
            if (! Hash::needsRehash($data['password'])) {
                // 既にハッシュ化されている場合はそのまま使用
            } else {
                $data['password'] = Hash::make($data['password']);
            }

            // 打刻専用パスワードの初期値
            $stampPassword = self::DEFAULT_STAMP_PASSWORD;
            $data['stamp_password'] = Hash::make($stampPassword);

            return DB::transaction(function () use ($data, $plainPassword, $stampPassword, $hasEmail): User {
                // リレーション・関連データを抽出
                $companyId = collect($data)->get('company_id');

                // 個人コードが未設定の場合は自動採番
                if (empty($data['employee_code']) && $companyId) {
                    $data['employee_code'] = $this->generateEmployeeCode($companyId);
                }
                $departmentId = collect($data)->get('department_id');
                $roleId = collect($data)->get('role_id');
                $roleIds = $roleId ? [$roleId] : [];
                $permissions = collect($data)->get('permissions', []);
                $shiftPatterns = collect($data)->get('shift_patterns', []);

                // デバッグログ
                $this->logInfo('Permissions data', [
                    'permissions' => $permissions,
                    'permissions_count' => count($permissions),
                    'is_empty' => collect($permissions)->isEmpty(),
                ]);

                // Userモデルに保存するデータを抽出（fillableに定義されているフィールドのみ）
                $userData = collect($data)->only([
                    'name',
                    'name_kana',
                    'employee_code',
                    'email',
                    'password',
                    'stamp_password',
                    'must_change_password',
                    'is_retired',
                    'retirement_date',
                ])->toArray();

                // ユーザーを作成
                $user = $this->userRepository->create($userData);

                // 会社を紐付け（is_primary=true で主要な会社として登録）
                if ($companyId) {
                    $this->userRepository->attachCompany($user->id, $companyId, isPrimary: true);
                }

                // 部署を紐付け（is_primary=true で主要な部署として登録）
                if ($departmentId) {
                    $this->userRepository->attachDepartment($user->id, $departmentId, isPrimary: true);
                }

                // ロールを割り当て
                if (collect($roleIds)->isNotEmpty()) {
                    $this->userRepository->syncRoles($user->id, $roleIds);
                }

                // ユーザー個別権限を保存
                if (collect($permissions)->isNotEmpty()) {
                    $this->logInfo('Saving permissions', [
                        'user_id' => $user->id,
                        'permissions' => $permissions,
                    ]);
                    $this->userPermissionRepository->syncPermissions($user->id, $permissions);
                } else {
                    $this->logWarning(ErrorCodeEnum::USER_PERMISSION_SYNC_FAILED, ['reason' => 'No permissions to save']);
                }

                // シフトパターンを保存し、勤務可能曜日を自動導出
                if (collect($shiftPatterns)->isNotEmpty()) {
                    $this->userRepository->syncShiftPatterns($user->id, $shiftPatterns);

                    $availableWorkDays = [];
                    foreach ($shiftPatterns as $day => $patternId) {
                        $availableWorkDays[$day] = ! empty($patternId);
                    }
                    $this->userRepository->syncAvailableWorkDays($user->id, $availableWorkDays);
                }

                // メールアドレスが設定されている場合、ウェルカムメールを送信
                if ($hasEmail && $plainPassword && $companyId) {
                    $company = Company::find($companyId);
                    if ($company) {
                        $loginUrl = config('attendance.staff_login_url');

                        // 管理者・責任者の場合は管理画面ログインURLを含める
                        $roleId = collect($data)->get('role_id');
                        $adminLoginUrl = in_array($roleId, [self::ADMIN_ROLE_ID, self::MANAGER_ROLE_ID], true)
                            ? config('attendance.admin_login_url')
                            : '';

                        $this->mailDispatcher->send(
                            $user->email,
                            new WelcomeUserMail(
                                $user,
                                $plainPassword,
                                $loginUrl,
                                $company->company_code,
                                $stampPassword,
                                $adminLoginUrl
                            ),
                            ['user_id' => $user->id],
                        );

                        $this->logInfo('Welcome email sent', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                        ]);
                    }
                }

                return $user;
            });
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::USER_CREATE_FAILED, [
                'data' => array_merge($data, ['password' => '***']), // パスワードをマスク
            ], $e);

            throw $e;
        }
    }

    /**
     * ユーザー情報を更新
     *
     * @param  int  $id  ユーザーID
     * @param  array<string, mixed>  $data  更新データ
     * @return User 更新されたユーザー
     *
     * @throws NotFoundException ユーザーが見つからない場合
     * @throws BusinessException ビジネスルール違反時
     */
    public function update(int $id, array $data): User
    {
        try {
            // ユーザーが存在するか確認（存在しない場合はNotFoundException）
            $this->findById($id);

            // パスワードの処理
            $password = collect($data)->get('password');
            if (empty($password)) {
                // パスワードが空またはnullの場合は更新しない（配列から削除）
                unset($data['password']);
            } elseif (! Hash::needsRehash($password)) {
                // 既にハッシュ化されている場合はそのまま使用
            } else {
                // パスワードをハッシュ化
                $data['password'] = Hash::make($password);
            }

            // 打刻パスワードの処理
            $stampPassword = collect($data)->get('stamp_password');
            if (empty($stampPassword)) {
                unset($data['stamp_password']);
            } else {
                $data['stamp_password'] = Hash::make($stampPassword);
            }

            return DB::transaction(function () use ($id, $data): User {
                // リレーション・関連データを抽出
                $companyId = collect($data)->get('company_id');
                $departmentId = collect($data)->get('department_id');
                $roleId = collect($data)->get('role_id');
                $roleIds = $roleId !== null ? [$roleId] : null;
                $permissions = collect($data)->get('permissions');
                $shiftPatterns = collect($data)->get('shift_patterns');

                // デバッグログ
                $this->logInfo('Permissions data', [
                    'user_id' => $id,
                    'permissions' => $permissions,
                    'permissions_is_null' => $permissions === null,
                    'permissions_count' => $permissions !== null ? count($permissions) : 0,
                ]);

                // Userモデルに保存するデータを抽出（fillableに定義されているフィールドのみ）
                $userData = collect($data)->only([
                    'name',
                    'name_kana',
                    'employee_code',
                    'email',
                    'password',
                    'stamp_password',
                    'is_stamp_hidden',
                    'is_shift_hidden',
                    'felica_idm',
                    'is_retired',
                    'retirement_date',
                ])->filter(function ($value, $key) {
                    // パスワードが空の場合は除外
                    if (in_array($key, ['password', 'stamp_password'], true) && empty($value)) {
                        return false;
                    }

                    return true;
                })->toArray();

                // FeliCaカードの登録日時は、カードが変わったときだけ更新する
                if (array_key_exists('felica_idm', $userData)) {
                    $current = $this->userRepository->findById($id);

                    if ($userData['felica_idm'] === null) {
                        $userData['felica_registered_at'] = null;
                    } elseif ($current && $userData['felica_idm'] !== $current->felica_idm) {
                        $userData['felica_registered_at'] = CarbonImmutable::now();
                    }
                }

                // ユーザーを更新
                $updatedUser = $this->userRepository->update($id, $userData);

                // 会社を更新（変更がある場合）
                if ($companyId !== null) {
                    // 既存の主要な会社を解除して新しい会社を設定
                    $updatedUser->companies()->syncWithPivotValues([$companyId], ['is_primary' => true]);
                }

                // 部署を更新（変更がある場合）
                if ($departmentId !== null) {
                    // 既存の主要な部署を解除して新しい部署を設定
                    $updatedUser->departments()->syncWithPivotValues([$departmentId], ['is_primary' => true]);
                }

                // ロールを更新
                if ($roleIds !== null) {
                    // オーナーの管理者ロールは変更不可
                    $currentUser = $this->findById($id);
                    if ($currentUser->isOwner() && ! in_array(self::ADMIN_ROLE_ID, $roleIds, true)) {
                        throw new BusinessException('最初に作成されたスタッフの役割は変更できません。');
                    }

                    // 管理者から別の役割に変更する場合、唯一の管理者でないことを確認
                    if ($currentUser->isAdmin() && ! in_array(self::ADMIN_ROLE_ID, $roleIds, true)) {
                        $userCompanyId = $companyId ?? $currentUser->company_id;
                        if ($userCompanyId && $this->userRepository->countAdminsByCompanyId($userCompanyId) <= 1) {
                            throw new BusinessException('会社に管理者が1人しかいないため、役割を変更できません。');
                        }
                    }

                    $this->userRepository->syncRoles($updatedUser->id, $roleIds);
                }

                // ユーザー個別権限を更新
                if ($permissions !== null) {
                    $this->logInfo('Saving permissions', [
                        'user_id' => $updatedUser->id,
                        'permissions' => $permissions,
                    ]);
                    $this->userPermissionRepository->syncPermissions($updatedUser->id, $permissions);
                } else {
                    $this->logInfo('Permissions is null, skipping save', ['user_id' => $updatedUser->id]);
                }

                // シフトパターンを更新し、勤務可能曜日を自動導出
                if ($shiftPatterns !== null) {
                    $this->userRepository->syncShiftPatterns($updatedUser->id, $shiftPatterns);

                    $availableWorkDays = [];
                    foreach ($shiftPatterns as $day => $patternId) {
                        $availableWorkDays[$day] = ! empty($patternId);
                    }
                    $this->userRepository->syncAvailableWorkDays($updatedUser->id, $availableWorkDays);
                }

                return $updatedUser->fresh(['roles']);
            });
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::USER_UPDATE_FAILED, [
                'user_id' => $id,
                'data' => array_key_exists('password', $data) ? array_merge($data, ['password' => '***']) : $data,
            ], $e);

            throw $e;
        }
    }

    /**
     * ユーザーを削除
     *
     * @param  int  $id  ユーザーID
     *
     * @throws NotFoundException ユーザーが見つからない場合
     * @throws BusinessException 削除できない状態の場合
     */
    public function delete(int $id): void
    {
        $user = $this->findById($id);

        // オーナーは削除不可
        if ($user->isOwner()) {
            throw new BusinessException('最初に作成されたスタッフは削除できません。');
        }

        // 管理者が1人のみの場合は削除不可
        if ($this->isLastAdminInCompany($id, $user->company_id)) {
            throw new BusinessException('会社に管理者が1人しかいないため、削除できません。');
        }

        $deleted = $this->userRepository->delete($id);

        if (! $deleted) {
            throw new BusinessException(ErrorCodeEnum::USER_DELETE_FAILED);
        }
    }

    /**
     * ユーザーにロールを割り当て
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $roleId  ロールID
     *
     * @throws NotFoundException ユーザーが見つからない場合
     */
    public function assignRole(int $userId, int $roleId): void
    {
        // ユーザーが存在するか確認
        $this->findById($userId);

        $this->userRepository->attachRole($userId, $roleId);
    }

    /**
     * ユーザーからロールを削除
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $roleId  ロールID
     *
     * @throws NotFoundException ユーザーが見つからない場合
     */
    public function removeRole(int $userId, int $roleId): void
    {
        // ユーザーが存在するか確認
        $this->findById($userId);

        $this->userRepository->detachRole($userId, $roleId);
    }

    /**
     * 初期パスワードを生成
     *
     * @param  int  $length  パスワードの長さ（デフォルト: 12）
     * @return string 生成されたパスワード
     */
    public function generateInitialPassword(int $length = 12): string
    {
        $characters = str_split(self::PASSWORD_CHARACTERS);

        return collect(range(1, $length))
            ->map(fn () => $characters[random_int(0, count($characters) - 1)])
            ->implode('');
    }

    /**
     * ランダムなパスワードを生成
     */
    private function generateRandomPassword(int $length = 16): string
    {
        return $this->generateInitialPassword($length);
    }

    /**
     * 個人コード（employee_code）を自動採番
     *
     * 会社内で連番を採番する（6桁ゼロ埋め）
     * 例: 000001, 000002, ...
     *
     * @param  int  $companyId  会社ID
     * @return string 生成された個人コード
     */
    private function generateEmployeeCode(int $companyId): string
    {
        $maxCode = $this->userRepository->getMaxEmployeeCodeByCompany($companyId);

        if ($maxCode === null) {
            return self::INITIAL_EMPLOYEE_CODE;
        }

        $nextNumber = (int) $maxCode + 1;

        // 6桁に収まる場合はそのまま使用
        if ($nextNumber <= 999999) {
            return str_pad((string) $nextNumber, self::EMPLOYEE_CODE_DIGITS, '0', STR_PAD_LEFT);
        }

        // 桁あふれ: 欠番から最小の空き番号を探す
        $existingCodes = $this->userRepository->getEmployeeCodesByCompany($companyId)
            ->map(fn ($c) => (int) $c)
            ->sort()
            ->values();

        foreach ($existingCodes as $index => $code) {
            if ($code !== $index + 1) {
                return str_pad((string) ($index + 1), self::EMPLOYEE_CODE_DIGITS, '0', STR_PAD_LEFT);
            }
        }

        throw new \RuntimeException('個人コードの空き番号がありません（上限999999件）');
    }

    /**
     * ユーザーの勤務可能曜日を取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, bool> 曜日名をキーとした配列
     */
    public function getAvailableWorkDays(int $userId): array
    {
        return $this->userRepository->getAvailableWorkDays($userId);
    }

    /**
     * ユーザーのシフトパターンを取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, int|null> 曜日名をキーとした配列
     */
    public function getShiftPatterns(int $userId): array
    {
        return $this->userRepository->getShiftPatterns($userId);
    }

    /**
     * ユーザーの権限をフロントエンド用の形式で取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, string> 権限名をキーとした配列
     */
    public function getPermissionsForFrontend(int $userId): array
    {
        // デフォルト値
        $permissions = [
            'shift_view_permission' => 'self',
            'attendance_view_permission' => 'self',
            'approval_permission' => 'department',
            'shift_edit_permission' => 'department',
        ];

        // ユーザー個別権限を取得
        $userPermissions = $this->userPermissionRepository->findByUserId($userId);

        // resource_codeとscope_codeのマッピング
        $resourceMapping = [
            'shift_view' => 'shift_view_permission',
            'attendance_view' => 'attendance_view_permission',
            'request_approval' => 'approval_permission',
            'shift_edit' => 'shift_edit_permission',
        ];

        foreach ($userPermissions as $permission) {
            $resourceCode = $permission->resource->resource_code ?? null;
            $scopeCode = $permission->scope->scope_code ?? null;

            if ($resourceCode && $scopeCode && isset($resourceMapping[$resourceCode])) {
                $permissions[$resourceMapping[$resourceCode]] = $scopeCode;
            }
        }

        return $permissions;
    }

    /**
     * CSVファイルからユーザーを一括インポート
     *
     * @param  UploadedFile  $file  CSVファイル
     * @param  int  $companyId  会社ID
     * @return array{success: int, failed: int, errors: array<int, array<string, mixed>>, auto_assigned: array<int, array{row: int, name: string, employee_code: string}>}
     */
    public function importFromCsv(UploadedFile $file, int $companyId): array
    {
        $result = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'auto_assigned' => [],
        ];

        // CSVファイルを読み込み
        $csv = $this->parseCsvFile($file);

        $this->logInfo('CSV parsed', [
            'row_count' => count($csv),
            'csv_data' => $csv,
        ]);

        if (empty($csv)) {
            $result['errors'][] = ['row' => 0, 'message' => 'CSVファイルが空です。'];

            return $result;
        }

        // ヘッダー行を取得
        $headers = array_shift($csv);
        $expectedHeaders = $this->getCsvHeaders();

        $this->logInfo('Headers', [
            'actual_headers' => $headers,
            'expected_headers' => $expectedHeaders,
            'data_rows_count' => count($csv),
        ]);

        // ヘッダーの検証
        if (! $this->validateCsvHeaders($headers, $expectedHeaders)) {
            $result['errors'][] = ['row' => 1, 'message' => 'CSVのヘッダーが正しくありません。テンプレートを使用してください。'];

            return $result;
        }

        // 各行を処理
        foreach ($csv as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1; // データ行は1から開始

            // 空行をスキップ
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $userData = $this->mapCsvRowToUserData($headers, $row, $companyId);

                $this->logInfo('Row data', [
                    'row_number' => $rowNumber,
                    'raw_row' => $row,
                    'mapped_data' => $userData,
                ]);

                // バリデーション
                $validator = Validator::make($userData, $this->getCsvValidationRules(), $this->getCsvValidationMessages());

                if ($validator->fails()) {
                    $this->logWarning(ErrorCodeEnum::VALIDATION_FAILED, [
                        'row_number' => $rowNumber,
                        'errors' => $validator->errors()->all(),
                    ]);
                    $result['failed']++;
                    $result['errors'][] = [
                        'row' => $rowNumber,
                        'message' => implode(', ', $validator->errors()->all()),
                    ];

                    continue;
                }

                // 個人コードの会社内ユニークチェック（StoreUserRequestと同じロジック）
                // 空欄の場合は create() 内で自動採番されるためスキップ
                if (! empty($userData['employee_code'])
                    && $this->userRepository->existsByEmployeeCodeInCompany($companyId, $userData['employee_code'])
                ) {
                    $result['failed']++;
                    $result['errors'][] = [
                        'row' => $rowNumber,
                        'message' => 'この個人コードは既に使用されています',
                    ];

                    continue;
                }

                // CSV で個人コードが空欄だったかを記憶（create() 後に自動採番結果を報告するため）
                $wasEmployeeCodeEmpty = empty($userData['employee_code']);

                // ユーザー作成
                $createdUser = $this->create($userData);
                $result['success']++;

                if ($wasEmployeeCodeEmpty) {
                    $result['auto_assigned'][] = [
                        'row' => $rowNumber,
                        'name' => $createdUser->name,
                        'employee_code' => $createdUser->employee_code,
                    ];
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ];
                $this->logError(ErrorCodeEnum::USER_CSV_IMPORT_FAILED, [
                    'row' => $rowNumber,
                ], $e);
            }
        }

        return $result;
    }

    /**
     * CSVテンプレートのヘッダーを取得
     *
     * @return array<string>
     */
    public function getCsvHeaders(): array
    {
        return [
            '社員番号',
            '氏名',
            '氏名（カナ）',
            'メールアドレス',
            '部署',
            '役割',
        ];
    }

    /**
     * CSVファイルをパース
     *
     * @param  UploadedFile  $file  CSVファイル
     * @return array<int, array<int, string>>
     */
    private function parseCsvFile(UploadedFile $file): array
    {
        $csv = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return $csv;
        }

        // BOMを除去
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        while (($row = fgetcsv($handle)) !== false) {
            // SJISからUTF-8に変換
            $row = array_map(function ($value) {
                $encoded = mb_detect_encoding($value, ['UTF-8', 'SJIS', 'SJIS-win', 'EUC-JP']);
                if ($encoded && $encoded !== 'UTF-8') {
                    return mb_convert_encoding($value, 'UTF-8', $encoded);
                }

                return $value;
            }, $row);
            $csv[] = $row;
        }

        fclose($handle);

        return $csv;
    }

    /**
     * CSVヘッダーを検証
     *
     * @param  array<string>  $headers  実際のヘッダー
     * @param  array<string>  $expectedHeaders  期待するヘッダー
     */
    private function validateCsvHeaders(array $headers, array $expectedHeaders): bool
    {
        // ヘッダーの数を確認
        if (count($headers) < count($expectedHeaders)) {
            return false;
        }

        // 各ヘッダーを確認
        foreach ($expectedHeaders as $index => $expected) {
            $actual = trim($headers[$index] ?? '');
            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * CSV行をユーザーデータにマッピング
     *
     * @param  array<string>  $headers  ヘッダー
     * @param  array<string>  $row  行データ
     * @param  int  $companyId  会社ID
     * @return array<string, mixed>
     */
    private function mapCsvRowToUserData(array $headers, array $row, int $companyId): array
    {
        $rawEmployeeCode = trim($row[0] ?? '');

        return [
            // 空欄の場合は null にして、UserService::create() 内で自動採番させる
            'employee_code' => $rawEmployeeCode === ''
                ? null
                : str_pad($rawEmployeeCode, self::EMPLOYEE_CODE_DIGITS, '0', STR_PAD_LEFT),
            'name' => trim($row[1] ?? ''),
            'name_kana' => trim($row[2] ?? ''),
            'email' => trim($row[3] ?? '') === '' ? null : trim($row[3] ?? ''),
            'department_id' => ! empty($row[4]) ? (int) trim($row[4]) : null,
            'role_id' => ! empty($row[5]) ? (int) trim($row[5]) : null,
            'company_id' => $companyId,
        ];
    }

    /**
     * CSVインポート用のバリデーションルールを取得
     *
     * @return array<string, array<string>>
     */
    private function getCsvValidationRules(): array
    {
        return [
            // 空欄なら自動採番されるため nullable
            'employee_code' => ['nullable', 'string', 'regex:/^[0-9]{6}$/'],
            'name' => ['required', 'string', 'max:255'],
            'name_kana' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }

    /**
     * CSVインポート用のバリデーションメッセージを取得
     *
     * @return array<string, string>
     */
    private function getCsvValidationMessages(): array
    {
        return [
            'employee_code.regex' => '個人コードは半角数字6桁で入力してください',
            'name.required' => '氏名は必須です',
            'name.max' => '氏名は255文字以内で入力してください',
            'name_kana.max' => '氏名（カナ）は255文字以内で入力してください',
            'email.email' => 'メールアドレスの形式が正しくありません',
            'email.max' => 'メールアドレスは255文字以内で入力してください',
            'email.unique' => 'このメールアドレスは既に使用されています',
            'department_id.integer' => '部署は数値で入力してください',
            'department_id.exists' => '指定された部署は存在しません',
            'role_id.required' => '役割は必須です',
            'role_id.integer' => '役割は数値で入力してください',
            'role_id.exists' => '指定された役割は存在しません（1=管理者, 2=責任者, 3=一般）',
            'company_id.required' => '会社は必須です',
            'company_id.exists' => '指定された会社は存在しません',
        ];
    }

    /**
     * CSVテンプレートの内容を生成
     *
     * @param  array<int, array{id: int, name: string}>  $departments  部署一覧
     */
    public function generateCsvTemplate(array $departments = []): string
    {
        $headers = $this->getCsvHeaders();
        $bom = "\xEF\xBB\xBF"; // UTF-8 BOM

        // ヘッダー行のみ
        $csv = $bom.implode(',', $headers)."\n";

        return $csv;
    }

    /**
     * 管理者によるパスワード初期化
     *
     * パスワードをリセットし、ランダムな初期パスワードを設定する
     *
     * 新しいパスワードを生成し、次回ログイン時にパスワード変更を強制する。
     * メールアドレスが設定されている場合は、新しいパスワードをメールで通知する。
     *
     * @param  int  $userId  ユーザーID
     * @return array{password: string, email_sent: bool} 生成されたパスワードとメール送信状況
     *
     * @throws NotFoundException ユーザーが見つからない場合
     * @throws BusinessException パスワードリセットに失敗した場合
     */
    public function resetPassword(int $userId): array
    {
        try {
            $user = $this->findById($userId);

            // 新しいパスワードを生成
            $newPassword = $this->generateInitialPassword();

            // パスワードを更新し、パスワード変更必須フラグを設定
            $this->userRepository->update($userId, [
                'password' => Hash::make($newPassword),
                'must_change_password' => true,
            ]);

            $emailSent = false;

            // メールアドレスがある場合はメール送信
            if (! empty($user->email)) {
                $company = $user->companies()->first();
                $companyCode = $company?->company_code ?? '';
                $loginUrl = config('attendance.staff_login_url');

                $emailSent = $this->mailDispatcher->send(
                    $user->email,
                    new NewPasswordMail($user, $newPassword, $companyCode, $loginUrl),
                    ['user_id' => $userId],
                );

                $this->logInfo('Password reset email sent', [
                    'user_id' => $userId,
                    'email' => $user->email,
                ]);
            }

            $this->logInfo('Password reset completed', [
                'user_id' => $userId,
                'email_sent' => $emailSent,
            ]);

            return [
                'password' => $newPassword,
                'email_sent' => $emailSent,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::USER_PASSWORD_RESET_FAILED, [
                'user_id' => $userId,
            ], $e);

            throw new BusinessException(ErrorCodeEnum::USER_PASSWORD_RESET_FAILED);
        }
    }
}

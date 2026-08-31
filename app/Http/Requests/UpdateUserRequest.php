<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\LogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ユーザー更新リクエスト
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * リクエストの認可判定
     */
    public function authorize(): bool
    {
        // 管理者権限が必要
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'name_kana' => ['nullable', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:6', 'regex:/^[0-9]{6}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'stamp_password' => ['nullable', 'string', 'min:4'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'shift_patterns' => ['nullable', 'array'],
            'is_stamp_hidden' => ['nullable', 'boolean'],
            // ユーザー個別権限（役割のデフォルト権限を上書きする場合）
            'permissions' => ['nullable', 'array'],
            'permissions.*.resource_id' => ['required', 'integer', 'exists:permission_resources,id'],
            'permissions.*.scope_id' => ['required', 'integer', 'exists:permission_scopes,id'],
            'is_retired' => ['nullable', 'boolean'],
            'retirement_date' => ['nullable', 'date'],
        ];
    }

    /**
     * バリデーション後の追加チェック
     */

    /**
     * バリデーション前にデータを準備
     */
    protected function prepareForValidation(): void
    {
        $logService = app(LogService::class);

        // デバッグログ: リクエストの元データ
        $logService->info('UpdateUserRequest::prepareForValidation', 'Before conversion', [
            'has_shift_view' => $this->has('shift_view_permission'),
            'has_attendance_view' => $this->has('attendance_view_permission'),
            'has_approval' => $this->has('approval_permission'),
            'has_shift_edit' => $this->has('shift_edit_permission'),
            'shift_view_value' => $this->input('shift_view_permission'),
            'attendance_view_value' => $this->input('attendance_view_permission'),
            'approval_value' => $this->input('approval_permission'),
            'shift_edit_value' => $this->input('shift_edit_permission'),
        ]);

        // 権限フィールドをpermissions配列に変換
        $permissions = $this->convertPermissionsToArray();

        // デバッグログ: 変換後のデータ
        $logService->info('UpdateUserRequest::prepareForValidation', 'After conversion', [
            'permissions' => $permissions,
            'permissions_count' => count($permissions),
        ]);

        $this->merge([
            'permissions' => $permissions,
        ]);

        // デバッグログ: merge後の確認
        $logService->info('UpdateUserRequest::prepareForValidation', 'After merge', [
            'has_permissions' => $this->has('permissions'),
            'permissions_value' => $this->input('permissions'),
        ]);
    }

    /**
     * 権限フィールドをpermissions配列形式に変換
     *
     * @return array<int, array<string, int>>
     */
    private function convertPermissionsToArray(): array
    {
        $permissions = [];

        // リソースとスコープのマッピング
        $scopeMap = [
            'self' => 1,
            'department' => 2,
            'company' => 3,
        ];

        // シフト閲覧権限 (resource_id: 1)
        if ($this->has('shift_view_permission')) {
            $scopeCode = $this->input('shift_view_permission');
            if (isset($scopeMap[$scopeCode])) {
                $permissions[] = [
                    'resource_id' => 1,
                    'scope_id' => $scopeMap[$scopeCode],
                ];
            }
        }

        // 勤務実績閲覧権限 (resource_id: 2)
        if ($this->has('attendance_view_permission')) {
            $scopeCode = $this->input('attendance_view_permission');
            if (isset($scopeMap[$scopeCode])) {
                $permissions[] = [
                    'resource_id' => 2,
                    'scope_id' => $scopeMap[$scopeCode],
                ];
            }
        }

        // 申請承認権限 (resource_id: 3)
        if ($this->has('approval_permission')) {
            $scopeCode = $this->input('approval_permission');
            if (isset($scopeMap[$scopeCode])) {
                $permissions[] = [
                    'resource_id' => 3,
                    'scope_id' => $scopeMap[$scopeCode],
                ];
            }
        }

        // シフト編集権限 (resource_id: 4)
        if ($this->has('shift_edit_permission')) {
            $scopeCode = $this->input('shift_edit_permission');
            if (isset($scopeMap[$scopeCode])) {
                $permissions[] = [
                    'resource_id' => 4,
                    'scope_id' => $scopeMap[$scopeCode],
                ];
            }
        }

        return $permissions;
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '氏名は必須です。',
            'name.max' => '氏名は255文字以内で入力してください。',
            'name_kana.max' => 'フリガナは255文字以内で入力してください。',
            'employee_code.max' => '個人コードは6桁で入力してください。',
            'employee_code.regex' => '個人コードは6桁の数字で入力してください。',
            'email.email' => '有効なメールアドレスを入力してください。',
            'email.unique' => 'このメールアドレスは既に使用されています。',
            'password.min' => 'パスワードは8文字以上で入力してください。',
            'stamp_password.min' => '打刻パスワードは4文字以上で入力してください。',
            'role_id.integer' => 'ロールIDは整数で指定してください。',
            'role_id.exists' => '指定されたロールが存在しません。',
            'department_id.integer' => '部署IDは整数で指定してください。',
            'department_id.exists' => '指定された部署が存在しません。',
            'permissions.*.resource_id.required' => 'リソースIDは必須です。',
            'permissions.*.resource_id.integer' => 'リソースIDは整数で指定してください。',
            'permissions.*.resource_id.exists' => '指定されたリソースが存在しません。',
            'permissions.*.scope_id.required' => 'スコープIDは必須です。',
            'permissions.*.scope_id.integer' => 'スコープIDは整数で指定してください。',
            'permissions.*.scope_id.exists' => '指定されたスコープが存在しません。',
            'retirement_date.date' => '退職日は有効な日付を入力してください。',
        ];
    }
}

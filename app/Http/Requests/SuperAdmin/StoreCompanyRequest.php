<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * スーパー管理画面からの事業所作成リクエスト
 */
class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_email' => ['required', 'string', 'email', 'max:191', Rule::unique('users', 'email')],
            'owner_password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * バリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '事業所名を入力してください。',
            'owner_name.required' => '管理者名を入力してください。',
            'owner_email.required' => '管理者のメールアドレスを入力してください。',
            'owner_email.unique' => 'このメールアドレスは既に登録されています。',
            'owner_password.min' => 'パスワードは8文字以上で入力してください。',
        ];
    }
}

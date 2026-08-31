<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * 初回ログイン時のパスワード変更リクエスト
 *
 * 初回ログイン時は現在のパスワード入力は不要
 */
class PasswordChangeRequest extends FormRequest
{
    /**
     * リクエストの認可判定
     */
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
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => '新しいパスワードを入力してください。',
            'password.confirmed' => 'パスワード確認が一致しません。',
            'password.min' => 'パスワードは8文字以上で入力してください。',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100'],
            'name_kana' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'トークンが必要です。',
            'name.required' => '名前を入力してください。',
            'name.max' => '名前は100文字以内で入力してください。',
            'name_kana.max' => 'フリガナは100文字以内で入力してください。',
            'password.required' => 'パスワードを入力してください。',
            'password.confirmed' => 'パスワードが一致しません。',
            'password.min' => 'パスワードは8文字以上で入力してください。',
        ];
    }
}

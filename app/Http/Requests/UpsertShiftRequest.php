<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * シフト一括登録・更新リクエスト
 */
class UpsertShiftRequest extends FormRequest
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
        return [
            'shifts' => ['required', 'array'],
            'shifts.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'shifts.*.shift_date' => ['required', 'date'],
            'shifts.*.shift_pattern_id' => ['nullable', 'integer', 'exists:shift_patterns,id'],
            'shifts.*.note' => ['nullable', 'string', 'max:500'],
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
            'shifts.required' => 'シフトデータは必須です。',
            'shifts.array' => 'シフトデータは配列形式で指定してください。',
            'shifts.*.user_id.required' => 'ユーザーIDは必須です。',
            'shifts.*.user_id.integer' => 'ユーザーIDは整数で指定してください。',
            'shifts.*.user_id.exists' => '指定されたユーザーが存在しません。',
            'shifts.*.shift_date.required' => 'シフト日付は必須です。',
            'shifts.*.shift_date.date' => 'シフト日付は正しい日付形式で指定してください。',
            'shifts.*.shift_pattern_id.integer' => 'シフトパターンIDは整数で指定してください。',
            'shifts.*.shift_pattern_id.exists' => '指定されたシフトパターンが存在しません。',
            'shifts.*.note.string' => '備考は文字列で指定してください。',
            'shifts.*.note.max' => '備考は500文字以内で入力してください。',
        ];
    }
}

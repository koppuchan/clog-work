<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * シフトパターン作成リクエスト
 */
class StoreShiftPatternRequest extends FormRequest
{
    /**
     * リクエストの認可判定
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i'],
            'workMinutes' => ['nullable', 'integer', 'min:0'],
            'breakMode' => ['nullable', 'integer', 'in:1,2'],
            'breakMinutes' => ['nullable', 'integer', 'min:0'],
            'breakStart' => ['nullable', 'date_format:H:i'],
            'breakEnd' => ['nullable', 'date_format:H:i'],
            'autoFillBreak' => ['nullable', 'boolean'],
            'colorId' => ['nullable', 'integer', 'exists:shift_colors,id'],
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
            'name.required' => 'シフトパターン名は必須です。',
            'name.max' => 'シフトパターン名は100文字以内で入力してください。',
            'startTime.date_format' => '開始時刻は HH:MM 形式で入力してください。',
            'endTime.date_format' => '終了時刻は HH:MM 形式で入力してください。',
            'workMinutes.integer' => '勤務時間は数値で入力してください。',
            'workMinutes.min' => '勤務時間は0以上で入力してください。',
            'breakMode.in' => '休憩モードが不正です。',
            'breakMinutes.integer' => '休憩時間は数値で入力してください。',
            'breakMinutes.min' => '休憩時間は0以上で入力してください。',
            'breakStart.date_format' => '休憩開始時刻は HH:MM 形式で入力してください。',
            'breakEnd.date_format' => '休憩終了時刻は HH:MM 形式で入力してください。',
            'colorId.integer' => '色IDは整数で入力してください。',
            'colorId.exists' => '選択した色が存在しません。',
        ];
    }
}

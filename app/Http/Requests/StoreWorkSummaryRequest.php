<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 勤務実績新規作成リクエスト
 */
class StoreWorkSummaryRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'work_start' => ['nullable', 'date_format:H:i'],
            'work_end' => ['nullable', 'date_format:H:i'],
            'break_periods' => ['nullable', 'array'],
            'break_periods.*.start' => ['nullable', 'date_format:H:i', 'required_with:break_periods.*.end'],
            'break_periods.*.end' => ['nullable', 'date_format:H:i', 'required_with:break_periods.*.start'],
        ];
    }

    /**
     * バリデーション後の追加チェック
     */
    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator): void {
                if (! $this->filled('work_start') && ! $this->filled('work_end')) {
                    $validator->errors()->add('work_start', '勤務開始時刻または勤務終了時刻のどちらかは入力してください。');
                }
            },
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
            'user_id.required' => 'ユーザーIDは必須です。',
            'user_id.exists' => '指定されたユーザーが見つかりません。',
            'work_date.required' => '勤務日は必須です。',
            'work_date.date_format' => '勤務日は YYYY-MM-DD 形式で入力してください。',
            'work_start.date_format' => '勤務開始時刻は HH:MM 形式で入力してください。',
            'work_end.date_format' => '勤務終了時刻は HH:MM 形式で入力してください。',
            'break_periods.array' => '休憩時間は配列形式で入力してください。',
            'break_periods.*.start.date_format' => '休憩開始時刻は HH:MM 形式で入力してください。',
            'break_periods.*.start.required_with' => '休憩終了時刻が入力されている場合、開始時刻も入力してください。',
            'break_periods.*.end.date_format' => '休憩終了時刻は HH:MM 形式で入力してください。',
            'break_periods.*.end.required_with' => '休憩開始時刻が入力されている場合、終了時刻も入力してください。',
        ];
    }
}

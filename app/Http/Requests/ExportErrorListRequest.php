<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AttendanceErrorListService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 勤務実績エラーリスト出力リクエスト
 */
class ExportErrorListRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lists' => ['required', 'array', 'min:1'],
            'lists.*' => ['string', Rule::in([
                AttendanceErrorListService::LIST_NO_BREAK,
                AttendanceErrorListService::LIST_MISSING_STAMP,
            ])],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'scope' => ['nullable', Rule::in(['single', 'all'])],
            'user_id' => ['nullable', 'integer'],
            'batch' => ['nullable', 'integer', 'min:1'],
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
            'lists.required' => '出力するエラーリストを選択してください。',
            'lists.min' => '出力するエラーリストを選択してください。',
            'lists.*.in' => '指定されたエラーリストは出力できません。',
            'start_date.required' => '開始日は必須です。',
            'start_date.date_format' => '開始日は YYYY-MM-DD 形式で入力してください。',
            'end_date.required' => '終了日は必須です。',
            'end_date.date_format' => '終了日は YYYY-MM-DD 形式で入力してください。',
            'end_date.after_or_equal' => '終了日は開始日以降の日付を指定してください。',
            'scope.in' => '出力対象の指定が正しくありません。',
        ];
    }
}

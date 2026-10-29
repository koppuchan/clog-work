<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * シフト表の印刷・Excel出力リクエスト
 */
class ShiftTableRequest extends FormRequest
{
    /**
     * 一度に出力できる期間の上限（日）。シフト管理画面の表示期間（1か月前後）より十分広い
     */
    private const MAX_DAYS = 93;

    /**
     * リクエストの認可判定
     *
     * 管理画面のルート（管理者・責任者のみ）で守られている。出力できるスタッフの範囲は、
     * シフト管理画面と同じ閲覧権限（スコープ）で、コントローラーが絞り込む。
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('company_id', $this->user()?->company_id),
            ],
            // 全部署のとき、部署ごとにページを分けるか
            'page_break' => ['nullable', 'boolean'],
        ];
    }

    /**
     * バリデーション後の追加チェック
     */
    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $days = \Carbon\CarbonImmutable::parse($this->input('start_date'))
                    ->diffInDays(\Carbon\CarbonImmutable::parse($this->input('end_date')));

                if ($days >= self::MAX_DAYS) {
                    $validator->errors()->add('end_date', '出力できる期間は'.self::MAX_DAYS.'日以内です。');
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
            'start_date.required' => '開始日は必須です。',
            'start_date.date_format' => '開始日は YYYY-MM-DD 形式で入力してください。',
            'end_date.required' => '終了日は必須です。',
            'end_date.date_format' => '終了日は YYYY-MM-DD 形式で入力してください。',
            'end_date.after_or_equal' => '終了日は開始日以降の日付を指定してください。',
            'department_id.integer' => '部署の指定が正しくありません。',
            'department_id.exists' => '指定された部署が見つかりません。',
            'page_break.boolean' => '改ページの指定が正しくありません。',
        ];
    }
}

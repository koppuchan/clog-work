<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * スタッフ申請作成リクエスト
 */
class StoreRequestRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを行う権限があるかどうか
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'target_date' => ['required', 'date'],
            'type' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'break_start_time' => ['nullable', 'date_format:H:i'],
            'break_end_time' => ['nullable', 'date_format:H:i'],
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
            'target_date.required' => '対象日は必須です',
            'target_date.date' => '対象日は有効な日付を入力してください',
            'type.required' => '申請タイプは必須です',
            'type.string' => '申請タイプは文字列で入力してください',
            'reason.required' => '申請理由は必須です',
            'reason.string' => '申請理由は文字列で入力してください',
            'reason.max' => '申請理由は500文字以内で入力してください',
            'start_time.date_format' => '開始時刻はHH:mm形式で入力してください',
            'end_time.date_format' => '終了時刻はHH:mm形式で入力してください',
            'break_start_time.date_format' => '休憩開始時刻はHH:mm形式で入力してください',
            'break_end_time.date_format' => '休憩終了時刻はHH:mm形式で入力してください',
        ];
    }

    /**
     * バリデーション属性名
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target_date' => '対象日',
            'type' => '申請タイプ',
            'reason' => '申請理由',
            'start_time' => '開始時刻',
            'end_time' => '終了時刻',
            'break_start_time' => '休憩開始時刻',
            'break_end_time' => '休憩終了時刻',
        ];
    }

    /**
     * 時間有給が1時間単位（60分の倍数）になっているか確認する
     *
     * 画面側（ApplicationDialog）でも同じ判定をしているが、APIを直接
     * 呼ばれた場合の保護として、サーバー側でも同じ基準で弾く。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== 'hourly-leave') {
                return;
            }

            $startTime = $this->input('start_time');
            $endTime = $this->input('end_time');

            if (! $startTime || ! $endTime) {
                return;
            }

            [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
            [$endHour, $endMinute] = array_map('intval', explode(':', $endTime));
            $durationMinutes = ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute);

            if ($durationMinutes <= 0 || $durationMinutes % 60 !== 0) {
                $validator->errors()->add('end_time', '時間有給は１時間単位で請求してください。');
            }
        });
    }
}

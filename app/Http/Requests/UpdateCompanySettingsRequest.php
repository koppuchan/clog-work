<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 会社設定更新リクエスト
 */
class UpdateCompanySettingsRequest extends FormRequest
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
            // 会社基本設定
            'companyName' => ['required', 'string', 'max:100'],
            'weekendDays' => ['nullable', 'array'],
            'weekendDays.*' => ['boolean'],
            'includeNationalHolidays' => ['boolean'],

            // 打刻画面非表示設定
            'isStampHidden' => ['boolean'],

            // 給与締め日
            'payrollClosingDay' => ['nullable', 'string', 'max:10'],

            // シフト表示期間
            'shiftDisplayPeriod' => ['nullable', 'string', 'in:monthly,closing_day_based'],

            // 打刻丸め設定
            'clockRounding' => ['nullable', 'string', 'in:none,5min,10min,15min,30min'],

            // 基本シフトパターン
            'defaultShiftPatternId' => ['nullable', 'integer', 'exists:shift_patterns,id'],

            // 有給休暇設定
            'paidLeaveHalfDay' => ['boolean'],
            'paidLeaveHourly' => ['boolean'],
            'dailyWorkingHours' => ['nullable', 'numeric', 'min:0', 'max:24'],

            // 労務アラート設定
            'alertOvertimeNotification' => ['nullable', 'integer', 'min:0'],
            'alertOvertimeLimit' => ['nullable', 'integer', 'min:0'],
            'alertMessages' => ['nullable', 'array'],
            'alertMessages.warningTitle' => ['nullable', 'string', 'max:255'],
            'alertMessages.warningMessage' => ['nullable', 'string', 'max:1000'],
            'alertMessages.cautionTitle' => ['nullable', 'string', 'max:255'],
            'alertMessages.cautionMessage' => ['nullable', 'string', 'max:1000'],

            // 部署設定（一括更新）
            'departments' => ['nullable', 'array'],
            'departments.*.id' => ['nullable', 'integer'],
            'departments.*.name' => ['required', 'string', 'max:100'],
            'deletedDepartmentIds' => ['nullable', 'array'],
            'deletedDepartmentIds.*' => ['integer'],

            // シフトパターン設定（一括更新）
            'shiftPatterns' => ['nullable', 'array'],
            'shiftPatterns.*.id' => ['nullable', 'integer'],
            'shiftPatterns.*.name' => ['required', 'string', 'max:100'],
            'shiftPatterns.*.startTime' => ['nullable', 'date_format:H:i'],
            'shiftPatterns.*.endTime' => ['nullable', 'date_format:H:i'],
            'shiftPatterns.*.workMinutes' => ['nullable', 'integer', 'min:0'],
            'shiftPatterns.*.breakMode' => ['nullable', 'integer', 'in:1,2'],
            'shiftPatterns.*.breakMinutes' => ['nullable', 'integer', 'min:0'],
            'shiftPatterns.*.breakStart' => ['nullable', 'date_format:H:i'],
            'shiftPatterns.*.breakEnd' => ['nullable', 'date_format:H:i'],
            'shiftPatterns.*.autoFillBreak' => ['nullable', 'boolean'],
            'deletedShiftPatternIds' => ['nullable', 'array'],
            'deletedShiftPatternIds.*' => ['integer'],
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
            'companyName.required' => '会社名は必須です。',
            'companyName.max' => '会社名は100文字以内で入力してください。',
            'dailyWorkingHours.numeric' => '所定労働時間は数値で入力してください。',
            'dailyWorkingHours.min' => '所定労働時間は0以上で入力してください。',
            'dailyWorkingHours.max' => '所定労働時間は24時間以内で入力してください。',
            'alertOvertimeNotification.integer' => '残業時間アラートは整数で入力してください。',
            'alertOvertimeNotification.min' => '残業時間アラートは0以上で入力してください。',
            'alertOvertimeLimit.integer' => '残業時間上限は整数で入力してください。',
            'alertOvertimeLimit.min' => '残業時間上限は0以上で入力してください。',
            'departments.*.name.required' => '部署名は必須です。',
            'departments.*.name.max' => '部署名は100文字以内で入力してください。',
            'shiftPatterns.*.name.required' => 'シフトパターン名は必須です。',
            'shiftPatterns.*.name.max' => 'シフトパターン名は100文字以内で入力してください。',
            'shiftPatterns.*.startTime.date_format' => '開始時刻は HH:MM 形式で入力してください。',
            'shiftPatterns.*.endTime.date_format' => '終了時刻は HH:MM 形式で入力してください。',
        ];
    }
}

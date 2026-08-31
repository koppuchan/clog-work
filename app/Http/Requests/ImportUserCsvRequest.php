<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ユーザーCSVインポートリクエスト
 */
class ImportUserCsvRequest extends FormRequest
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
            'csv_file' => ['required', 'file', 'max:5120'], // 5MB制限
        ];
    }

    /**
     * カスタム属性名
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'csv_file' => 'CSVファイル',
        ];
    }

    /**
     * カスタムエラーメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'CSVファイルを選択してください。',
            'csv_file.file' => '有効なファイルをアップロードしてください。',
            'csv_file.mimes' => 'CSV形式のファイルをアップロードしてください。',
            'csv_file.max' => 'ファイルサイズは5MB以下にしてください。',
        ];
    }
}

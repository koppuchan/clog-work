<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * エラーコードEnum
 *
 * アプリケーション全体で使用するエラーコードを定義
 * ログ出力形式: 【E1001】エラー内容
 */
enum ErrorCodeEnum: int
{
    // === AUTH (1000-1099) ===
    case AUTH_INVALID_CREDENTIALS = 1001;
    case AUTH_SESSION_EXPIRED = 1002;
    case AUTH_UNAUTHORIZED_ACCESS = 1003;
    case AUTH_COMPANY_CODE_INVALID = 1004;
    case AUTH_TOKEN_INVALID = 1005;
    case AUTH_TOKEN_EXPIRED = 1006;

    // === USER (1100-1199) ===
    case USER_NOT_FOUND = 1101;
    case USER_ALREADY_EXISTS = 1102;
    case USER_CREATE_FAILED = 1103;
    case USER_UPDATE_FAILED = 1104;
    case USER_DELETE_FAILED = 1105;
    case USER_CSV_IMPORT_FAILED = 1106;
    case USER_PASSWORD_RESET_FAILED = 1107;
    case USER_EMAIL_SEND_FAILED = 1108;
    case USER_PERMISSION_SYNC_FAILED = 1109;

    // === ATTENDANCE (2000-2099) ===
    case ATTENDANCE_NOT_FOUND = 2001;
    case ATTENDANCE_ALREADY_CLOCKED_IN = 2002;
    case ATTENDANCE_NOT_CLOCKED_IN = 2003;
    case ATTENDANCE_ON_BREAK = 2004;
    case ATTENDANCE_NOT_ON_BREAK = 2005;
    case ATTENDANCE_RECORD_FAILED = 2006;
    case ATTENDANCE_UPDATE_FAILED = 2007;
    case ATTENDANCE_DELETE_FAILED = 2008;
    case ATTENDANCE_INVALID_TIME = 2009;
    case ATTENDANCE_CANNOT_EDIT_REQUEST = 2010;
    case ATTENDANCE_BATCH_AGGREGATE_FAILED = 2011;

    // === REQUEST (2100-2199) ===
    case REQUEST_NOT_FOUND = 2101;
    case REQUEST_ALREADY_PROCESSED = 2102;
    case REQUEST_CREATE_FAILED = 2103;
    case REQUEST_APPROVE_FAILED = 2104;
    case REQUEST_REJECT_FAILED = 2105;
    case REQUEST_CANCEL_FAILED = 2106;
    case REQUEST_INVALID_STATUS = 2107;

    // === SHIFT (3000-3099) ===
    case SHIFT_NOT_FOUND = 3001;
    case SHIFT_CONFLICT = 3002;
    case SHIFT_CREATE_FAILED = 3003;
    case SHIFT_UPDATE_FAILED = 3004;
    case SHIFT_DELETE_FAILED = 3005;
    case SHIFT_BULK_UPSERT_FAILED = 3006;

    // === COMPANY (4000-4099) ===
    case COMPANY_NOT_FOUND = 4001;
    case COMPANY_CODE_INVALID = 4002;
    case COMPANY_CREATE_FAILED = 4003;

    // === DEPARTMENT (4100-4199) ===
    case DEPARTMENT_NOT_FOUND = 4101;
    case DEPARTMENT_CREATE_FAILED = 4102;

    // === REGISTRATION (4200-4299) ===
    case REGISTRATION_EMAIL_ALREADY_EXISTS = 4201;
    case REGISTRATION_TOKEN_INVALID = 4202;
    case REGISTRATION_COMPLETE_FAILED = 4203;

    // === SYSTEM (9000-9099) ===
    case SYSTEM_UNEXPECTED_ERROR = 9001;
    case SYSTEM_DATABASE_ERROR = 9002;
    case SYSTEM_TRANSACTION_FAILED = 9003;
    case SYSTEM_CONFIGURATION_ERROR = 9004;

    // === VALIDATION (9100-9199) ===
    case VALIDATION_FAILED = 9101;
    case VALIDATION_INVALID_FORMAT = 9102;

    // === EXTERNAL (9200-9299) ===
    case EXTERNAL_MAIL_FAILED = 9201;
    case EXTERNAL_API_FAILED = 9202;

    /**
     * 日本語ラベル（ユーザー向けメッセージ）を取得
     */
    public function label(): string
    {
        return match ($this) {
            // AUTH
            self::AUTH_INVALID_CREDENTIALS => '認証情報が正しくありません。',
            self::AUTH_SESSION_EXPIRED => 'セッションが期限切れです。',
            self::AUTH_UNAUTHORIZED_ACCESS => '権限がありません。',
            self::AUTH_COMPANY_CODE_INVALID => '会社コードが無効です。',
            self::AUTH_TOKEN_INVALID => '無効なトークンです。',
            self::AUTH_TOKEN_EXPIRED => 'トークンの有効期限が切れています。',

            // USER
            self::USER_NOT_FOUND => 'スタッフが見つかりません。',
            self::USER_ALREADY_EXISTS => 'スタッフは既に存在します。',
            self::USER_CREATE_FAILED => 'スタッフの作成に失敗しました。',
            self::USER_UPDATE_FAILED => 'スタッフの更新に失敗しました。',
            self::USER_DELETE_FAILED => 'スタッフの削除に失敗しました。',
            self::USER_CSV_IMPORT_FAILED => 'CSVインポートに失敗しました。',
            self::USER_PASSWORD_RESET_FAILED => 'パスワードの初期化に失敗しました。',
            self::USER_EMAIL_SEND_FAILED => 'メールの送信に失敗しました。',
            self::USER_PERMISSION_SYNC_FAILED => '権限の同期に失敗しました。',

            // ATTENDANCE
            self::ATTENDANCE_NOT_FOUND => '勤務実績が見つかりません。',
            self::ATTENDANCE_ALREADY_CLOCKED_IN => '既に勤務中です。',
            self::ATTENDANCE_NOT_CLOCKED_IN => '勤務開始が記録されていません。',
            self::ATTENDANCE_ON_BREAK => '休憩中です。',
            self::ATTENDANCE_NOT_ON_BREAK => '休憩開始が記録されていません。',
            self::ATTENDANCE_RECORD_FAILED => '打刻の記録に失敗しました。',
            self::ATTENDANCE_UPDATE_FAILED => '勤務実績の更新に失敗しました。',
            self::ATTENDANCE_DELETE_FAILED => '勤務実績の削除に失敗しました。',
            self::ATTENDANCE_INVALID_TIME => '打刻時刻が無効です。',
            self::ATTENDANCE_CANNOT_EDIT_REQUEST => '申請修正による記録は編集できません。',
            self::ATTENDANCE_BATCH_AGGREGATE_FAILED => '日次勤務実績の集計に失敗しました。',

            // REQUEST
            self::REQUEST_NOT_FOUND => '申請が見つかりません。',
            self::REQUEST_ALREADY_PROCESSED => 'この申請は既に処理済みです。',
            self::REQUEST_CREATE_FAILED => '申請の作成に失敗しました。',
            self::REQUEST_APPROVE_FAILED => '申請の承認に失敗しました。',
            self::REQUEST_REJECT_FAILED => '申請の却下に失敗しました。',
            self::REQUEST_CANCEL_FAILED => '申請の取消に失敗しました。',
            self::REQUEST_INVALID_STATUS => '申請のステータスが無効です。',

            // SHIFT
            self::SHIFT_NOT_FOUND => 'シフトが見つかりません。',
            self::SHIFT_CONFLICT => 'シフトが重複しています。',
            self::SHIFT_CREATE_FAILED => 'シフトの作成に失敗しました。',
            self::SHIFT_UPDATE_FAILED => 'シフトの更新に失敗しました。',
            self::SHIFT_DELETE_FAILED => 'シフトの削除に失敗しました。',
            self::SHIFT_BULK_UPSERT_FAILED => 'シフトの一括登録に失敗しました。',

            // COMPANY
            self::COMPANY_NOT_FOUND => '会社が見つかりません。',
            self::COMPANY_CODE_INVALID => '会社コードが無効です。',
            self::COMPANY_CREATE_FAILED => '会社の作成に失敗しました。',

            // DEPARTMENT
            self::DEPARTMENT_NOT_FOUND => '部署が見つかりません。',
            self::DEPARTMENT_CREATE_FAILED => '部署の作成に失敗しました。',

            // REGISTRATION
            self::REGISTRATION_EMAIL_ALREADY_EXISTS => 'このメールアドレスは既に登録されています。',
            self::REGISTRATION_TOKEN_INVALID => '無効または期限切れのトークンです。',
            self::REGISTRATION_COMPLETE_FAILED => '登録の完了に失敗しました。',

            // SYSTEM
            self::SYSTEM_UNEXPECTED_ERROR => '予期しないエラーが発生しました。',
            self::SYSTEM_DATABASE_ERROR => 'データベースエラーが発生しました。',
            self::SYSTEM_TRANSACTION_FAILED => 'トランザクションに失敗しました。',
            self::SYSTEM_CONFIGURATION_ERROR => '設定エラーが発生しました。',

            // VALIDATION
            self::VALIDATION_FAILED => '入力内容に誤りがあります。',
            self::VALIDATION_INVALID_FORMAT => '入力形式が正しくありません。',

            // EXTERNAL
            self::EXTERNAL_MAIL_FAILED => 'メール送信に失敗しました。',
            self::EXTERNAL_API_FAILED => '外部サービスとの通信に失敗しました。',
        };
    }

    /**
     * エラーコード文字列を取得（ログ出力用）
     * 例: "E1001"
     */
    public function code(): string
    {
        return 'E'.$this->value;
    }

    /**
     * フォーマット済みメッセージを取得
     * 例: "【E1001】認証情報が正しくありません。"
     */
    public function formatted(): string
    {
        return "【{$this->code()}】{$this->label()}";
    }

    /**
     * カスタムメッセージでフォーマット
     * 例: "【E1001】カスタムメッセージ"
     */
    public function withMessage(string $message): string
    {
        return "【{$this->code()}】{$message}";
    }

    /**
     * カテゴリ名を取得
     */
    public function category(): string
    {
        return match (true) {
            $this->value >= 9200 => 'EXTERNAL',
            $this->value >= 9100 => 'VALIDATION',
            $this->value >= 9000 => 'SYSTEM',
            $this->value >= 4200 => 'REGISTRATION',
            $this->value >= 4100 => 'DEPARTMENT',
            $this->value >= 4000 => 'COMPANY',
            $this->value >= 3000 => 'SHIFT',
            $this->value >= 2100 => 'REQUEST',
            $this->value >= 2000 => 'ATTENDANCE',
            $this->value >= 1100 => 'USER',
            $this->value >= 1000 => 'AUTH',
            default => 'UNKNOWN',
        };
    }

    /**
     * HTTPステータスコードを取得
     */
    public function httpStatus(): int
    {
        return match ($this) {
            // 401 Unauthorized
            self::AUTH_INVALID_CREDENTIALS,
            self::AUTH_SESSION_EXPIRED,
            self::AUTH_TOKEN_INVALID,
            self::AUTH_TOKEN_EXPIRED => 401,

            // 403 Forbidden
            self::AUTH_UNAUTHORIZED_ACCESS => 403,

            // 404 Not Found
            self::USER_NOT_FOUND,
            self::ATTENDANCE_NOT_FOUND,
            self::REQUEST_NOT_FOUND,
            self::SHIFT_NOT_FOUND,
            self::COMPANY_NOT_FOUND,
            self::DEPARTMENT_NOT_FOUND => 404,

            // 409 Conflict
            self::USER_ALREADY_EXISTS,
            self::ATTENDANCE_ALREADY_CLOCKED_IN,
            self::REQUEST_ALREADY_PROCESSED,
            self::SHIFT_CONFLICT,
            self::REGISTRATION_EMAIL_ALREADY_EXISTS => 409,

            // 422 Unprocessable Entity
            self::VALIDATION_FAILED,
            self::VALIDATION_INVALID_FORMAT,
            self::ATTENDANCE_INVALID_TIME,
            self::AUTH_COMPANY_CODE_INVALID,
            self::COMPANY_CODE_INVALID => 422,

            // 500 Internal Server Error
            self::SYSTEM_UNEXPECTED_ERROR,
            self::SYSTEM_DATABASE_ERROR,
            self::SYSTEM_TRANSACTION_FAILED,
            self::SYSTEM_CONFIGURATION_ERROR => 500,

            // 502 Bad Gateway
            self::EXTERNAL_MAIL_FAILED,
            self::EXTERNAL_API_FAILED => 502,

            // 400 Bad Request (default)
            default => 400,
        };
    }
}

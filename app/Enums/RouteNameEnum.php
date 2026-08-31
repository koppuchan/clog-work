<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ルート名Enum
 *
 * ルート名と日本語ラベルのマッピングを定義
 * ログ出力形式: 【開始】シフト一覧取得 / 【終了】シフト一覧取得
 */
enum RouteNameEnum: string
{
    // === 公開打刻 ===
    case PUBLIC_STAMP_INDEX = 'public.stamp.index';
    case PUBLIC_STAMP_STATUS = 'public.stamp.status';
    case PUBLIC_STAMP_CLOCK_IN = 'public.stamp.clock-in';
    case PUBLIC_STAMP_CLOCK_OUT = 'public.stamp.clock-out';
    case PUBLIC_STAMP_BREAK_START = 'public.stamp.break-start';
    case PUBLIC_STAMP_BREAK_END = 'public.stamp.break-end';

    // === Admin - シフト管理 ===
    case ADMIN_SHIFTS = 'admin.shifts';
    case ADMIN_SHIFTS_UPSERT = 'admin.shifts.upsert';
    case ADMIN_SHIFTS_DESTROY = 'admin.shifts.destroy';
    case ADMIN_SHIFTS_USER_INFO = 'admin.shifts.user-info';
    case ADMIN_SHIFTS_BULK_USER_INFO = 'admin.shifts.bulk-user-info';

    // === Admin - ユーザー管理 ===
    case ADMIN_USERS = 'admin.users';
    case ADMIN_USERS_NEW = 'admin.users.new';
    case ADMIN_USERS_CONFIRM = 'admin.users.confirm';
    case ADMIN_USERS_STORE = 'admin.users.store';
    case ADMIN_USERS_CSV_TEMPLATE = 'admin.users.csv-template';
    case ADMIN_USERS_CSV_IMPORT_SHOW = 'admin.users.csv-import.show';
    case ADMIN_USERS_CSV_IMPORT = 'admin.users.csv-import';
    case ADMIN_USERS_EDIT = 'admin.users.edit';
    case ADMIN_USERS_CONFIRM_UPDATE = 'admin.users.confirm-update';
    case ADMIN_USERS_UPDATE = 'admin.users.update';
    case ADMIN_USERS_DESTROY = 'admin.users.destroy';
    case ADMIN_USERS_RESET_PASSWORD = 'admin.users.reset-password';

    // === Admin - 申請管理 ===
    case ADMIN_APPLICATIONS = 'admin.applications';
    case ADMIN_APPLICATIONS_SHOW = 'admin.applications.show';
    case ADMIN_APPLICATIONS_APPROVE = 'admin.applications.approve';
    case ADMIN_APPLICATIONS_REJECT = 'admin.applications.reject';

    // === Admin - その他 ===
    case ADMIN_ALERTS = 'admin.alerts';
    case ADMIN_REPORTS = 'admin.reports';
    case ADMIN_REPORTS_EXPORT_CSV = 'admin.reports.export.csv';
    case ADMIN_BILLING = 'admin.billing';
    case ADMIN_SETTINGS = 'admin.settings';
    case ADMIN_SETTINGS_UPDATE = 'admin.settings.update';

    // === Admin - 部署管理 ===
    case ADMIN_SETTINGS_DEPARTMENTS_STORE = 'admin.settings.departments.store';
    case ADMIN_SETTINGS_DEPARTMENTS_UPDATE = 'admin.settings.departments.update';
    case ADMIN_SETTINGS_DEPARTMENTS_DESTROY = 'admin.settings.departments.destroy';

    // === Admin - シフトパターン管理 ===
    case ADMIN_SETTINGS_SHIFT_PATTERNS_STORE = 'admin.settings.shiftPatterns.store';
    case ADMIN_SETTINGS_SHIFT_PATTERNS_UPDATE = 'admin.settings.shiftPatterns.update';
    case ADMIN_SETTINGS_SHIFT_PATTERNS_DESTROY = 'admin.settings.shiftPatterns.destroy';

    // === Staff ===
    case STAFF_INDEX = 'staff.index';
    case STAFF_PASSWORD_CHANGE = 'staff.password-change';
    case STAFF_PASSWORD_CHANGE_STORE = 'staff.password-change.store';
    case STAFF_SHIFTS = 'staff.shifts';
    case STAFF_STAMP = 'staff.stamp';
    case STAFF_STAMP_CLOCK_IN = 'staff.stamp.clock-in';
    case STAFF_STAMP_CLOCK_OUT = 'staff.stamp.clock-out';
    case STAFF_STAMP_BREAK_START = 'staff.stamp.break-start';
    case STAFF_STAMP_BREAK_END = 'staff.stamp.break-end';
    case STAFF_STAMP_STATUS = 'staff.stamp.status';
    case STAFF_REPORTS = 'staff.reports';
    case STAFF_REQUESTS_STORE = 'staff.requests.store';

    // === Auth ===
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case REGISTER = 'register';
    case ADMIN_LOGIN = 'admin.login';
    case STAFF_LOGIN = 'staff.login';

    /**
     * 日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            // 公開打刻
            self::PUBLIC_STAMP_INDEX => '公開打刻画面表示',
            self::PUBLIC_STAMP_STATUS => '公開打刻状態取得',
            self::PUBLIC_STAMP_CLOCK_IN => '公開勤務開始打刻',
            self::PUBLIC_STAMP_CLOCK_OUT => '公開勤務終了打刻',
            self::PUBLIC_STAMP_BREAK_START => '公開休憩開始打刻',
            self::PUBLIC_STAMP_BREAK_END => '公開休憩終了打刻',

            // Admin - シフト
            self::ADMIN_SHIFTS => 'シフト一覧取得',
            self::ADMIN_SHIFTS_UPSERT => 'シフト一括登録',
            self::ADMIN_SHIFTS_DESTROY => 'シフト削除',
            self::ADMIN_SHIFTS_USER_INFO => 'ユーザーシフト情報取得',
            self::ADMIN_SHIFTS_BULK_USER_INFO => 'ユーザーシフト一括情報取得',

            // Admin - ユーザー
            self::ADMIN_USERS => 'ユーザー一覧取得',
            self::ADMIN_USERS_NEW => 'ユーザー新規作成画面',
            self::ADMIN_USERS_CONFIRM => 'ユーザー作成確認',
            self::ADMIN_USERS_STORE => 'ユーザー作成',
            self::ADMIN_USERS_CSV_TEMPLATE => 'CSVテンプレートダウンロード',
            self::ADMIN_USERS_CSV_IMPORT_SHOW => 'CSVインポート画面',
            self::ADMIN_USERS_CSV_IMPORT => 'CSVインポート実行',
            self::ADMIN_USERS_EDIT => 'ユーザー編集画面',
            self::ADMIN_USERS_CONFIRM_UPDATE => 'ユーザー更新確認',
            self::ADMIN_USERS_UPDATE => 'ユーザー更新',
            self::ADMIN_USERS_DESTROY => 'ユーザー削除',
            self::ADMIN_USERS_RESET_PASSWORD => 'パスワード初期化',

            // Admin - 申請
            self::ADMIN_APPLICATIONS => '申請一覧取得',
            self::ADMIN_APPLICATIONS_SHOW => '申請詳細取得',
            self::ADMIN_APPLICATIONS_APPROVE => '申請承認',
            self::ADMIN_APPLICATIONS_REJECT => '申請却下',

            // Admin - その他
            self::ADMIN_ALERTS => 'アラート一覧取得',
            self::ADMIN_REPORTS => '勤務実績レポート取得',
            self::ADMIN_REPORTS_EXPORT_CSV => '勤務実績CSVエクスポート',
            self::ADMIN_BILLING => '請求画面表示',
            self::ADMIN_SETTINGS => '設定画面取得',
            self::ADMIN_SETTINGS_UPDATE => '設定更新',

            // Admin - 部署
            self::ADMIN_SETTINGS_DEPARTMENTS_STORE => '部署作成',
            self::ADMIN_SETTINGS_DEPARTMENTS_UPDATE => '部署更新',
            self::ADMIN_SETTINGS_DEPARTMENTS_DESTROY => '部署削除',

            // Admin - シフトパターン
            self::ADMIN_SETTINGS_SHIFT_PATTERNS_STORE => 'シフトパターン作成',
            self::ADMIN_SETTINGS_SHIFT_PATTERNS_UPDATE => 'シフトパターン更新',
            self::ADMIN_SETTINGS_SHIFT_PATTERNS_DESTROY => 'シフトパターン削除',

            // Staff
            self::STAFF_INDEX => 'スタッフホーム',
            self::STAFF_PASSWORD_CHANGE => 'パスワード変更画面',
            self::STAFF_PASSWORD_CHANGE_STORE => 'パスワード変更',
            self::STAFF_SHIFTS => 'シフト閲覧',
            self::STAFF_STAMP => '打刻画面表示',
            self::STAFF_STAMP_CLOCK_IN => '勤務開始打刻',
            self::STAFF_STAMP_CLOCK_OUT => '勤務終了打刻',
            self::STAFF_STAMP_BREAK_START => '休憩開始打刻',
            self::STAFF_STAMP_BREAK_END => '休憩終了打刻',
            self::STAFF_STAMP_STATUS => '打刻状態取得',
            self::STAFF_REPORTS => '勤務実績閲覧',
            self::STAFF_REQUESTS_STORE => '申請作成',

            // Auth
            self::LOGIN => 'ログイン',
            self::LOGOUT => 'ログアウト',
            self::REGISTER => '新規登録',
            self::ADMIN_LOGIN => '管理者ログイン',
            self::STAFF_LOGIN => 'スタッフログイン',
        };
    }

    /**
     * ルート名からEnumを取得
     */
    public static function fromRouteName(?string $routeName): ?self
    {
        if ($routeName === null) {
            return null;
        }

        return self::tryFrom($routeName);
    }

    /**
     * ルート名から日本語ラベルを取得
     * 定義がない場合はルート名をそのまま返す
     */
    public static function getLabelForRoute(?string $routeName): string
    {
        if ($routeName === null) {
            return 'Unknown';
        }

        $enum = self::tryFrom($routeName);

        return $enum?->label() ?? $routeName;
    }
}

-- ============================================
-- 勤怠管理システム テーブル定義（最適化版）
-- 正規化、パフォーマンス、データ型最適化を考慮
-- ============================================

-- ============================================
-- マスターテーブル群
-- ============================================

-- 役割マスター
CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '1=管理者, 2=責任者, 3=一般',
    name VARCHAR(50) NOT NULL COMMENT '役割名',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id, name, created_at, updated_at) VALUES
(1, '管理者', NOW(), NOW()),
(2, '責任者', NOW(), NOW()),
(3, '一般', NOW(), NOW());

-- 曜日マスター
CREATE TABLE weekdays (
    id TINYINT UNSIGNED PRIMARY KEY COMMENT '1=月,2=火,3=水,4=木,5=金,6=土,7=日',
    name VARCHAR(10) NOT NULL COMMENT '曜日名',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO weekdays (id, name, created_at, updated_at) VALUES
(1, '月曜', NOW(), NOW()),
(2, '火曜', NOW(), NOW()),
(3, '水曜', NOW(), NOW()),
(4, '木曜', NOW(), NOW()),
(5, '金曜', NOW(), NOW()),
(6, '土曜', NOW(), NOW()),
(7, '日曜', NOW(), NOW());

-- シフト表示期間マスター
CREATE TABLE shift_periods (
    id TINYINT UNSIGNED PRIMARY KEY COMMENT '1=月初〜月末, 2=締め日翌日スタート',
    name VARCHAR(50) NOT NULL COMMENT '期間名',
    description VARCHAR(100) DEFAULT NULL COMMENT '補足説明',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shift_periods (id, name, description, created_at, updated_at) VALUES
(1, '月初〜月末', '1日〜30日または31日', NOW(), NOW()),
(2, '締め日翌日スタート', '給与締め日の翌日から翌月締め日まで', NOW(), NOW());

-- 打刻丸め単位マスター
CREATE TABLE shift_rounding_units (
    id TINYINT UNSIGNED PRIMARY KEY COMMENT '1=1分単位,2=5分単位,3=10分単位,4=15分単位,5=30分単位',
    name VARCHAR(50) NOT NULL COMMENT '丸め単位名',
    minutes TINYINT UNSIGNED NOT NULL COMMENT '丸め単位（分）',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shift_rounding_units (id, name, minutes, created_at, updated_at) VALUES
(1, '1分単位', 1, NOW(), NOW()),
(2, '5分単位', 5, NOW(), NOW()),
(3, '10分単位', 10, NOW(), NOW()),
(4, '15分単位', 15, NOW(), NOW()),
(5, '30分単位', 30, NOW(), NOW());

-- 承認ステータスマスター
CREATE TABLE approval_statuses (
    id TINYINT UNSIGNED PRIMARY KEY COMMENT '1=承認待ち,2=承認済み,3=却下',
    name VARCHAR(50) NOT NULL COMMENT 'ステータス名',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO approval_statuses (id, name, created_at, updated_at) VALUES
(1, '承認待ち', NOW(), NOW()),
(2, '承認済み', NOW(), NOW()),
(3, '却下', NOW(), NOW());

-- 申請タイプマスター（src-reから取得）
CREATE TABLE application_types (
    id TINYINT UNSIGNED PRIMARY KEY,
    code VARCHAR(50) NOT NULL COMMENT '申請タイプコード',
    name VARCHAR(100) NOT NULL COMMENT '申請タイプ名',
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '表示順序',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT '有効/無効',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO application_types (id, code, name, display_order, is_active, created_at, updated_at) VALUES
(1, 'paid-leave', '有給休暇', 1, TRUE, NOW(), NOW()),
(9, 'half-day-leave', '半日有給', 2, TRUE, NOW(), NOW()),
(10, 'hourly-leave', '時間有給', 3, TRUE, NOW(), NOW()),
(2, 'clock-error', '打刻間違い', 4, TRUE, NOW(), NOW()),
(3, 'late', '遅刻', 5, TRUE, NOW(), NOW()),
(4, 'early-leave', '早退', 6, TRUE, NOW(), NOW()),
(5, 'special-leave', '特別休暇', 7, TRUE, NOW(), NOW()),
(6, 'absence', '欠勤', 8, TRUE, NOW(), NOW()),
(7, 'overtime', '残業申請', 9, TRUE, NOW(), NOW()),
(8, 'other', 'その他', 10, TRUE, NOW(), NOW());

-- ============================================
-- トランザクションテーブル群
-- ============================================

-- 会社
CREATE TABLE companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '会社ID（想定：数千社まで）',
    company_code VARCHAR(20) NOT NULL COMMENT '会社コード（一意識別子）',
    name VARCHAR(100) NOT NULL COMMENT '会社名',
    is_closed_on_holidays BOOLEAN NOT NULL DEFAULT TRUE COMMENT '祝日を休業とする場合TRUE',
    payroll_closing_day TINYINT UNSIGNED DEFAULT 25 COMMENT '給与締切日（1-31）',
    paid_leave_half_day BOOLEAN NOT NULL DEFAULT FALSE COMMENT '半日単位の有給を付与する場合TRUE',
    paid_leave_hourly BOOLEAN NOT NULL DEFAULT FALSE COMMENT '時間単位の有給を付与する場合TRUE',
    daily_working_hours DECIMAL(4,1) NULL COMMENT '1日あたりの所定労働時間（時間有給の計算に使用）',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_company_code (company_code),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 会社ごとの定休日
CREATE TABLE company_regular_holidays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    weekday_id TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (weekday_id) REFERENCES weekdays(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_weekday (company_id, weekday_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 会社ごとの打刻丸め設定（現在の設定）
CREATE TABLE company_shift_rounding_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    rounding_unit_id TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (rounding_unit_id) REFERENCES shift_rounding_units(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_rounding (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 打刻丸め設定変更履歴
CREATE TABLE company_shift_rounding_setting_histories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    rounding_unit_id TINYINT UNSIGNED NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '変更日時',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (rounding_unit_id) REFERENCES shift_rounding_units(id) ON DELETE CASCADE,
    INDEX idx_company_changed_at (company_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 部署
CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL COMMENT '所属会社ID',
    name VARCHAR(100) NOT NULL COMMENT '部署名',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company_name (company_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ユーザーと会社の関係
CREATE TABLE user_companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'メインの所属会社',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_company (user_id, company_id),
    INDEX idx_company_user (company_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ユーザーとロールの関係（中間テーブル）
CREATE TABLE user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'ユーザーID',
    role_id TINYINT UNSIGNED NOT NULL COMMENT '役割ID',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ユーザーと部署の関係（中間テーブル）
CREATE TABLE user_departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'ユーザーID',
    department_id INT UNSIGNED NOT NULL COMMENT '部署ID',
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'メイン部署かどうか',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_department (user_id, department_id),
    INDEX idx_department_id (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 会社ごとのシフト表示設定
CREATE TABLE company_shift_display_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    shift_period_id TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_period_id) REFERENCES shift_periods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_shift_display (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- シフトパターン
CREATE TABLE shift_patterns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    name VARCHAR(100) NOT NULL COMMENT 'シフト名（例：早番・遅番など）',
    start_time TIME NOT NULL COMMENT '始業時刻',
    end_time TIME NOT NULL COMMENT '終業時刻',
    work_minutes SMALLINT UNSIGNED DEFAULT NULL COMMENT '勤務時間（分）',
    break_mode TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=分数入力, 2=区間入力',
    break_minutes SMALLINT UNSIGNED DEFAULT NULL COMMENT '休憩時間（分）',
    break_start TIME DEFAULT NULL COMMENT '休憩開始時刻',
    break_end TIME DEFAULT NULL COMMENT '休憩終了時刻',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company_name (company_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- シフト色設定（カレンダー表示用）
CREATE TABLE shift_colors (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    background_color VARCHAR(50) NOT NULL COMMENT '背景色（Tailwind or HEX）',
    text_color VARCHAR(50) NOT NULL COMMENT 'テキスト色（Tailwind or HEX）',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_color_combination (background_color, text_color)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shift_colors (background_color, text_color, created_at, updated_at) VALUES
('bg-blue-100', 'text-blue-800', NOW(), NOW()),      -- 早番
('bg-green-100', 'text-green-800', NOW(), NOW()),    -- 日勤
('bg-orange-100', 'text-orange-800', NOW(), NOW()),  -- 遅番
('bg-purple-100', 'text-purple-800', NOW(), NOW()),  -- 夜勤
('bg-yellow-100', 'text-yellow-800', NOW(), NOW()),  -- 明け
('bg-teal-100', 'text-teal-800', NOW(), NOW());      -- 休み

-- シフトパターンと色の紐付け
CREATE TABLE shift_pattern_colors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shift_pattern_id INT UNSIGNED NOT NULL,
    shift_color_id SMALLINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (shift_pattern_id) REFERENCES shift_patterns(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_color_id) REFERENCES shift_colors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_shift_pattern_color (shift_pattern_id, shift_color_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 打刻レコード（company_id追加）
CREATE TABLE time_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '打刻は大量データなのでBIGINT',
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    user_id INT UNSIGNED NOT NULL COMMENT '従業員ID',
    record_type TINYINT UNSIGNED NOT NULL COMMENT '1=勤務開始, 2=勤務終了, 3=日付越え終了, 4=休憩開始, 5=休憩終了',
    record_time DATETIME NOT NULL COMMENT '実際に打刻した日時',
    rounded_time DATETIME DEFAULT NULL COMMENT '丸め処理後の打刻日時',
    record_source TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=自動打刻, 2=手動入力, 3=申請修正',
    note VARCHAR(255) DEFAULT NULL COMMENT '備考',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_company_user_time (company_id, user_id, record_time),
    INDEX idx_company_time (company_id, record_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 勤務日実績（company_id追加）
CREATE TABLE daily_work_summaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '日次データは大量なのでBIGINT',
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    user_id INT UNSIGNED NOT NULL COMMENT '従業員ID',
    work_date DATE NOT NULL COMMENT '勤務日',
    scheduled_start_time TIME DEFAULT NULL COMMENT '所定始業時刻（シフト基準）',
    scheduled_end_time   TIME DEFAULT NULL COMMENT '所定終業時刻（シフト基準）',
    work_start DATETIME DEFAULT NULL COMMENT '勤務開始時刻',
    work_end DATETIME DEFAULT NULL COMMENT '勤務終了時刻',
    work_minutes SMALLINT UNSIGNED DEFAULT 0 COMMENT '勤務時間（分）',
    break_minutes SMALLINT UNSIGNED DEFAULT 0 COMMENT '休憩時間（分）',
    net_work_minutes SMALLINT UNSIGNED DEFAULT 0 COMMENT '実働時間（分）',
    night_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '深夜労働（分）',
    holiday_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '休日労働（分）',
    overtime_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '時間外労働（分）',
    late_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '遅刻時間（分）',
    early_leave_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '早退時間（分）',
    is_cross_day BOOLEAN NOT NULL DEFAULT FALSE COMMENT '日付越え勤務',
    record_source TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=自動集計, 2=手動修正, 3=申請修正',
    note VARCHAR(255) DEFAULT NULL COMMENT '備考',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_user_work_date (company_id, user_id, work_date),
    INDEX idx_company_work_date (company_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 勤務月合計（company_id追加）
CREATE TABLE monthly_work_summaries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    user_id INT UNSIGNED NOT NULL COMMENT '従業員ID',
    work_year SMALLINT UNSIGNED NOT NULL COMMENT '年(例: 2025)',
    work_month TINYINT UNSIGNED NOT NULL COMMENT '月(1-12)',
    total_work_minutes INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '総労働時間（分）',
    night_work_minutes INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '深夜労働（分）',
    overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '時間外労働（分）',
    holiday_work_minutes INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '休日労働（分）',
    record_source TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=自動集計,2=手動修正,3=申請反映',
    finalized BOOLEAN NOT NULL DEFAULT FALSE COMMENT '確定済みか',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_user_year_month (company_id, user_id, work_year, work_month),
    INDEX idx_company_year_month (company_id, work_year, work_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 申請（company_id追加）
CREATE TABLE requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    requested_by INT UNSIGNED NOT NULL COMMENT '申請者(user_id)',
    type TINYINT UNSIGNED NOT NULL COMMENT '1=休暇,2=遅刻,3=早退,4=欠席,5=休憩申請,6=その他',
    target_date DATE NOT NULL COMMENT '申請対象日',
    start_time TIME DEFAULT NULL COMMENT '開始時間',
    end_time TIME DEFAULT NULL COMMENT '終了時間',
    reason VARCHAR(1000) DEFAULT NULL COMMENT '申請理由',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=申請中,2=承認,3=却下,4=取消',
    approver_id INT UNSIGNED DEFAULT NULL COMMENT '承認者(user_id)',
    decided_at DATETIME DEFAULT NULL COMMENT '承認/却下日時',
    decision_note VARCHAR(1000) DEFAULT NULL COMMENT '承認・却下コメント',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_company_status_target_date (company_id, status, target_date),
    INDEX idx_company_requested_by (company_id, requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 打刻修正申請（company_id追加）
CREATE TABLE time_record_correction_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    user_id INT UNSIGNED NOT NULL COMMENT '申請者(従業員ID)',
    target_date DATE NOT NULL COMMENT '修正対象日',
    reason VARCHAR(1000) NOT NULL COMMENT '修正理由',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=申請中,2=承認済み,3=却下,4=取消',
    approver_id INT UNSIGNED DEFAULT NULL COMMENT '承認者(user_id)',
    approved_at DATETIME DEFAULT NULL COMMENT '承認日時',
    rejection_reason VARCHAR(1000) DEFAULT NULL COMMENT '却下理由',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_company_user_target_date (company_id, user_id, target_date),
    INDEX idx_company_status (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 打刻修正申請明細
CREATE TABLE time_record_correction_request_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    correction_request_id INT UNSIGNED NOT NULL COMMENT '打刻修正申請ID',
    time_record_id BIGINT UNSIGNED DEFAULT NULL COMMENT '修正対象の打刻レコードID',
    record_type TINYINT UNSIGNED NOT NULL COMMENT '1=勤務開始, 2=勤務終了, 3=日付越え終了, 4=休憩開始, 5=休憩終了',
    original_record_time DATETIME DEFAULT NULL COMMENT '修正前の打刻時刻',
    original_rounded_time DATETIME DEFAULT NULL COMMENT '修正前の丸め時刻',
    corrected_record_time DATETIME NOT NULL COMMENT '修正後の打刻時刻',
    corrected_rounded_time DATETIME DEFAULT NULL COMMENT '修正後の丸め時刻',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (correction_request_id) REFERENCES time_record_correction_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (time_record_id) REFERENCES time_records(id) ON DELETE SET NULL,
    INDEX idx_correction_request (correction_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 打刻修正履歴
CREATE TABLE time_record_corrections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    correction_request_detail_id INT UNSIGNED NOT NULL COMMENT '打刻修正申請明細ID',
    time_record_id BIGINT UNSIGNED NOT NULL COMMENT '修正対象の打刻レコードID',
    before_record_time DATETIME NOT NULL COMMENT '修正前の打刻時刻',
    before_rounded_time DATETIME DEFAULT NULL COMMENT '修正前の丸め時刻',
    before_record_source TINYINT UNSIGNED NOT NULL COMMENT '修正前の記録元',
    after_record_time DATETIME NOT NULL COMMENT '修正後の打刻時刻',
    after_rounded_time DATETIME DEFAULT NULL COMMENT '修正後の丸め時刻',
    after_record_source TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '修正後の記録元（3=申請修正）',
    corrected_by INT UNSIGNED NOT NULL COMMENT '修正実行者(user_id)',
    correction_note VARCHAR(1000) DEFAULT NULL COMMENT '修正時のメモ',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (correction_request_detail_id) REFERENCES time_record_correction_request_details(id) ON DELETE CASCADE,
    FOREIGN KEY (time_record_id) REFERENCES time_records(id) ON DELETE CASCADE,
    FOREIGN KEY (corrected_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_correction_request_detail (correction_request_detail_id),
    INDEX idx_time_record (time_record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- シフト（実際に登録されたシフト情報）
CREATE TABLE shifts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    user_id INT UNSIGNED NOT NULL COMMENT '従業員ID',
    shift_date DATE NOT NULL COMMENT 'シフト日付（DATE型直接保存：範囲検索最適化）',
    shift_pattern_id INT UNSIGNED NOT NULL COMMENT 'シフトパターンID',
    note VARCHAR(500) DEFAULT NULL COMMENT '備考',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_pattern_id) REFERENCES shift_patterns(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_company_user_shift_date (company_id, user_id, shift_date),
    INDEX idx_company_shift_date (company_id, shift_date),
    INDEX idx_company_user_date (company_id, user_id, shift_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ユーザーの勤務可能曜日設定
CREATE TABLE user_available_work_days (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '従業員ID',
    weekday_id TINYINT UNSIGNED NOT NULL COMMENT '曜日ID',
    is_available BOOLEAN NOT NULL DEFAULT TRUE COMMENT '勤務可能かどうか',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (weekday_id) REFERENCES weekdays(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_weekday (user_id, weekday_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ユーザーのシフトパターン設定
CREATE TABLE user_shift_patterns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '従業員ID',
    weekday_id TINYINT UNSIGNED NOT NULL COMMENT '曜日ID',
    shift_pattern_id INT UNSIGNED DEFAULT NULL COMMENT 'シフトパターンID（NULLは休み）',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (weekday_id) REFERENCES weekdays(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_pattern_id) REFERENCES shift_patterns(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_weekday_pattern (user_id, weekday_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 権限管理テーブル群
-- ============================================

-- 権限リソースマスター（シフト閲覧、勤務実績閲覧など）
CREATE TABLE permission_resources (
    id TINYINT UNSIGNED PRIMARY KEY,
    resource_code VARCHAR(50) NOT NULL COMMENT 'リソースコード',
    resource_name VARCHAR(100) NOT NULL COMMENT 'リソース名',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_resource_code (resource_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_resources (id, resource_code, resource_name, created_at, updated_at) VALUES
(1, 'shift_view', 'シフト閲覧', NOW(), NOW()),
(2, 'attendance_view', '勤務実績閲覧', NOW(), NOW()),
(3, 'request_approval', '申請承認', NOW(), NOW()),
(4, 'shift_edit', 'シフト編集', NOW(), NOW());

-- 権限スコープマスター（本人のみ、部署、全社）
CREATE TABLE permission_scopes (
    id TINYINT UNSIGNED PRIMARY KEY,
    scope_code VARCHAR(50) NOT NULL COMMENT 'スコープコード',
    scope_name VARCHAR(100) NOT NULL COMMENT 'スコープ名',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_scope_code (scope_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_scopes (id, scope_code, scope_name, created_at, updated_at) VALUES
(1, 'self', '本人のみ', NOW(), NOW()),
(2, 'department', '部署', NOW(), NOW()),
(3, 'company', '全社', NOW(), NOW());

-- 役割権限設定（役割ごとのデフォルト権限）
CREATE TABLE role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL COMMENT '役割ID',
    resource_id TINYINT UNSIGNED NOT NULL COMMENT 'リソースID',
    default_scope_id TINYINT UNSIGNED NOT NULL COMMENT 'デフォルトスコープID',
    is_fixed BOOLEAN NOT NULL DEFAULT FALSE COMMENT '変更不可フラグ（TRUE=固定、FALSE=変更可能）',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES permission_resources(id) ON DELETE CASCADE,
    FOREIGN KEY (default_scope_id) REFERENCES permission_scopes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_resource (role_id, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 一般ユーザー (role_id=3)
INSERT INTO role_permissions (role_id, resource_id, default_scope_id, is_fixed, created_at, updated_at) VALUES
(3, 1, 2, FALSE, NOW(), NOW()), -- シフト閲覧: デフォルト部署、変更可能
(3, 2, 1, TRUE, NOW(), NOW());  -- 勤務実績: 本人のみ固定

-- 責任者 (role_id=2)
INSERT INTO role_permissions (role_id, resource_id, default_scope_id, is_fixed, created_at, updated_at) VALUES
(2, 1, 2, FALSE, NOW(), NOW()), -- シフト閲覧: デフォルト部署、変更可能
(2, 2, 2, FALSE, NOW(), NOW()), -- 勤務実績: デフォルト部署、変更可能
(2, 3, 2, FALSE, NOW(), NOW()), -- 申請承認: デフォルト部署、変更可能
(2, 4, 2, FALSE, NOW(), NOW()); -- シフト編集: デフォルト部署、変更可能

-- 管理者 (role_id=1)
INSERT INTO role_permissions (role_id, resource_id, default_scope_id, is_fixed, created_at, updated_at) VALUES
(1, 1, 3, TRUE, NOW(), NOW()), -- シフト閲覧: 全社固定
(1, 2, 3, TRUE, NOW(), NOW()), -- 勤務実績: 全社固定
(1, 3, 3, TRUE, NOW(), NOW()), -- 申請承認: 全社固定
(1, 4, 3, TRUE, NOW(), NOW()); -- シフト編集: 全社固定

-- ユーザー個別権限（役割のデフォルトを上書きする場合に使用、is_fixed=FALSEの権限のみ）
CREATE TABLE user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'ユーザーID',
    resource_id TINYINT UNSIGNED NOT NULL COMMENT 'リソースID',
    scope_id TINYINT UNSIGNED NOT NULL COMMENT 'スコープID',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES permission_resources(id) ON DELETE CASCADE,
    FOREIGN KEY (scope_id) REFERENCES permission_scopes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_resource (user_id, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 労務アラート設定テーブル群
-- ============================================

-- アラートレベルマスター（注意・警告）
CREATE TABLE alert_levels (
    id TINYINT UNSIGNED PRIMARY KEY,
    code VARCHAR(20) NOT NULL COMMENT 'アラートレベルコード',
    name VARCHAR(50) NOT NULL COMMENT 'アラートレベル名',
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '表示順序',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO alert_levels (id, code, name, display_order, created_at, updated_at) VALUES
(1, 'caution', '注意', 1, NOW(), NOW()),
(2, 'warning', '警告', 2, NOW(), NOW());

-- 会社ごとの労務アラート閾値設定
CREATE TABLE company_labor_alert_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    alert_level_id TINYINT UNSIGNED NOT NULL COMMENT 'アラートレベルID',
    overtime_threshold_hours SMALLINT UNSIGNED NOT NULL DEFAULT 45 COMMENT '残業時間閾値（時間/月）',
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'このアラートを有効にするか',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (alert_level_id) REFERENCES alert_levels(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_alert_level (company_id, alert_level_id),
    INDEX idx_company_enabled (company_id, is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 会社ごとのアラートメッセージ設定
-- メッセージ内では以下のプレースホルダを使用可能:
-- {hours} - 時間数（例: 今月の残業時間が{hours}時間に達しています）
-- {threshold} - 閾値
-- {user_name} - ユーザー名
CREATE TABLE company_alert_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    alert_level_id TINYINT UNSIGNED NOT NULL COMMENT 'アラートレベルID',
    title VARCHAR(100) NOT NULL COMMENT 'アラートタイトル',
    message TEXT NOT NULL COMMENT 'アラートメッセージ（{hours},{threshold},{user_name}等のプレースホルダ使用可能）',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (alert_level_id) REFERENCES alert_levels(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_alert_message (company_id, alert_level_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 労務アラート履歴（発生したアラートの記録）
CREATE TABLE labor_alert_histories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'アラート履歴は大量になる可能性があるためBIGINT',
    company_id INT UNSIGNED NOT NULL COMMENT '会社ID',
    user_id INT UNSIGNED NOT NULL COMMENT '対象従業員ID',
    alert_level_id TINYINT UNSIGNED NOT NULL COMMENT 'アラートレベルID',
    target_year SMALLINT UNSIGNED NOT NULL COMMENT '対象年',
    target_month TINYINT UNSIGNED NOT NULL COMMENT '対象月',
    alert_value SMALLINT UNSIGNED NOT NULL COMMENT 'アラート発生時の値（時間）',
    threshold_value SMALLINT UNSIGNED NOT NULL COMMENT 'アラート発生時の閾値（時間）',
    title VARCHAR(100) NOT NULL COMMENT '表示されたアラートタイトル',
    message TEXT NOT NULL COMMENT '表示されたアラートメッセージ（プレースホルダ展開後）',
    is_read BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'ユーザーが確認済みか',
    read_at DATETIME DEFAULT NULL COMMENT '確認日時',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (alert_level_id) REFERENCES alert_levels(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_alert_year_month (user_id, alert_level_id, target_year, target_month),
    INDEX idx_company_user_year_month (company_id, user_id, target_year, target_month),
    INDEX idx_company_unread (company_id, is_read, created_at),
    INDEX idx_user_unread (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- スキーマ変更（ALTER文）
-- ============================================

-- ユーザーテーブルにパスワード変更必須フラグを追加
ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'パスワード変更必須フラグ' AFTER password;

-- ユーザーテーブルのemailをNULL許可に変更
ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL COMMENT 'メールアドレス';

-- 会社テーブルにUUIDカラムを追加
ALTER TABLE companies ADD COLUMN uuid CHAR(36) NULL COMMENT 'UUID（外部連携用識別子）' AFTER company_code;
ALTER TABLE companies ADD UNIQUE KEY unique_uuid (uuid);

-- 申請テーブルに反映日時カラムを追加（バッチでtime_recordsに反映済みかを管理）
-- ALTER TABLE requests ADD COLUMN applied_at DATETIME DEFAULT NULL COMMENT 'time_recordsへの反映日時（NULLは未反映）' AFTER decision_note;
-- ALTER TABLE requests ADD INDEX idx_status_applied (status, applied_at);

-- daily_work_summariesに休暇情報カラムを追加
ALTER TABLE daily_work_summaries ADD COLUMN leave_type TINYINT UNSIGNED NULL COMMENT '休暇種別（1=有給, 2=特休, 3=欠勤）' AFTER note;
ALTER TABLE daily_work_summaries ADD COLUMN leave_minutes INT UNSIGNED NULL COMMENT '休暇時間（分単位、NULLは終日）' AFTER leave_type;

-- monthly_work_summariesに休暇日数カラムを追加
ALTER TABLE monthly_work_summaries ADD COLUMN paid_leave_days DECIMAL(3,1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '有給休暇日数（0.5単位）' AFTER holiday_work_minutes;
ALTER TABLE monthly_work_summaries ADD COLUMN special_leave_days DECIMAL(3,1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '特別休暇日数（0.5単位）' AFTER paid_leave_days;
ALTER TABLE monthly_work_summaries ADD COLUMN absence_days DECIMAL(3,1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '欠勤日数（0.5単位）' AFTER special_leave_days;

-- 会社テーブルに基本シフトパターンIDを追加
ALTER TABLE companies ADD COLUMN default_shift_pattern_id INT UNSIGNED NULL COMMENT '基本シフトパターンID' AFTER uuid;
ALTER TABLE companies ADD FOREIGN KEY fk_companies_default_shift (default_shift_pattern_id) REFERENCES shift_patterns(id) ON DELETE SET NULL;

-- 会社テーブルに打刻画面非表示フラグを追加
ALTER TABLE companies ADD COLUMN is_stamp_hidden BOOLEAN NOT NULL DEFAULT FALSE COMMENT '打刻画面を非表示にする場合TRUE' AFTER paid_leave_hourly;

-- 部署テーブルに打刻画面非表示フラグを追加
ALTER TABLE departments ADD COLUMN is_stamp_hidden BOOLEAN NOT NULL DEFAULT FALSE COMMENT '打刻画面を非表示にする場合TRUE' AFTER name;

-- 打刻修正履歴テーブルに record_type カラムを追加
ALTER TABLE time_record_corrections
ADD COLUMN record_type TINYINT UNSIGNED DEFAULT NULL COMMENT '打刻種別（1=勤務開始, 2=勤務終了, 3=日付越え終了, 4=休憩開始, 5=休憩終了）'
AFTER time_record_id;

-- 既存データを time_records から backfill
UPDATE time_record_corrections trc
JOIN time_records tr ON trc.time_record_id = tr.id
SET trc.record_type = tr.record_type;

-- record_type を NOT NULL に変更
ALTER TABLE time_record_corrections
MODIFY COLUMN record_type TINYINT UNSIGNED NOT NULL COMMENT '打刻種別（1=勤務開始, 2=勤務終了, 3=日付越え終了, 4=休憩開始, 5=休憩終了）';

-- correction_request_detail_id を nullable に変更（管理者直接修正は NULL）
ALTER TABLE time_record_corrections
MODIFY COLUMN correction_request_detail_id INT UNSIGNED DEFAULT NULL COMMENT '打刻修正申請明細ID（NULL=管理者直接修正）';

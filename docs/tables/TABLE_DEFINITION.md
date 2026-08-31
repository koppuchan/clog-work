# テーブル定義書

勤怠管理システム データベース設計書

## 目次

1. [マスターテーブル](#マスターテーブル)
2. [会社・組織テーブル](#会社組織テーブル)
3. [ユーザー関連テーブル](#ユーザー関連テーブル)
4. [シフト関連テーブル](#シフト関連テーブル)
5. [打刻・勤務実績テーブル](#打刻勤務実績テーブル)
6. [申請関連テーブル](#申請関連テーブル)
7. [権限管理テーブル](#権限管理テーブル)
8. [労務アラートテーブル](#労務アラートテーブル)

---

## マスターテーブル

### roles（役割マスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | AUTO_INCREMENT | 主キー（1=管理者, 2=責任者, 3=一般） |
| name | VARCHAR(50) | NO | - | 役割名 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**初期データ:**
- 1: 管理者
- 2: 責任者
- 3: 一般

---

### weekdays（曜日マスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | - | 主キー（1=月〜7=日） |
| name | VARCHAR(10) | NO | - | 曜日名 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**初期データ:**
- 1: 月曜, 2: 火曜, 3: 水曜, 4: 木曜, 5: 金曜, 6: 土曜, 7: 日曜

---

### shift_periods（シフト表示期間マスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | - | 主キー |
| name | VARCHAR(50) | NO | - | 期間名 |
| description | VARCHAR(100) | YES | NULL | 補足説明 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**初期データ:**
- 1: 月初〜月末（1日〜30日または31日）
- 2: 締め日翌日スタート（給与締め日の翌日から翌月締め日まで）

---

### shift_rounding_units（打刻丸め単位マスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | - | 主キー |
| name | VARCHAR(50) | NO | - | 丸め単位名 |
| minutes | TINYINT UNSIGNED | NO | - | 丸め単位（分） |

**初期データ:**
- 1: 1分単位, 2: 5分単位, 3: 10分単位, 4: 15分単位, 5: 30分単位

---

### approval_statuses（承認ステータスマスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | - | 主キー |
| name | VARCHAR(50) | NO | - | ステータス名 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**初期データ:**
- 1: 承認待ち, 2: 承認済み, 3: 却下

---

### application_types（申請タイプマスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | - | 主キー |
| code | VARCHAR(50) | NO | - | 申請タイプコード |
| name | VARCHAR(100) | NO | - | 申請タイプ名 |
| display_order | TINYINT UNSIGNED | NO | 0 | 表示順序 |
| is_active | BOOLEAN | NO | TRUE | 有効/無効 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** code

**初期データ:**
| ID | コード | 名称 |
|----|--------|------|
| 1 | paid-leave | 有給休暇 |
| 2 | clock-error | 打刻間違い |
| 3 | late | 遅刻 |
| 4 | early-leave | 早退 |
| 5 | special-leave | 特別休暇 |
| 6 | absence | 欠勤 |
| 7 | overtime | 残業申請 |
| 8 | other | その他 |

---

## 会社・組織テーブル

### companies（会社）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 会社ID |
| company_code | VARCHAR(20) | NO | - | 会社コード（一意識別子） |
| name | VARCHAR(100) | NO | - | 会社名 |
| is_closed_on_holidays | BOOLEAN | NO | TRUE | 祝日を休業とする場合TRUE |
| payroll_closing_day | TINYINT UNSIGNED | YES | 25 | 給与締切日（1-31） |
| paid_leave_half_day | BOOLEAN | NO | FALSE | 半日単位の有給を付与する場合TRUE |
| paid_leave_hourly | BOOLEAN | NO | FALSE | 時間単位の有給を付与する場合TRUE |
| daily_working_hours | DECIMAL(4,1) | YES | NULL | 1日あたりの所定労働時間（時間有給の計算に使用） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** company_code
**インデックス:** name

---

### company_regular_holidays（会社定休日）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| weekday_id | TINYINT UNSIGNED | NO | - | 曜日ID（FK: weekdays.id） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (company_id, weekday_id)
**外部キー:** company_id → companies(id) ON DELETE CASCADE, weekday_id → weekdays(id) ON DELETE CASCADE

---

### company_shift_rounding_settings（会社打刻丸め設定）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| rounding_unit_id | TINYINT UNSIGNED | NO | - | 丸め単位ID（FK: shift_rounding_units.id） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** company_id

---

### company_shift_rounding_setting_histories（打刻丸め設定変更履歴）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| rounding_unit_id | TINYINT UNSIGNED | NO | - | 丸め単位ID（FK: shift_rounding_units.id） |
| changed_at | TIMESTAMP | NO | CURRENT_TIMESTAMP | 変更日時 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**インデックス:** (company_id, changed_at)

---

### departments（部署）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 部署ID |
| company_id | INT UNSIGNED | NO | - | 所属会社ID（FK: companies.id） |
| name | VARCHAR(100) | NO | - | 部署名 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**インデックス:** (company_id, name)
**外部キー:** company_id → companies(id) ON DELETE CASCADE

---

### company_shift_display_settings（会社シフト表示設定）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| shift_period_id | TINYINT UNSIGNED | NO | - | シフト期間ID（FK: shift_periods.id） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** company_id

---

## ユーザー関連テーブル

### users（ユーザー）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | ユーザーID |
| name | VARCHAR(100) | NO | - | ユーザー名 |
| name_kana | VARCHAR(255) | YES | NULL | フリガナ |
| employee_code | VARCHAR(6) | YES | NULL | 個人コード（6桁、会社内ユニーク・アプリ層で担保） |
| email | VARCHAR(191) | YES | NULL | メールアドレス |
| email_verified_at | TIMESTAMP | YES | NULL | メール認証日時 |
| password | VARCHAR(255) | NO | - | パスワードハッシュ |
| must_change_password | BOOLEAN | NO | FALSE | 初回ログイン時パスワード変更必須フラグ |
| is_retired | BOOLEAN | NO | FALSE | 退職済みフラグ |
| retirement_date | DATE | YES | NULL | 退職日 |
| remember_token | VARCHAR(100) | YES | NULL | ログイン保持トークン |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** email
**インデックス:** name, employee_code

---

### user_companies（ユーザー・会社関係）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| user_id | INT UNSIGNED | NO | - | ユーザーID（FK: users.id） |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| is_primary | BOOLEAN | YES | FALSE | メインの所属会社 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (user_id, company_id)
**インデックス:** (company_id, user_id)

---

### user_roles（ユーザー・役割関係）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| user_id | INT UNSIGNED | NO | - | ユーザーID（FK: users.id） |
| role_id | TINYINT UNSIGNED | NO | - | 役割ID（FK: roles.id） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (user_id, role_id)
**インデックス:** role_id

---

### user_departments（ユーザー・部署関係）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| user_id | INT UNSIGNED | NO | - | ユーザーID（FK: users.id） |
| department_id | INT UNSIGNED | NO | - | 部署ID（FK: departments.id） |
| is_primary | BOOLEAN | YES | FALSE | メイン部署かどうか |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (user_id, department_id)
**インデックス:** department_id

---

### user_available_work_days（ユーザー勤務可能曜日設定）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| user_id | INT UNSIGNED | NO | - | 従業員ID（FK: users.id） |
| weekday_id | TINYINT UNSIGNED | NO | - | 曜日ID（FK: weekdays.id） |
| is_available | BOOLEAN | NO | TRUE | 勤務可能かどうか |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (user_id, weekday_id)

---

### user_shift_patterns（ユーザーシフトパターン設定）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| user_id | INT UNSIGNED | NO | - | 従業員ID（FK: users.id） |
| weekday_id | TINYINT UNSIGNED | NO | - | 曜日ID（FK: weekdays.id） |
| shift_pattern_id | INT UNSIGNED | YES | NULL | シフトパターンID（FK: shift_patterns.id、NULLは休み） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (user_id, weekday_id)

---

## シフト関連テーブル

### shift_patterns（シフトパターン）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| name | VARCHAR(100) | NO | - | シフト名（例：早番・遅番など） |
| start_time | TIME | NO | - | 始業時刻 |
| end_time | TIME | NO | - | 終業時刻 |
| work_minutes | SMALLINT UNSIGNED | YES | NULL | 勤務時間（分） |
| break_mode | TINYINT UNSIGNED | NO | 1 | 1=分数入力, 2=区間入力 |
| break_minutes | SMALLINT UNSIGNED | YES | NULL | 休憩時間（分） |
| break_start | TIME | YES | NULL | 休憩開始時刻 |
| break_end | TIME | YES | NULL | 休憩終了時刻 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**インデックス:** (company_id, name)
**外部キー:** company_id → companies(id) ON DELETE CASCADE

---

### shift_colors（シフト色設定）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | SMALLINT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| background_color | VARCHAR(50) | NO | - | 背景色（Tailwind or HEX） |
| text_color | VARCHAR(50) | NO | - | テキスト色（Tailwind or HEX） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (background_color, text_color)

**初期データ:**
| 背景色 | テキスト色 | 用途例 |
|--------|-----------|--------|
| bg-blue-100 | text-blue-800 | 早番 |
| bg-green-100 | text-green-800 | 日勤 |
| bg-orange-100 | text-orange-800 | 遅番 |
| bg-purple-100 | text-purple-800 | 夜勤 |
| bg-yellow-100 | text-yellow-800 | 明け |
| bg-gray-100 | text-gray-800 | 休み |

---

### shift_pattern_colors（シフトパターン・色紐付け）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| shift_pattern_id | INT UNSIGNED | NO | - | シフトパターンID（FK: shift_patterns.id） |
| shift_color_id | SMALLINT UNSIGNED | NO | - | シフト色ID（FK: shift_colors.id） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (shift_pattern_id, shift_color_id)

---

### shifts（シフト）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| user_id | INT UNSIGNED | NO | - | 従業員ID（FK: users.id） |
| shift_date | DATE | NO | - | シフト日付 |
| shift_pattern_id | INT UNSIGNED | NO | - | シフトパターンID（FK: shift_patterns.id） |
| note | VARCHAR(500) | YES | NULL | 備考 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (company_id, user_id, shift_date)
**インデックス:** (company_id, shift_date), (company_id, user_id, shift_date)

---

## 打刻・勤務実績テーブル

### time_records（打刻レコード）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| user_id | INT UNSIGNED | NO | - | 従業員ID（FK: users.id） |
| record_type | TINYINT UNSIGNED | NO | - | 打刻種別（下記参照） |
| record_time | DATETIME | NO | - | 実際に打刻した日時 |
| rounded_time | DATETIME | YES | NULL | 丸め処理後の打刻日時 |
| record_source | TINYINT UNSIGNED | NO | 1 | 記録元（下記参照） |
| note | VARCHAR(255) | YES | NULL | 備考 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**record_type 値:**
- 1: 勤務開始
- 2: 勤務終了
- 3: 日付越え終了
- 4: 休憩開始
- 5: 休憩終了

**record_source 値:**
- 1: 自動打刻
- 2: 手動入力
- 3: 申請修正

**インデックス:** (company_id, user_id, record_time), (company_id, record_time)

---

### daily_work_summaries（勤務日実績）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| user_id | INT UNSIGNED | NO | - | 従業員ID（FK: users.id） |
| work_date | DATE | NO | - | 勤務日 |
| scheduled_start_time | TIME | YES | NULL | 所定始業時刻（シフト基準） |
| scheduled_end_time | TIME | YES | NULL | 所定終業時刻（シフト基準） |
| work_start | DATETIME | YES | NULL | 勤務開始時刻 |
| work_end | DATETIME | YES | NULL | 勤務終了時刻 |
| work_minutes | SMALLINT UNSIGNED | YES | 0 | 勤務時間（分） |
| break_minutes | SMALLINT UNSIGNED | YES | 0 | 休憩時間（分） |
| net_work_minutes | SMALLINT UNSIGNED | YES | 0 | 実働時間（分） |
| night_minutes | SMALLINT UNSIGNED | NO | 0 | 深夜労働（分） |
| holiday_minutes | SMALLINT UNSIGNED | NO | 0 | 休日労働（分） |
| overtime_minutes | SMALLINT UNSIGNED | NO | 0 | 時間外労働（分） |
| late_minutes | SMALLINT UNSIGNED | NO | 0 | 遅刻時間（分） |
| early_leave_minutes | SMALLINT UNSIGNED | NO | 0 | 早退時間（分） |
| is_cross_day | BOOLEAN | NO | FALSE | 日付越え勤務 |
| record_source | TINYINT UNSIGNED | NO | 1 | 記録元（1=自動集計, 2=手動修正, 3=申請修正） |
| note | VARCHAR(255) | YES | NULL | 備考 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (company_id, user_id, work_date)
**インデックス:** (company_id, work_date)

---

### monthly_work_summaries（勤務月合計）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| user_id | INT UNSIGNED | NO | - | 従業員ID（FK: users.id） |
| work_year | SMALLINT UNSIGNED | NO | - | 年（例: 2025） |
| work_month | TINYINT UNSIGNED | NO | - | 月（1-12） |
| total_work_minutes | INT UNSIGNED | NO | 0 | 総労働時間（分） |
| night_work_minutes | INT UNSIGNED | NO | 0 | 深夜労働（分） |
| overtime_minutes | INT UNSIGNED | NO | 0 | 時間外労働（分） |
| holiday_work_minutes | INT UNSIGNED | NO | 0 | 休日労働（分） |
| record_source | TINYINT UNSIGNED | NO | 1 | 記録元（1=自動集計, 2=手動修正, 3=申請反映） |
| finalized | BOOLEAN | NO | FALSE | 確定済みか |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (company_id, user_id, work_year, work_month)
**インデックス:** (company_id, work_year, work_month)

---

## 申請関連テーブル

### requests（申請）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| requested_by | INT UNSIGNED | NO | - | 申請者（FK: users.id） |
| type | TINYINT UNSIGNED | NO | - | 申請タイプ（下記参照） |
| target_date | DATE | NO | - | 申請対象日 |
| start_time | TIME | YES | NULL | 開始時間 |
| end_time | TIME | YES | NULL | 終了時間 |
| reason | VARCHAR(1000) | YES | NULL | 申請理由 |
| status | TINYINT UNSIGNED | NO | 1 | ステータス（下記参照） |
| approver_id | INT UNSIGNED | YES | NULL | 承認者（FK: users.id） |
| decided_at | DATETIME | YES | NULL | 承認/却下日時 |
| decision_note | VARCHAR(1000) | YES | NULL | 承認・却下コメント |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**type 値:**
- 1: 休暇, 2: 遅刻, 3: 早退, 4: 欠席, 5: 休憩申請, 6: その他

**status 値:**
- 1: 申請中, 2: 承認, 3: 却下, 4: 取消

**インデックス:** (company_id, status, target_date), (company_id, requested_by)

---

### time_record_correction_requests（打刻修正申請）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| user_id | INT UNSIGNED | NO | - | 申請者（FK: users.id） |
| target_date | DATE | NO | - | 修正対象日 |
| reason | VARCHAR(1000) | NO | - | 修正理由 |
| status | TINYINT UNSIGNED | NO | 1 | ステータス（1=申請中, 2=承認済み, 3=却下, 4=取消） |
| approver_id | INT UNSIGNED | YES | NULL | 承認者（FK: users.id） |
| approved_at | DATETIME | YES | NULL | 承認日時 |
| rejection_reason | VARCHAR(1000) | YES | NULL | 却下理由 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**インデックス:** (company_id, user_id, target_date), (company_id, status)

---

### time_record_correction_request_details（打刻修正申請明細）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| correction_request_id | INT UNSIGNED | NO | - | 打刻修正申請ID（FK: time_record_correction_requests.id） |
| time_record_id | BIGINT UNSIGNED | YES | NULL | 修正対象の打刻レコードID（FK: time_records.id） |
| record_type | TINYINT UNSIGNED | NO | - | 打刻種別 |
| original_record_time | DATETIME | YES | NULL | 修正前の打刻時刻 |
| original_rounded_time | DATETIME | YES | NULL | 修正前の丸め時刻 |
| corrected_record_time | DATETIME | NO | - | 修正後の打刻時刻 |
| corrected_rounded_time | DATETIME | YES | NULL | 修正後の丸め時刻 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**インデックス:** correction_request_id

---

### time_record_corrections（打刻修正履歴）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| correction_request_detail_id | INT UNSIGNED | NO | - | 打刻修正申請明細ID（FK: time_record_correction_request_details.id） |
| time_record_id | BIGINT UNSIGNED | NO | - | 修正対象の打刻レコードID（FK: time_records.id） |
| before_record_time | DATETIME | NO | - | 修正前の打刻時刻 |
| before_rounded_time | DATETIME | YES | NULL | 修正前の丸め時刻 |
| before_record_source | TINYINT UNSIGNED | NO | - | 修正前の記録元 |
| after_record_time | DATETIME | NO | - | 修正後の打刻時刻 |
| after_rounded_time | DATETIME | YES | NULL | 修正後の丸め時刻 |
| after_record_source | TINYINT UNSIGNED | NO | 3 | 修正後の記録元（3=申請修正） |
| corrected_by | INT UNSIGNED | NO | - | 修正実行者（FK: users.id） |
| correction_note | VARCHAR(1000) | YES | NULL | 修正時のメモ |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**インデックス:** correction_request_detail_id, time_record_id

---

## 権限管理テーブル

### permission_resources（権限リソースマスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | - | 主キー |
| resource_code | VARCHAR(50) | NO | - | リソースコード |
| resource_name | VARCHAR(100) | NO | - | リソース名 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** resource_code

**初期データ:**
| ID | コード | 名称 |
|----|--------|------|
| 1 | shift_view | シフト閲覧 |
| 2 | attendance_view | 勤務実績閲覧 |
| 3 | request_approval | 申請承認 |
| 4 | shift_edit | シフト編集 |

---

### permission_scopes（権限スコープマスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | - | 主キー |
| scope_code | VARCHAR(50) | NO | - | スコープコード |
| scope_name | VARCHAR(100) | NO | - | スコープ名 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** scope_code

**初期データ:**
| ID | コード | 名称 |
|----|--------|------|
| 1 | self | 本人のみ |
| 2 | department | 部署 |
| 3 | company | 全社 |

---

### role_permissions（役割権限設定）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| role_id | TINYINT UNSIGNED | NO | - | 役割ID（FK: roles.id） |
| resource_id | TINYINT UNSIGNED | NO | - | リソースID（FK: permission_resources.id） |
| default_scope_id | TINYINT UNSIGNED | NO | - | デフォルトスコープID（FK: permission_scopes.id） |
| is_fixed | BOOLEAN | NO | FALSE | 変更不可フラグ（TRUE=固定） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (role_id, resource_id)

**初期データ:**

| 役割 | リソース | デフォルトスコープ | 固定 |
|------|----------|-------------------|------|
| 一般(3) | シフト閲覧 | 部署 | × |
| 一般(3) | 勤務実績閲覧 | 本人のみ | ○ |
| 責任者(2) | シフト閲覧 | 部署 | × |
| 責任者(2) | 勤務実績閲覧 | 部署 | × |
| 責任者(2) | 申請承認 | 部署 | × |
| 責任者(2) | シフト編集 | 部署 | × |
| 管理者(1) | シフト閲覧 | 全社 | ○ |
| 管理者(1) | 勤務実績閲覧 | 全社 | ○ |
| 管理者(1) | 申請承認 | 全社 | ○ |
| 管理者(1) | シフト編集 | 全社 | ○ |

---

### user_permissions（ユーザー個別権限）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| user_id | INT UNSIGNED | NO | - | ユーザーID（FK: users.id） |
| resource_id | TINYINT UNSIGNED | NO | - | リソースID（FK: permission_resources.id） |
| scope_id | TINYINT UNSIGNED | NO | - | スコープID（FK: permission_scopes.id） |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (user_id, resource_id)

---

## 労務アラートテーブル

### alert_levels（アラートレベルマスター）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | TINYINT UNSIGNED | NO | - | 主キー |
| code | VARCHAR(20) | NO | - | アラートレベルコード |
| name | VARCHAR(50) | NO | - | アラートレベル名 |
| display_order | TINYINT UNSIGNED | NO | 0 | 表示順序 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** code

**初期データ:**
| ID | コード | 名称 |
|----|--------|------|
| 1 | caution | 注意 |
| 2 | warning | 警告 |

---

### company_labor_alert_settings（会社労務アラート閾値設定）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| alert_level_id | TINYINT UNSIGNED | NO | - | アラートレベルID（FK: alert_levels.id） |
| overtime_threshold_hours | SMALLINT UNSIGNED | NO | 45 | 残業時間閾値（時間/月） |
| is_enabled | BOOLEAN | NO | TRUE | このアラートを有効にするか |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (company_id, alert_level_id)
**インデックス:** (company_id, is_enabled)

---

### company_alert_messages（会社アラートメッセージ設定）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | INT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| alert_level_id | TINYINT UNSIGNED | NO | - | アラートレベルID（FK: alert_levels.id） |
| title | VARCHAR(100) | NO | - | アラートタイトル |
| message | TEXT | NO | - | アラートメッセージ |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (company_id, alert_level_id)

**プレースホルダ:**
- `{hours}` - 時間数
- `{threshold}` - 閾値
- `{user_name}` - ユーザー名

---

### labor_alert_histories（労務アラート履歴）

| カラム名 | データ型 | NULL | デフォルト | 説明 |
|---------|---------|------|-----------|------|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | 主キー |
| company_id | INT UNSIGNED | NO | - | 会社ID（FK: companies.id） |
| user_id | INT UNSIGNED | NO | - | 対象従業員ID（FK: users.id） |
| alert_level_id | TINYINT UNSIGNED | NO | - | アラートレベルID（FK: alert_levels.id） |
| target_year | SMALLINT UNSIGNED | NO | - | 対象年 |
| target_month | TINYINT UNSIGNED | NO | - | 対象月 |
| alert_value | SMALLINT UNSIGNED | NO | - | アラート発生時の値（時間） |
| threshold_value | SMALLINT UNSIGNED | NO | - | アラート発生時の閾値（時間） |
| title | VARCHAR(100) | NO | - | 表示されたアラートタイトル |
| message | TEXT | NO | - | 表示されたアラートメッセージ |
| is_read | BOOLEAN | NO | FALSE | ユーザーが確認済みか |
| read_at | DATETIME | YES | NULL | 確認日時 |
| created_at | TIMESTAMP | YES | NULL | 作成日時 |
| updated_at | TIMESTAMP | YES | NULL | 更新日時 |

**ユニークキー:** (user_id, alert_level_id, target_year, target_month)
**インデックス:** (company_id, user_id, target_year, target_month), (company_id, is_read, created_at), (user_id, is_read, created_at)

---

## ER図（概要）

```
[会社] 1 ── * [部署]
[会社] 1 ── * [シフトパターン]
[会社] 1 ── * [会社定休日]
[会社] 1 ── * [会社労務アラート設定]

[ユーザー] * ── * [会社] (user_companies)
[ユーザー] * ── * [部署] (user_departments)
[ユーザー] * ── * [役割] (user_roles)

[ユーザー] 1 ── * [打刻レコード]
[ユーザー] 1 ── * [勤務日実績]
[ユーザー] 1 ── * [勤務月合計]
[ユーザー] 1 ── * [シフト]
[ユーザー] 1 ── * [申請]

[シフト] * ── 1 [シフトパターン]
[シフトパターン] * ── * [シフト色] (shift_pattern_colors)

[役割] 1 ── * [役割権限設定]
[ユーザー] 1 ── * [ユーザー個別権限]
```

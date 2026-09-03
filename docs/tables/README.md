# テーブル設計説明書

このドキュメントは、勤怠管理システムのデータベース設計（`table-design-optimized.sql`）について説明します。

## 目次

- [全体設計方針](#全体設計方針)
- [テーブル一覧](#テーブル一覧)
- [マスターテーブル](#マスターテーブル)
- [トランザクションテーブル](#トランザクションテーブル)
- [権限管理テーブル](#権限管理テーブル)
- [重要な設計ポイント](#重要な設計ポイント)

## 全体設計方針

### 1. 正規化とパフォーマンスのバランス
- 第3正規形を基本としつつ、パフォーマンスが必要な箇所では非正規化も検討
- マスターテーブルと中間テーブルで多対多関係を実現
- 頻繁に検索される項目には適切なインデックスを配置

### 2. データ型の最適化
- **TINYINT UNSIGNED**: マスターデータ（最大255件）
  - 役割、曜日、申請タイプなど
- **SMALLINT UNSIGNED**: 中規模データ（最大65,535件）
  - シフト色設定など
- **INT UNSIGNED**: 大規模データ（最大約43億件）
  - 会社、ユーザー、部署、シフトなど
- **BIGINT UNSIGNED**: 超大規模データ（最大約1844京件）
  - 打刻レコード、日次勤務実績など

### 3. company_idの追加
主要なトランザクションテーブルには`company_id`を追加し、マルチテナント対応とパフォーマンス向上を実現:
- 会社ごとのデータ分離
- 複合インデックスによる高速検索
- データ削除時のカスケード処理

## テーブル一覧

### マスターテーブル（8テーブル）
| テーブル名 | 説明 | 主キーデータ型 |
|-----------|------|---------------|
| roles | 役割マスター（管理者/責任者/一般） | TINYINT |
| weekdays | 曜日マスター | TINYINT |
| shift_periods | シフト表示期間マスター | TINYINT |
| shift_rounding_units | 打刻丸め単位マスター | TINYINT |
| approval_statuses | 承認ステータスマスター | TINYINT |
| application_types | 申請タイプマスター | TINYINT |
| permission_resources | 権限リソースマスター | TINYINT |
| permission_scopes | 権限スコープマスター | TINYINT |

### トランザクションテーブル（22テーブル）
| カテゴリ | テーブル名 | 説明 |
|---------|-----------|------|
| **会社関連** | companies | 会社基本情報 |
| | company_regular_holidays | 会社の定休日設定 |
| | company_shift_rounding_settings | 会社の打刻丸め設定（現在） |
| | company_shift_rounding_setting_histories | 打刻丸め設定変更履歴 |
| | company_shift_display_settings | シフト表示期間設定 |
| | departments | 部署情報 |
| **ユーザー関連** | users | ユーザー基本情報 |
| | user_companies | ユーザーと会社の紐付け |
| | user_roles | ユーザーと役割の紐付け |
| | user_departments | ユーザーと部署の紐付け |
| | user_available_work_days | ユーザーの勤務可能曜日 |
| | user_shift_patterns | ユーザーの曜日別シフトパターン |
| **シフト関連** | shift_patterns | シフトパターン定義 |
| | shift_colors | シフト色設定 |
| | shift_pattern_colors | シフトパターンと色の紐付け |
| | shifts | 実際に登録されたシフト |
| **勤怠関連** | time_records | 打刻レコード |
| | daily_work_summaries | 日次勤務実績 |
| | monthly_work_summaries | 月次勤務実績 |
| | felica_stamp_attempts | FeliCa打刻の試行ログ（打刻専用画面へのトースト表示用。マイグレーション管理） |
| **申請関連** | requests | 各種申請 |
| | time_record_correction_requests | 打刻修正申請 |
| | time_record_correction_request_details | 打刻修正申請明細 |
| | time_record_corrections | 打刻修正履歴 |

### 権限管理テーブル（2テーブル）
| テーブル名 | 説明 |
|-----------|------|
| role_permissions | 役割ごとのデフォルト権限 |
| user_permissions | ユーザー個別権限（上書き用） |

## マスターテーブル

### roles（役割マスター）
```sql
id | name
---|------
1  | 管理者
2  | 責任者
3  | 一般
```

**用途**: ユーザーの役割を定義し、権限管理の基礎となる

### weekdays（曜日マスター）
```sql
id | name
---|------
1  | 月曜
2  | 火曜
...
7  | 日曜
```

**用途**:
- 会社の定休日設定
- ユーザーの勤務可能曜日設定
- シフトパターン設定

### permission_resources（権限リソースマスター）
```sql
id | resource_code      | resource_name
---|--------------------|---------------
1  | shift_view         | シフト閲覧
2  | attendance_view    | 勤務実績閲覧
3  | request_approval   | 申請承認
4  | shift_edit         | シフト編集
```

### permission_scopes（権限スコープマスター）
```sql
id | scope_code | scope_name
---|-----------|------------
1  | self      | 本人のみ
2  | department| 部署
3  | company   | 全社
```

## トランザクションテーブル

### user_available_work_days（ユーザーの勤務可能曜日）

**目的**: ユーザーがどの曜日に勤務可能かを管理

**カラム構成**:
| カラム名 | データ型 | 説明 |
|---------|---------|------|
| id | INT UNSIGNED | 主キー |
| user_id | INT UNSIGNED | ユーザーID |
| weekday_id | TINYINT UNSIGNED | 曜日ID（1-7） |
| is_available | BOOLEAN | 勤務可能か |
| created_at | TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | 更新日時 |

**使用例**:
```sql
-- 月曜日に勤務可能
INSERT INTO user_available_work_days (user_id, weekday_id, is_available)
VALUES (123, 1, TRUE);

-- 日曜日は勤務不可
INSERT INTO user_available_work_days (user_id, weekday_id, is_available)
VALUES (123, 7, FALSE);
```

### user_shift_patterns（ユーザーのシフトパターン設定）

**目的**: ユーザーの各曜日におけるデフォルトのシフトパターンを管理

**カラム構成**:
| カラム名 | データ型 | 説明 |
|---------|---------|------|
| id | INT UNSIGNED | 主キー |
| user_id | INT UNSIGNED | ユーザーID |
| weekday_id | TINYINT UNSIGNED | 曜日ID |
| shift_pattern_id | INT UNSIGNED | シフトパターンID（NULLは休み） |
| created_at | TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | 更新日時 |

**使用例**:
```sql
-- 月曜日は早番
INSERT INTO user_shift_patterns (user_id, weekday_id, shift_pattern_id)
VALUES (123, 1, 10);

-- 日曜日は休み（shift_pattern_id = NULL）
INSERT INTO user_shift_patterns (user_id, weekday_id, shift_pattern_id)
VALUES (123, 7, NULL);
```

### felica_stamp_attempts（FeliCa打刻の試行ログ）

**目的**: FeliCa打刻専用画面（`/stamp/{uuid}`）にカードタップの結果をリアルタイム表示するためのログ。

常駐アプリ（FeliCa打刻）はサーバーへ直接POSTするため、打刻専用画面（ブラウザ）は打刻結果を知る手段を持たない。
このテーブルに成功・失敗を問わず全ての試行を記録し、ブラウザ側は `GET /stamp/{uuid}/felica-events` を数秒おきにポーリングして新しい試行をトースト表示する。

他のトランザクションテーブルと異なり、`table-design-optimized.sql`ではなく通常のLaravelマイグレーション（`2026_09_03_100000_create_felica_stamp_attempts_table.php`）で管理する。

**カラム構成**:
| カラム名 | データ型 | 説明 |
|---------|---------|------|
| id | BIGINT UNSIGNED | 主キー（高頻度データのためBIGINT） |
| company_id | INT UNSIGNED | 会社ID |
| user_id | INT UNSIGNED NULL | 打刻したユーザーID（未登録カードの場合はNULL） |
| felica_idm | VARCHAR(16) | FeliCa IDm（16進数16桁） |
| status | VARCHAR(20) | success / cooldown / unregistered / retired / error |
| message | VARCHAR(255) | 打刻専用画面に表示する見出しメッセージ |
| detail | VARCHAR(255) NULL | 打刻専用画面に表示する補足メッセージ |
| time_record_id | BIGINT UNSIGNED NULL | 成功時に記録された打刻レコードID |
| created_at | TIMESTAMP | 記録日時（updated_atは使用しない） |

## 権限管理テーブル

### role_permissions（役割権限設定）

**目的**: 各役割のデフォルト権限を定義

**設計ポイント**:
- `is_fixed`: TRUE=変更不可、FALSE=ユーザーごとに上書き可能
- 管理者の権限は全て固定（`is_fixed=TRUE`）
- 一般ユーザーは一部権限を変更可能

**権限マトリクス**:

| 役割 | リソース | デフォルトスコープ | 変更可能 |
|-----|---------|-----------------|---------|
| **一般** | シフト閲覧 | 部署 | ✓ |
| | 勤務実績閲覧 | 本人のみ | ✗（固定） |
| **責任者** | シフト閲覧 | 部署 | ✓ |
| | 勤務実績閲覧 | 部署 | ✓ |
| | 申請承認 | 部署 | ✓ |
| | シフト編集 | 部署 | ✓ |
| **管理者** | シフト閲覧 | 全社 | ✗（固定） |
| | 勤務実績閲覧 | 全社 | ✗（固定） |
| | 申請承認 | 全社 | ✗（固定） |
| | シフト編集 | 全社 | ✗（固定） |

### user_permissions（ユーザー個別権限）

**目的**: 役割のデフォルト権限を上書き

**使用条件**:
- `is_fixed=FALSE`の権限のみ上書き可能
- 例: 一般ユーザーのシフト閲覧を「部署」→「全社」に変更

## 重要な設計ポイント

### 1. 曜日設定の3段階構造

1. **company_regular_holidays**: 会社全体の定休日
2. **user_available_work_days**: ユーザーの勤務可能曜日
3. **user_shift_patterns**: ユーザーの曜日別シフトパターン

**優先順位**: 会社定休日 > ユーザー勤務可能曜日 > シフトパターン

### 2. 祝日勤務の管理

- **companies.is_closed_on_holidays**: 会社として祝日を休業とするか
- 祝日が勤務日として扱われた場合の各ユーザーの勤務有無は、その曜日のシフトパターン設定（`user_shift_patterns`）に従う

### 3. 打刻丸め設定の履歴管理

- **company_shift_rounding_settings**: 現在の設定
- **company_shift_rounding_setting_histories**: 変更履歴

**目的**:
- 過去の打刻データを正しく再計算
- 設定変更による影響範囲の把握

### 4. 権限の2層構造

1. **役割ベース権限**（role_permissions）
   - 役割ごとのデフォルト設定
   - `is_fixed`で変更可否を制御

2. **ユーザー個別権限**（user_permissions）
   - 役割のデフォルトを上書き
   - `is_fixed=FALSE`の権限のみ

**権限判定ロジック**:
```
IF user_permissionsにレコードあり THEN
    user_permissionsの権限を適用
ELSE
    role_permissionsの権限を適用
END IF
```

### 5. マルチテナント対応

主要テーブルに`company_id`を追加:
- データの論理的分離
- 会社ごとのパフォーマンス最適化
- 複合インデックスによる高速検索

**インデックス例**:
```sql
INDEX idx_company_user_date (company_id, user_id, shift_date)
```

## データ整合性の保証

### 外部キー制約

- **CASCADE**: 親レコード削除時に子レコードも自動削除
  - 会社削除時に関連する全データを削除
  - ユーザー削除時に関連する全設定を削除

- **SET NULL**: 親レコード削除時に子レコードの外部キーをNULLに
  - 承認者削除時も申請レコードは残す

- **RESTRICT**: 子レコードが存在する場合は親レコード削除を拒否
  - シフトパターンが使用中の場合は削除不可

### UNIQUE制約

重複データの防止:
- `unique_user_weekday`: 同一ユーザー・曜日の組み合わせは1件のみ
- `unique_company_user_shift_date`: 同一会社・ユーザー・日付のシフトは1件のみ
- `unique_user_holiday_setting`: 1ユーザー1設定のみ

## パフォーマンス最適化

### インデックス設計

1. **複合インデックス**: 検索条件に合わせた複合インデックス
   ```sql
   INDEX idx_company_user_time (company_id, user_id, record_time)
   ```

2. **カバリングインデックス**: SELECT対象カラムを全て含む
   ```sql
   INDEX idx_company_status_target_date (company_id, status, target_date)
   ```

3. **ソート用インデックス**: ORDER BY句で使用するカラム
   ```sql
   INDEX idx_company_changed_at (company_id, changed_at)
   ```

### クエリ最適化例

```sql
-- ✓ 良い例: インデックスを使用
SELECT * FROM shifts
WHERE company_id = 1
  AND user_id = 123
  AND shift_date BETWEEN '2025-01-01' AND '2025-01-31';

-- ✗ 悪い例: インデックスを使用しない
SELECT * FROM shifts
WHERE YEAR(shift_date) = 2025
  AND MONTH(shift_date) = 1;
```

## 今後の拡張性

### 追加予定の機能

1. **有給休暇管理**: 残日数、取得履歴
2. **労働基準法対応**: 36協定、時間外労働上限チェック
3. **給与計算連携**: 勤務実績データのエクスポート
4. **通知機能**: 承認待ち、締め切り前通知
5. **監査ログ**: データ変更履歴の記録

### スケーラビリティ

- **パーティショニング**: 大量データ蓄積時の対策
  ```sql
  PARTITION BY RANGE (YEAR(work_date))
  ```

- **アーカイブ**: 古いデータの別テーブル移動
- **読み取りレプリカ**: レポート用の別DB

## 参考資料

- [Laravel Eloquent ORM](https://laravel.com/docs/12.x/eloquent)
- [MySQL 8.0 Reference Manual](https://dev.mysql.com/doc/refman/8.0/en/)
- [データベース設計のアンチパターン](https://www.oreilly.co.jp/books/9784873115894/)

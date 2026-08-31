# _recovery — 復元資料

前任開発者から引き継ぎが受けられなかったため、入手可能な資料から復元した参考情報です。
アプリケーション本体のビルドには使用しません。実装時の仕様確認用です。

## felica-app-src/

FeliCa打刻アプリ（`FeliCa打刻 Setup 0.2.6.exe`）から復元したソースコード。
インストーラ内に非圧縮で埋め込まれていた `app.asar` を展開したもの。

**サーバー側が実装すべきAPI**

```
POST {serverUrl}/stamp/{companyUuid}/felica
Content-Type: application/json
Accept: application/json

Body : { "idm": "0123456789abcdef", "intent": "break-start" }
       intent は休憩開始モード時のみ付与。通常は省略。

成功条件 : HTTP 2xx かつ レスポンス body の success === true
```

- リーダー: Sony RC-S300（PC/SC 経由、`nfc-pcsc`）
- IDm: APDU `FFCA000000` で取得、小文字16進16桁
- アプリ側設定（`electron-store` / `felica-stamp-config`）: `serverUrl` / `companyUuid` / `deviceName`

このエンドポイントを同じ仕様で実装すれば、既存の v0.2.6 がそのまま利用できる。

## production-build/

旧本番環境（clog-work.jp）が配信していたビルド済みフロントエンド一式。
2026-06-03 以降の改修が反映された最終状態のため、画面構成の差分確認に使用する。

`manifest.json` から確認できる現行ページは 33 画面。
6/3 時点のソース（29 画面）に対し、以下の 4 画面が新規追加されている。

- `SuperAdmin/Dashboard.tsx`
- `SuperAdmin/Companies.tsx`
- `SuperAdmin/NewCompany.tsx`
- `SuperAdmin/Users.tsx`

残り 29 画面は既存ファイル内部のロジック改修。

## deploy/

前任者作成のデプロイ手順書・スクリプト・nginx 設定。
サーバー構成の参考にする。

**認証情報は `<REDACTED>` に置換済み。**

判明しているサーバー構成:

| 項目 | 値 |
| --- | --- |
| アプリ配置 | `/var/www/attendance-web` |
| ドキュメントルート | `/var/www/attendance-web/public` |
| PHP-FPM | `unix:/run/php/php8.4-fpm.sock` |
| DB | MySQL（同一ホスト） |

FeliCa 用のカラム追加 SQL も `deploy.sh` 内に残っている。

```sql
ALTER TABLE users
  ADD COLUMN felica_idm VARCHAR(16) DEFAULT NULL,
  ADD COLUMN felica_registered_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE users ADD UNIQUE KEY uk_users_felica_idm (felica_idm);
```

## 帳票仕様（勤務実績Excel）

実際の出力ファイルから復元。テンプレート内の数式を保持したままデータ範囲のみ書き込む方式。

- シート名: `テンプレート`
- 行6: ヘッダー（勤務区分 / シフト開始 / シフト終了 / シフト休憩入 / シフト休憩出 / 出勤時刻 / 退勤時刻 / 休憩入① / 休憩出① / 休憩入② / 休憩出② / 労働時間 / 時間外 / 休日 / 深夜 / 遅刻早退 / 備考・申請）
- 行7〜37: 日別データ（31日分）
- 行38: 合計（SUM）
- 行3〜4: サマリ（有給日数・欠勤日数・特休日数・休日日数・遅早回数・実働時間・時間外・休日時間・遅早時間・遅早申請回数・遅早申請時間・残業申請）
- 給与締切日: 20日（出力期間が `06月21日〜07月20日` であることから確定）

サマリ欄は備考・申請列のテキスト（`有給休暇`, `時間有給`, `半日有休`, `特別休暇`, `休日出勤`, `遅刻`, `早退`, `残業申請`）を解析して時間数を合算する数式で構成されている。

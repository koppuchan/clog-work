#!/usr/bin/env bash
# GitHub Issue 一括作成スクリプト
#
# 事前準備:
#   sudo apt install gh
#   gh auth login
#
# 実行:
#   bash _recovery/create-issues.sh
#
set -euo pipefail
REPO="koppuchan/clog-work"

for l in "infra:0e8a16:サーバー・インフラ" \
         "initial-4:d73a4a:当初依頼の4点" \
         "reimpl:1d76db:6/3以降の再実装" \
         "migration:5319e7:移行作業"; do
  IFS=: read -r name color desc <<< "$l"
  gh label create "$name" --repo "$REPO" --color "$color" --description "$desc" 2>/dev/null || true
done

mk() { gh issue create --repo "$REPO" --title "$1" --label "$2" --body "$3"; }

############################################
# インフラ
############################################
mk "新VPSの初期構築" infra \
"Ubuntu 24.04 上に旧環境と同等のスタックを構築する。

- nginx
- PHP 8.4 + PHP-FPM（ソケット: \`/run/php/php8.4-fpm.sock\`）
- MySQL
- Node.js / npm（\`npm run build\` 用）
- Composer

アプリ配置は \`/var/www/attendance-web\`、ドキュメントルートは \`/var/www/attendance-web/public\`。
参考: \`_recovery/deploy/DEPLOY_RUNBOOK.md\`, \`_recovery/deploy/nginx-clog-work.jp.conf\`"

mk "ドメイン・DNS・SSL設定" infra \
"- \`clog-work.jp\` / \`www.clog-work.jp\` のAレコードを新VPSへ向ける
- ネームサーバーは \`ns1-3.xvps.ne.jp\`（VPSパネルのDNS設定で編集する。レンタルサーバー側のDNS設定は反映されない）
- certbot で Let's Encrypt 証明書を取得し、httpsへリダイレクト
- \`.env\` の \`APP_URL\` を \`https://clog-work.jp\` に設定

**注意**: 旧VPSが稼働中の間は切り替えないこと。動作確認後に切り替える。"

mk "メール送信設定とGmail到達性の確保" infra \
"会社新規登録時の確認メールが届かない問題の恒久対策。

旧環境の問題点:
- SPF が \`v=spf1 ip4:162.43.90.89 ~all\`（VPSのIPのみ）
- MXレコードが存在しない
- 逆引きが \`x162-43-90-89.static.xvps.ne.jp\` の汎用ホスト名
- \`.env\` の \`MAIL_MAILER\` が \`log\` のままだった可能性

対応:
- 外部SMTP経由での送信に切り替える（既存のレンタルサーバーのメールアカウント等）
- SPF / DKIM / DMARC を設定
- 実際にGmail宛へ到達することをテストで確認

参考: \`app/Services/RegistrationService.php\` の \`Mail::to(\$email)->send(...)\`（同期送信）"

mk "デプロイ手順の整備とGitHub連携" infra \
"- リポジトリからのデプロイ手順を確立する
- \`composer install --no-dev --optimize-autoloader\` / \`npm ci && npm run build\`
- \`php artisan config:clear\` 等のキャッシュ処理
- ロールバック手順の用意

参考: \`_recovery/deploy/deploy.sh\`, \`_recovery/deploy/rollback.sh\`"

mk "DBの自動バックアップ" infra \
"旧環境では毎日深夜3時に取得・30日保持の設定が入っていた。

**要確認**: 新VPSでビジネスプランの自動バックアップを利用する場合は不要になる可能性がある。
櫻本さんの意向を確認したうえで判断する。"

############################################
# 当初依頼の4点
############################################
mk "事業所（会社）の削除機能を追加する" initial-4 \
"スーパー管理画面から事業所を削除できるようにする。

**背景**
テスト登録した事業所に紐づくメールアドレスが解放されず、再登録できない。
対象: Smile circle株式会社 / \`izumi@...\`

**調査済みの前提**
- \`companies\` を参照する外部キー16本はすべて \`ON DELETE CASCADE\`。会社を消せば関連データは自動削除される
- ただし \`users\` は \`user_companies\` 経由の多対多。会社を消しても **users レコードと email の UNIQUE 制約は残る**
- したがってメールアドレスを解放するには、その会社にのみ所属するユーザーの削除も必要

**受け入れ条件**
- [ ] スーパー管理画面の事業所一覧に削除操作がある
- [ ] 削除時に確認ダイアログが出る
- [ ] 削除後、当該メールアドレスで新規登録できる
- [ ] 他の事業所のデータに影響がない"

mk "新規事業所作成時の管理者個人コードを固定値にする" initial-4 \
"**背景**
新規事業所を作成すると管理者の個人コードが全事業所を通した連番で振られるため、
その事業所だけコード体系が不揃いになる（例: 会社000002の管理者が000007）。

**原因（特定済み）**
\`app/Services/RegistrationService.php\` の \`generateEmployeeCode()\` が
\`getMaxEmployeeCode()\`（全ユーザー横断の最大値）を使用している。

**実装方針**
- \`employee_code\` にDBのUNIQUE制約は無く、インデックスのみ（会社ごとの一意性はアプリ層で担保）。
  したがって全事業所の管理者に同じ固定値を割り当ててよい
- \`completeRegistration()\` 内の採番呼び出し1箇所を固定値に変更する
- 桁数は6桁ゼロ埋めのため \`009999\`

**要確認**: 固定値を \`009999\` として良いか櫻本さんに確認する。

**受け入れ条件**
- [ ] 新規事業所作成時、管理者に固定コードが割り当たる
- [ ] 既存ユーザーの個人コードは一切変化しない
- [ ] 一般スタッフの採番ロジックは変更しない"

mk "会社新規登録時のメールが届かない問題を修正する" initial-4 \
"**背景**
新規事業所の登録時に確認メールが届かない。

**調査済みの前提**
- 送信処理は \`Mail::to(\$email)->send(new RegistrationVerificationMail(...))\` の**同期送信**。
  キューワーカー停止が原因の線は消えている
- 原因は \`.env\` の \`MAIL_MAILER\` 設定か、DNS（SPF/MX/逆引き）に起因する到達性のいずれか
- いずれの場合もアプリケーションコードの変更は不要

インフラ側の対応は #メール送信設定とGmail到達性の確保 を参照。

**受け入れ条件**
- [ ] 新規登録時にGmail宛へメールが到達する（迷惑メールフォルダも含めて確認）
- [ ] 送信ログで成否が追跡できる"

mk "個人マスタにシフト表非表示フラグを追加する" initial-4 \
"管理者などをシフト表に表示しないようにする。

**実装方針**
既に \`is_stamp_hidden\`（打刻画面を非表示）という同型の実装があるため、同じ形で追加する。

\`\`\`php
\$table->boolean('is_shift_hidden')->default(false)
    ->comment('シフト表を非表示にする場合TRUE');
\`\`\`

\`default(false)\` のため既存レコードの挙動は変わらない。

**要確認**（実装前に櫻本さんへ）
- 非表示にした人を部門合計・全体合計の人数カウントから除外するか
  （現状、管理者を含めて「本社合計 4人」と表示されている）
- CSV/Excel出力に含めるか

**受け入れ条件**
- [ ] スタッフ編集画面でフラグを設定できる
- [ ] シフト管理画面の一覧から除外される
- [ ] 既存スタッフの表示は変化しない"

############################################
# 6/3以降の再実装
############################################
mk "スーパー管理画面（/super-admin）を実装する" reimpl \
"6/3以降に追加された画面。SaaS運営者用の管理画面。

旧本番のビルド成果物から、以下4画面の存在を確認済み。

- \`SuperAdmin/Dashboard.tsx\`
- \`SuperAdmin/Companies.tsx\`
- \`SuperAdmin/NewCompany.tsx\`
- \`SuperAdmin/Users.tsx\`

ルート: \`/super-admin\`, \`/super-admin/companies\`, \`/super-admin/companies/new\`, \`/super-admin/users\`

事業所の削除機能（当初依頼の1点目）はこの画面に載せる。"

mk "FeliCa打刻APIエンドポイントを実装する" reimpl \
"既存のFeliCa打刻アプリ v0.2.6 をそのまま利用できるようにする。

**必要なAPI（復元済みの仕様）**

\`\`\`
POST /stamp/{companyUuid}/felica
Content-Type: application/json

Body : { \"idm\": \"0123456789abcdef\", \"intent\": \"break-start\" }
       intent は休憩開始モード時のみ。通常は省略

成功条件: HTTP 2xx かつ レスポンス body の success === true
\`\`\`

6/3時点のソースには \`/stamp/{uuid}/clock-in\` 等は存在するが、\`felica\` のみ不足。

**DBカラム追加**

\`\`\`sql
ALTER TABLE users
  ADD COLUMN felica_idm VARCHAR(16) DEFAULT NULL,
  ADD COLUMN felica_registered_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE users ADD UNIQUE KEY uk_users_felica_idm (felica_idm);
\`\`\`

詳細は \`_recovery/README.md\` および \`_recovery/felica-app-src/src/stamp-api.js\` を参照。"

mk "スタッフ編集画面にFeliCaカード登録UIを追加する" reimpl \
"\`felica_idm\` をスタッフに紐づけるための管理画面UI。カードをかざして登録する導線。"

mk "Excel出力：テンプレートの計算式を維持する" reimpl \
"PhpSpreadsheet で全セルに値を書き込むとテンプレートの数式が消えるため、
**データ範囲のみ書き込み、数式セルには触れない**方式にする（チャットで案Aを採用済み）。

実物の出力ファイルから復元した仕様は \`_recovery/README.md\` を参照。
サマリ欄には備考・申請列を解析する SUMPRODUCT 式が19本入っている。"

mk "Excel/CSV出力：10名単位のZip分割と一括ダウンロード" reimpl \
"人数が多い場合のサーバー負荷対策。チャットでは案A+案Bの併用で合意。

- 案A: 10名ごとのバッチ指定ダウンロード
- 案B: 一括DLボタン → 内部で10名ずつ生成 → Zipにまとめて返却"

mk "休憩2枠対応" reimpl \
"帳票および画面で休憩を2枠まで扱えるようにする（休憩入①/出①/休憩入②/出②）。"

mk "一覧のヘッダー固定表示" reimpl "スクロール時にヘッダー行を固定する。"

mk "日跨ぎ勤務の遅刻・早退・残業計算ロジック" reimpl \
"日付をまたぐ勤務における遅刻・早退・残業の判定を修正する。"

mk "夜勤の24時間ルールと打刻漏れ判定" reimpl \
"日跨ぎ夜勤の出退勤紐付け計算、打刻漏れ時の制御・判定ロジック。"

mk "重複打刻の防止ロック" reimpl "短時間に連続でカードをかざした場合の多重打刻を防ぐ。"

mk "休憩開始モード（intent=break-start）" reimpl \
"打刻画面に休憩開始モードを設ける。FeliCaアプリ側は \`intent: \"break-start\"\` を送信する実装になっている。"

mk "従業員側画面への労務アラート表示" reimpl "スタッフ画面に労務アラートを表示する。"

mk "月間サマリの表示項目を整理する" reimpl "月間サマリの項目構成を見直す。"

mk "打刻修正・申請表示の挙動改善" reimpl "打刻修正および申請の表示まわりの改善。"

mk "打刻レスポンスの高速化" reimpl "打刻処理のレスポンス改善。"

mk "管理者用のスタッフ検索（名前・個人コード）" reimpl "管理画面のスタッフ一覧に検索機能を追加する。"

mk "データ表示期間を過去66ヶ月に拡張する" reimpl "過去5.5年分のデータを参照できるようにする。"

mk "生打刻時刻と丸め時刻の役割を分離する" reimpl \
"画面表示は生の打刻時刻、内部計算は丸め時刻（15分単位等）という役割分担を明確にする。"

mk "休暇申請の承認時に自動減算・表示する（B機能）" reimpl \
"シフト管理画面において、休暇申請が承認された際に残日数を自動で減算し表示に反映する。
※ 旧契約では追加機能として4万円で発注された項目。"

mk "シフト設定に合わせた未打刻時休憩の自動入力（トグル付き）" reimpl \
"打刻が無い場合にシフト設定の休憩を自動補完する。トグルでON/OFF切り替え。

付随する表示仕様:
- 自動入力された休憩はグレー表示
- 15分丸め処理
- 片方だけ打刻がある場合の表示（例: \`〜 : 12:58\`）
- 紫色の「休憩漏れ」バッジ

※ 旧契約では追加機能として5万円で発注された項目。"

mk "アラートバッジと警告トースト（退勤忘れ・未計算）" reimpl \
"「退勤忘れ」「未計算」等の状態をバッジとトーストで通知する。"

mk "打刻修正バッジの視認性改善" reimpl \
"オレンジ色の時刻をクリックすると修正履歴を参照できるようにする。"

mk "スタッフ呼称の統一（ユーザー→スタッフ）" reimpl "画面上の表記を「スタッフ」に統一する。"

mk "スタッフ画面シフトテーブルへの休暇バッジと部門集計" reimpl \
"スタッフ側のシフト表に休暇バッジを表示し、部門ごとの集計を追加する。"

mk "夜勤登録時の休憩時間範囲の検証" reimpl "範囲外の休憩時間が登録された場合のエラー処理。"

mk "打刻トーストの文言調整" reimpl "例:「退勤（日付越え）を記録しました」。"

mk "CSV/Excel出力の最適化（片方打刻休憩・休日区分）" reimpl \
"片方だけ打刻された休憩の扱い、休日区分の出力形式を調整する。"

mk "LPサブドメイン（lp.clog-work.jp）のDNS設定" reimpl \
"ランディングページ用サブドメイン。**現在はレンタルサーバー（sv311）側でWordPressが稼働しており、移行対象外。**
新VPSへの切り替え時にDNSが影響を受けないよう注意する。"

############################################
# 移行
############################################
mk "FeliCa打刻端末の設定変更とカード再登録" migration \
"新サーバーでは会社のUUIDが変わるため、端末ごとにアプリの設定を更新する必要がある。

- アプリの設定画面で \`serverUrl\` と \`companyUuid\` を入れ直す（再インストールは不要）
- \`felica_idm\` は旧DBから取り出せないため、**全カードの再登録が必要**

設定は \`electron-store\`（\`felica-stamp-config\`）に保存される。"

mk "旧環境からのデータ移行" migration \
"旧VPSにはSSHでアクセスできないため、DBダンプは取得できない。
管理画面からCSV/Excelで出力できる範囲のみ移行を試みる。

- シフト
- 勤務実績
- スタッフ一覧

**移行できないもの**: パスワード（ハッシュ）、FeliCaカード登録、打刻履歴の全量。

旧VPSは移行完了まで削除しないこと。"

mk "旧環境の設定値を新環境に再現する" migration \
"管理画面の設定画面から値を控えて再設定する。

- 労務アラートの閾値
- シフトパターン
- 部門
- 打刻の丸め単位
- 給与締切日（**20日**であることは出力帳票の期間から確定済み）

旧VPSを停止する前にスクリーンショットで記録しておく。"

echo "完了"

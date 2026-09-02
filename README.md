# clog-work（勤怠管理システム）

打刻・シフト・勤務実績・各種申請をまとめて管理するWebアプリケーションです。

## 主な機能

**打刻**
- Web打刻（ログイン不要の専用画面。会社ごとのURLを共有して使う）
- FeliCaカード打刻（常駐アプリからAPI経由で記録）
- 出勤・退勤・休憩（2枠まで）／日跨ぎの夜勤に対応
- 打刻時刻の丸め（5〜30分単位）。表示は実打刻、計算は丸め後

**シフト管理**
- 部署ごとのシフト表。パターンの一括反映
- 承認済みの有給・特別休暇をセルに表示し、出勤予定人数から差し引く

**勤務実績**
- 打刻から労働時間・時間外・休日・深夜・遅刻早退を自動集計
- 管理者による打刻の登録・修正（履歴が残る）
- Excel・CSV出力。人数が多い場合は10名単位で分割できる

**申請と承認**
- 有給（1日・半日・時間）、特別休暇、欠勤、遅刻、早退、残業、打刻修正など
- 承認すると勤務実績へ自動反映

**その他**
- 労務アラート（残業の注意・限度時間の通知）
- 事業所単位のマルチテナント。運営者向けのスーパー管理画面

## 技術構成

| 区分 | 使用技術 |
| --- | --- |
| バックエンド | PHP 8.2以上 / Laravel 12 |
| フロントエンド | React 19 / TypeScript / Inertia.js v2 / Tailwind CSS 3 |
| データベース | MySQL 8.0 |
| 開発環境 | Laravel Sail（Docker） |
| テスト | PHPUnit 11 |

## セットアップ

### Docker（推奨）

```bash
git clone git@github.com:koppuchan/clog-work.git
cd clog-work
cp .env.example .env
```

`.env` にホストのUIDとGIDを設定します。合わせておかないと権限エラーになります。

```bash
id -u   # WWWUSER に設定
id -g   # WWWGROUP に設定
```

```bash
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

`http://localhost` で開きます。

### Dockerを使わない場合

PHP 8.2以上、Node.js、MySQLを用意したうえで実行します。

```bash
composer setup   # 依存関係、.env、マイグレーション、ビルドまで一括
composer dev     # 開発サーバー起動
```

## よく使うコマンド

```bash
./vendor/bin/sail artisan test        # テスト
./vendor/bin/sail php vendor/bin/pint # コード整形
npm run build                         # フロントエンドのビルド
```

### 日次集計

前日分の打刻から勤務実績を集計します。通常はcronで実行します。

```bash
php artisan batch:aggregate-daily-work-summaries
php artisan batch:aggregate-daily-work-summaries --date=2026-06-24 --dry-run
```

### データ移行

旧環境の管理画面から出力したCSVを取り込みます。`--dry-run` を付けると保存せず結果だけ確認できます。

```bash
php artisan migrate:settings <JSONパス> --company=<会社ID>
php artisan migrate:shifts <CSVパス> --company=<会社ID>
php artisan migrate:work-summaries <CSVパス> --company=<会社ID>
```

設定用JSONの書式は `storage/app/migration/old-environment.example.json` を参照してください。

## ディレクトリ構成

```
app/
  Console/Commands/  バッチ・移行コマンド
  Http/Controllers/  リクエストの受け口
  Services/          業務ロジック
  Repositories/      データ取得
  Models/            Eloquentモデル
resources/js/
  Pages/             画面（Admin / Staff / Public / SuperAdmin）
  Components/        共通コンポーネント
  hooks/             画面ロジック
deploy/              本番デプロイ・バックアップ用スクリプト
docs/                設計・コーディング規約
```

## 設計方針

`Controller → Service → Repository → Model` の順に依存します。コントローラに業務ロジックを書かず、データ取得はリポジトリに寄せます。

いくつか守っている決まりごとがあります。

- `Carbon::now()` ではなく `CarbonImmutable::now()` を使う
- `DB::table()` を避け、Eloquentかリポジトリを経由する
- スキーマは `docs/tables/README.md` を参照する

詳細は `docs/backend/CODING_GUIDE.md` にあります。

## デプロイ

`deploy/` にスクリプトを用意しています。データベースのバックアップ取得から、コード反映、マイグレーション、動作確認までを行います。

```bash
attendance-deploy          # main の最新を反映
attendance-deploy <ref>    # 指定したブランチ・タグを反映
attendance-rollback        # 直前の状態へ戻す
attendance-backup-db       # データベースのバックアップのみ
```

手順の詳細は `deploy/README.md` を参照してください。

## メール送信

会社の新規登録、スタッフ招待、パスワード再発行などで送信します。

送信元IPの評価によっては迷惑メール扱いになるため、送信実績のあるSMTPサーバーを中継する構成を推奨します。`.env` の `MAIL_MAILER` とPostfixの `relayhost` を設定するだけで、アプリ側の変更は不要です。

SPF・DKIM・DMARCの設定も合わせて必要です。

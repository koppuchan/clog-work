# deploy

サーバー運用に使うスクリプトと手順。

| スクリプト | 用途 | 配置先 |
| --- | --- | --- |
| `deploy.sh` | リリース | `/usr/local/bin/attendance-deploy` |
| `rollback.sh` | 切り戻し | `/usr/local/bin/attendance-rollback` |
| `backup-db.sh` | 日次バックアップ | `/usr/local/bin/attendance-backup-db` |

## サーバー構成

| 項目 | 値 |
| --- | --- |
| ホスト | 153.115.0.90 |
| OS | Ubuntu 24.04 LTS |
| Web | nginx 1.24 |
| PHP | 8.4（`unix:/run/php/php8.4-fpm.sock`） |
| DB | MySQL 8.0 |
| Node | 22 |
| 配置先 | `/var/www/attendance-web` |
| ドキュメントルート | `/var/www/attendance-web/public` |

## deploy.sh — リリース

```bash
attendance-deploy          # main の最新を反映
attendance-deploy v1.2.0   # タグやブランチ、コミットを指定
```

処理の流れ。

1. 現在のコミットを記録
2. **データベースをバックアップ**（取得できなければ中断）
3. コードを取得（変更がなければ何もせず終了）
4. メンテナンスモードへ
5. `composer install` / `npm ci` / `npm run build`
6. `migrate` とキャッシュ生成、権限の再設定
7. 公開し、`/admin/login` と `/staff/login` が 200 を返すことを確認

**途中で失敗した場合はメンテナンスモードのまま停止します。** 中途半端な状態で公開されるのを避けるためです。原因を確認してから切り戻すか、修正して再実行してください。

最後の応答確認で 200 以外が返った場合は異常終了します。

## rollback.sh — 切り戻し

```bash
attendance-rollback            # 直前のコミットへ
attendance-rollback abc1234    # 指定したコミットへ
```

**コードのみを戻します。データベースは戻しません。**

マイグレーションを含むリリースを戻す場合は、コードを戻したうえでバックアップから復元してください（後述）。

## backup-db.sh — データベースの日次バックアップ


契約プランに自動バックアップが含まれないため、サーバー側で取得する。旧環境と同じく**毎日3時に取得し30日保持**する。

### 導入

```bash
install -m 750 deploy/backup-db.sh /usr/local/bin/attendance-backup-db
printf '0 3 * * * root /usr/local/bin/attendance-backup-db >> /var/log/attendance-backup.log 2>&1\n' \
  > /etc/cron.d/attendance-backup
chmod 644 /etc/cron.d/attendance-backup
```

### 動作

| 項目 | 値 |
| --- | --- |
| 取得先 | `/var/backups/attendance-web/` |
| ファイル名 | `attendance_YYYYMMDD_HHMMSS.sql.gz` |
| 保持期間 | 30日（`RETENTION_DAYS` で変更可） |
| ログ | `/var/log/attendance-backup.log` |

接続情報はアプリの `.env` から読み取るため、DBのパスワードを変更してもスクリプトの修正は不要。

パスワードがプロセス一覧に出ないよう、`mysqldump` へは一時ファイル経由で渡している。取得後に `gzip -t` で壊れていないことを確認し、問題があれば世代の整理を行わずに終了する。

### 手動での取得

```bash
/usr/local/bin/attendance-backup-db
```

### 復元手順

**復元は既存のデータを上書きする。実行前に必ず現在の状態を取得しておくこと。**

```bash
# 1. 現在の状態を退避
/usr/local/bin/attendance-backup-db

# 2. 復元したいファイルを選ぶ
ls -lt /var/backups/attendance-web/

# 3. メンテナンスモードにする
cd /var/www/attendance-web && php artisan down

# 4. 復元
gunzip -c /var/backups/attendance-web/attendance_YYYYMMDD_HHMMSS.sql.gz | mysql attendance

# 5. 解除
php artisan up
```

### 復元できるかの確認

本番に影響を与えずに検証できる。

```bash
LATEST=$(ls -t /var/backups/attendance-web/*.sql.gz | head -1)
mysql -e "DROP DATABASE IF EXISTS restore_test; CREATE DATABASE restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "$LATEST" | mysql restore_test
mysql -N -B restore_test -e "SHOW TABLES;" | wc -l   # 46 になること
mysql -e "DROP DATABASE restore_test;"
```

## setup-mail.sh — メール送信の構築

Gmail は送信ドメイン認証が通らない差出人を拒否する。アプリと同じサーバーから送るため、DKIM 署名を付けたうえで DNS 側の登録が必要になる。

```bash
bash deploy/setup-mail.sh clog-work.jp
```

Postfix と OpenDKIM を導入し、鍵を生成して署名を有効にする。実行すると登録すべき DNS レコード3件が表示される。

### Laravel 側の設定

同一ホストの Postfix へ渡すため `sendmail` を使う。

```
MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS="no-reply@clog-work.jp"
```

`smtp` で `127.0.0.1` に繋ぐと、Postfix が提示する自己署名証明書のホスト名が一致せず STARTTLS で失敗する。同一ホスト内の通信のため TLS は不要。

### 必要な DNS レコード

| 種別 | ホスト名 | 内容 |
| --- | --- | --- |
| TXT | `@` | `v=spf1 ip4:153.115.0.90 ~all` |
| TXT | `mail._domainkey` | `setup-mail.sh` が出力する DKIM 公開鍵 |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:postmaster@clog-work.jp` |

### 逆引き（PTR）レコード

**これが未設定だと、他の設定が正しくても Gmail は受け取らない。**

```
550-5.7.25 The IP address sending this message does not have a PTR record setup
```

VPSパネルの「逆引きホスト名」を `clog-work.jp` に設定すること。DNS 側の A レコードが同じ IP を指している必要がある（正引きと逆引きの一致）。

### 配送の確認

```bash
cd /var/www/attendance-web
php artisan tinker --execute='Mail::raw("test", fn($m) => $m->to("宛先")->subject("test"));'
sleep 3 && tail -20 /var/log/mail.log
```

`status=sent` なら成功。`status=bounced` の場合は同じ行に相手側の応答が記録される。

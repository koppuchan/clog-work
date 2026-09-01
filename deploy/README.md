# deploy

サーバー運用に使うスクリプトと手順。

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

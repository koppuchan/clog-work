#!/usr/bin/env bash
#
# 勤怠管理システム データベースの日次バックアップ
#
# 旧環境では毎日3時に取得し30日保持していた。同じ運用を再現する。
# 契約プランに自動バックアップが含まれないため、サーバー側で取得する。
#
# 配置とcron登録:
#   install -m 750 deploy/backup-db.sh /usr/local/bin/attendance-backup-db
#   echo '0 3 * * * root /usr/local/bin/attendance-backup-db' > /etc/cron.d/attendance-backup
#
set -euo pipefail

APPDIR="${APPDIR:-/var/www/attendance-web}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/attendance-web}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

# .env から接続情報を読む（値のクォートは除去する）
env_value() {
    sed -n "s/^$1=//p" "$APPDIR/.env" | head -1 | tr -d '"' | tr -d "'"
}

DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"
DB_HOST="$(env_value DB_HOST)"

if [ -z "$DB_NAME" ]; then
    echo "[$(date '+%F %T')] .env からデータベース名を取得できませんでした" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

STAMP="$(date '+%Y%m%d_%H%M%S')"
DEST="$BACKUP_DIR/${DB_NAME}_${STAMP}.sql.gz"

# パスワードをコマンドライン引数に出さないよう、一時的な設定ファイル経由で渡す
CNF="$(mktemp)"
trap 'rm -f "$CNF"' EXIT
chmod 600 "$CNF"
cat > "$CNF" <<CONF
[client]
host=${DB_HOST:-127.0.0.1}
user=${DB_USER}
password=${DB_PASS}
CONF

mysqldump --defaults-extra-file="$CNF" \
    --single-transaction --quick --routines --triggers --events --no-tablespaces \
    "$DB_NAME" | gzip -c > "$DEST"

chmod 600 "$DEST"

# 出力が壊れていないか確認してから世代を整理する
if ! gzip -t "$DEST" 2>/dev/null; then
    echo "[$(date '+%F %T')] バックアップが壊れています: $DEST" >&2
    exit 1
fi

SIZE="$(du -h "$DEST" | cut -f1)"
echo "[$(date '+%F %T')] 取得しました: $DEST ($SIZE)"

DELETED="$(find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -mtime "+${RETENTION_DAYS}" -print -delete | wc -l)"
if [ "$DELETED" -gt 0 ]; then
    echo "[$(date '+%F %T')] ${RETENTION_DAYS}日を超えた ${DELETED} 件を削除しました"
fi

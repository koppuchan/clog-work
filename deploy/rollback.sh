#!/usr/bin/env bash
#
# 勤怠管理システム ロールバック
#
#   /usr/local/bin/attendance-rollback <commit>   指定したコミットへコードを戻す
#   /usr/local/bin/attendance-rollback            直前のコミットへ戻す
#
# コードのみを戻す。データベースは戻さない。
# マイグレーションを含むリリースを戻す場合は、
# deploy/README.md の手順でバックアップから復元すること。
#
set -euo pipefail

APPDIR="${APPDIR:-/var/www/attendance-web}"
TARGET="${1:-HEAD~1}"

log() { echo "[$(date '+%F %T')] $*"; }

cd "$APPDIR" || { log "$APPDIR が見つかりません"; exit 1; }

log "現在: $(git log --oneline -1)"
log "戻す先: $(git log --oneline -1 "$TARGET")"

php artisan down --retry=60 || true

git reset --hard --quiet "$TARGET"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --no-audit --no-fund --silent
npm run build

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data "$APPDIR"
chmod -R 775 "$APPDIR/storage" "$APPDIR/bootstrap/cache"

php artisan up

for path in /admin/login /staff/login; do
    log "  ${path} -> $(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1${path}")"
done

log "ロールバック完了: $(git log --oneline -1)"
log "※ データベースは戻していません。必要な場合は deploy/README.md を参照してください。"

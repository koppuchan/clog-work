#!/usr/bin/env bash
#
# 勤怠管理システム デプロイ
#
#   /usr/local/bin/attendance-deploy            main の最新を反映
#   /usr/local/bin/attendance-deploy <ref>      指定したブランチ・タグ・コミットを反映
#
# 反映前にデータベースのバックアップを取得する。
# 途中で失敗した場合はメンテナンスモードのまま停止するので、
# 原因を確認してから rollback.sh で戻すか、修正して再実行する。
#
set -euo pipefail

APPDIR="${APPDIR:-/var/www/attendance-web}"
REF="${1:-main}"
BACKUP_CMD="${BACKUP_CMD:-/usr/local/bin/attendance-backup-db}"

log() { echo "[$(date '+%F %T')] $*"; }
fail() { log "失敗: $*"; exit 1; }

# アプリの所有者は www-data、実行は root のため git の所有者チェックを許可する
git config --global --get-all safe.directory | grep -qx "$APPDIR" \
    || git config --global --add safe.directory "$APPDIR"

cd "$APPDIR" || fail "$APPDIR が見つかりません"

log "===== 1/7 現在の状態 ====="
CURRENT="$(git rev-parse HEAD)"
log "現在のコミット: $(git log --oneline -1)"

log "===== 2/7 データベースのバックアップ ====="
if [ -x "$BACKUP_CMD" ]; then
    "$BACKUP_CMD"
else
    log "バックアップスクリプトが見つかりません。中断します。"
    exit 1
fi

log "===== 3/7 コードを取得 ====="
git fetch --quiet --all --tags
git reset --hard --quiet "origin/${REF}" 2>/dev/null || git reset --hard --quiet "$REF"
log "反映するコミット: $(git log --oneline -1)"

if [ "$CURRENT" = "$(git rev-parse HEAD)" ]; then
    log "変更はありません。終了します。"
    exit 0
fi

log "===== 4/7 メンテナンスモード ====="
php artisan down --render="errors::503" --retry=60 || true
trap 'php artisan up || true' ERR

log "===== 5/7 依存パッケージ ====="
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --no-audit --no-fund --silent
npm run build

log "===== 6/7 マイグレーションとキャッシュ ====="
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data "$APPDIR"
chmod -R 775 "$APPDIR/storage" "$APPDIR/bootstrap/cache"

log "===== 7/7 公開 ====="
php artisan up
trap - ERR

# 応答を確認する。異常なら気づけるよう終了コードに反映する。
for path in /admin/login /staff/login; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1${path}")"
    log "  ${path} -> ${code}"
    [ "$code" = "200" ] || fail "${path} が ${code} を返しました。rollback.sh の実行を検討してください。"
done

log "完了しました（${CURRENT:0:7} → $(git rev-parse --short HEAD)）"

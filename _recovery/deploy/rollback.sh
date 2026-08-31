#!/usr/bin/env bash
#
# 勤怠管理システム ロールバックスクリプト
#
# 使い方:
#   bash /root/rollback.sh /root/backups/YYYYMMDD_HHMMSS

set -euo pipefail

BKDIR="${1:-}"
APPDIR="${APPDIR:-}"

if [ -z "$BKDIR" ]; then
    echo "使い方: $0 <バックアップディレクトリ>"
    echo ""
    echo "利用可能なバックアップ:"
    ls -lt /root/backups/ 2>/dev/null | head -10
    exit 1
fi

[ -d "$BKDIR" ] || { echo "ERROR: $BKDIR が存在しません"; exit 1; }
[ -f "$BKDIR/code.tar.gz" ] || { echo "ERROR: $BKDIR/code.tar.gz が見つかりません"; exit 1; }
[ -f "$BKDIR/db.sql" ] || { echo "ERROR: $BKDIR/db.sql が見つかりません"; exit 1; }

# APPDIR 自動検出
if [ -z "$APPDIR" ]; then
    DETECTED=$(find /var/www /home /opt -maxdepth 4 -name 'artisan' -type f 2>/dev/null | head -1)
    if [ -n "$DETECTED" ]; then
        APPDIR=$(dirname "$DETECTED")
    else
        APPDIR="/var/www/attendance-web"
    fi
fi

echo "============================================"
echo "  ロールバック実行"
echo "============================================"
echo "  APPDIR : $APPDIR"
echo "  BKDIR  : $BKDIR"
echo "============================================"
read -p "本当にロールバックしますか？ (yes/no): " confirm
[ "$confirm" = "yes" ] || { echo "中止しました"; exit 0; }

# メンテナンスモード
[ -f "$APPDIR/artisan" ] && { cd "$APPDIR" && php artisan down --message="復旧作業中です。" || true; }

# DB 情報を取得
DB_HOST=$(grep '^DB_HOST=' "$APPDIR/.env" | cut -d= -f2-)
DB_NAME=$(grep '^DB_DATABASE=' "$APPDIR/.env" | cut -d= -f2-)
DB_USER=$(grep '^DB_USERNAME=' "$APPDIR/.env" | cut -d= -f2-)
DB_PASS=$(grep '^DB_PASSWORD=' "$APPDIR/.env" | cut -d= -f2- | tr -d '"')

# コード復元
echo ""
echo "[1/2] コード復元 ..."
PARENT=$(dirname "$APPDIR")
TS=$(date +%Y%m%d_%H%M%S)
mv "$APPDIR" "${APPDIR}_rollback_target_$TS"
tar -xzf "$BKDIR/code.tar.gz" -C "$PARENT"
echo "  ✓ コード復元完了"

# DB 復元
echo ""
echo "[2/2] DB 復元 ..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BKDIR/db.sql"
echo "  ✓ DB 復元完了"

# 復旧
cd "$APPDIR"
php artisan up

echo ""
echo "============================================"
echo "  ✅ ロールバック完了"
echo "============================================"
echo ""
echo "  失敗したデプロイのコード: ${APPDIR}_rollback_target_$TS"
echo "  確認後 rm -rf で削除してください"

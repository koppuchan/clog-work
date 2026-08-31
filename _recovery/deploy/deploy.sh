#!/usr/bin/env bash
#
# 勤怠管理システム ワンショットデプロイスクリプト
#
# 使い方:
#   1. /root/attendance-web.zip にデプロイパッケージをアップロード
#   2. このスクリプトを /root/deploy.sh に配置
#   3. chmod +x /root/deploy.sh && /root/deploy.sh
#
# 環境変数で挙動を上書き可能:
#   APPDIR  : アプリ配置先 (default: 自動検出 or /var/www/attendance-web)
#   ZIPFILE : デプロイパッケージ (default: /root/attendance-web.zip)
#   SKIP_BACKUP : 1 にするとバックアップをスキップ（非推奨）

set -euo pipefail

# ──────────────────────────────────────
# 設定
# ──────────────────────────────────────
ZIPFILE="${ZIPFILE:-/root/attendance-web.zip}"
BKROOT="${BKROOT:-/root/backups}"
TS=$(date +%Y%m%d_%H%M%S)
BKDIR="$BKROOT/$TS"

# アプリ配置先の自動検出
if [ -z "${APPDIR:-}" ]; then
    DETECTED=$(find /var/www /home /opt -maxdepth 4 -name 'artisan' -type f 2>/dev/null | head -1)
    if [ -n "$DETECTED" ]; then
        APPDIR=$(dirname "$DETECTED")
    else
        APPDIR="/var/www/attendance-web"
    fi
fi

echo "============================================"
echo "  勤怠管理システム デプロイ"
echo "============================================"
echo "  APPDIR  : $APPDIR"
echo "  ZIPFILE : $ZIPFILE"
echo "  BACKUP  : $BKDIR"
echo "============================================"

# ──────────────────────────────────────
# 前提チェック
# ──────────────────────────────────────
[ -f "$ZIPFILE" ] || { echo "ERROR: $ZIPFILE が見つかりません"; exit 1; }
[ -d "$APPDIR" ] || { echo "ERROR: $APPDIR が存在しません"; exit 1; }
[ -f "$APPDIR/.env" ] || { echo "ERROR: $APPDIR/.env が見つかりません"; exit 1; }
[ -f "$APPDIR/artisan" ] || { echo "ERROR: $APPDIR/artisan が見つかりません（Laravelアプリではない可能性）"; exit 1; }

command -v php >/dev/null || { echo "ERROR: php がインストールされていません"; exit 1; }
command -v composer >/dev/null || { echo "ERROR: composer がインストールされていません"; exit 1; }
command -v npm >/dev/null || { echo "ERROR: npm がインストールされていません"; exit 1; }
command -v mysql >/dev/null || { echo "ERROR: mysql クライアントがインストールされていません"; exit 1; }
command -v unzip >/dev/null || { echo "ERROR: unzip がインストールされていません (apt install unzip)"; exit 1; }

# DB 接続情報を .env から取得
DB_HOST=$(grep '^DB_HOST=' "$APPDIR/.env" | cut -d= -f2-)
DB_NAME=$(grep '^DB_DATABASE=' "$APPDIR/.env" | cut -d= -f2-)
DB_USER=$(grep '^DB_USERNAME=' "$APPDIR/.env" | cut -d= -f2-)
DB_PASS=$(grep '^DB_PASSWORD=' "$APPDIR/.env" | cut -d= -f2- | tr -d '"')

# ──────────────────────────────────────
# Step 1: メンテナンスモード ON
# ──────────────────────────────────────
echo ""
echo "[1/7] メンテナンスモード ON ..."
cd "$APPDIR"
php artisan down --message="システムメンテナンス中です。10〜15分ほどで復旧予定です。" --retry=60 || true

# ──────────────────────────────────────
# Step 2: バックアップ
# ──────────────────────────────────────
echo ""
echo "[2/7] バックアップ取得中 ..."
if [ "${SKIP_BACKUP:-0}" != "1" ]; then
    mkdir -p "$BKDIR"

    # コード
    echo "  - コード ($APPDIR → $BKDIR/code.tar.gz)"
    tar -czf "$BKDIR/code.tar.gz" -C "$(dirname $APPDIR)" "$(basename $APPDIR)"

    # DB
    echo "  - DB ($DB_NAME → $BKDIR/db.sql)"
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BKDIR/db.sql"

    echo "  ✓ バックアップ完了: $BKDIR"
    ls -lh "$BKDIR"
else
    echo "  ! SKIP_BACKUP=1 のためバックアップをスキップしました"
fi

# ──────────────────────────────────────
# Step 3: 新コード展開（旧コードは退避）
# ──────────────────────────────────────
echo ""
echo "[3/7] 新コード展開中 ..."

OLD_APP="${APPDIR}_old_$TS"
mv "$APPDIR" "$OLD_APP"
mkdir -p "$APPDIR"
cd "$APPDIR"
unzip -q "$ZIPFILE"

# .env を引き継ぎ
cp "$OLD_APP/.env" "$APPDIR/.env"

# storage 配下の永続データを引き継ぎ
if [ -d "$OLD_APP/storage/app/public" ]; then
    cp -r "$OLD_APP/storage/app/public" "$APPDIR/storage/app/" 2>/dev/null || true
fi
if [ -d "$OLD_APP/storage/logs" ]; then
    mkdir -p "$APPDIR/storage/logs"
fi

# 権限
WWWUSER=$(stat -c '%U' "$OLD_APP/artisan" 2>/dev/null || echo www-data)
WWWGROUP=$(stat -c '%G' "$OLD_APP/artisan" 2>/dev/null || echo www-data)
chown -R "$WWWUSER:$WWWGROUP" "$APPDIR"
find "$APPDIR" -type d -exec chmod 755 {} \;
find "$APPDIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$APPDIR/storage" "$APPDIR/bootstrap/cache"

echo "  ✓ 新コード配置完了"

# ──────────────────────────────────────
# Step 4: 依存パッケージ
# ──────────────────────────────────────
echo ""
echo "[4/7] PHP/Node 依存パッケージのインストール ..."
cd "$APPDIR"

su - "$WWWUSER" -s /bin/bash -c "cd $APPDIR && composer install --no-dev --optimize-autoloader --no-interaction" 2>&1 | tail -5 || \
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5

npm ci --no-audit --no-fund 2>&1 | tail -3
npm run build 2>&1 | tail -3

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "  ✓ パッケージインストール完了"

# ──────────────────────────────────────
# Step 5: DB スキーマ更新（冪等）
# ──────────────────────────────────────
echo ""
echo "[5/7] DB スキーマ更新 ..."

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<'SQL'
ALTER TABLE users
ADD COLUMN IF NOT EXISTS felica_idm VARCHAR(16) DEFAULT NULL COMMENT 'FeliCa IDm（16進数16桁）',
ADD COLUMN IF NOT EXISTS felica_registered_at TIMESTAMP NULL DEFAULT NULL COMMENT 'FeliCaカード登録日時';
SQL

# ユニークキーは IF NOT EXISTS 構文が無いので存在チェック
HAS_UK=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='$DB_NAME' AND table_name='users' AND index_name='uk_users_felica_idm';")
if [ "$HAS_UK" = "0" ]; then
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE users ADD UNIQUE KEY uk_users_felica_idm (felica_idm);"
    echo "  ✓ uk_users_felica_idm ユニークキー追加"
else
    echo "  ✓ uk_users_felica_idm ユニークキーは既に存在"
fi

echo "  ✓ DB スキーマ更新完了"

# ──────────────────────────────────────
# Step 6: メンテナンスモード OFF
# ──────────────────────────────────────
echo ""
echo "[6/7] メンテナンスモード OFF ..."
cd "$APPDIR"
php artisan up

# ──────────────────────────────────────
# Step 7: スモークテスト
# ──────────────────────────────────────
echo ""
echo "[7/7] スモークテスト ..."

for path in "/admin/login" "/staff/login"; do
    CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost$path" || echo "000")
    if [ "$CODE" = "200" ]; then
        echo "  ✓ $path → HTTP $CODE"
    else
        echo "  ✗ $path → HTTP $CODE (要確認)"
    fi
done

echo ""
echo "============================================"
echo "  ✅ デプロイ完了"
echo "============================================"
echo ""
echo "  バックアップ: $BKDIR"
echo "  旧コード退避先: $OLD_APP"
echo ""
echo "  問題があればロールバック:"
echo "    bash /root/rollback.sh $BKDIR"
echo ""
echo "  古い退避を削除して容量を空ける場合:"
echo "    rm -rf $OLD_APP"
echo ""

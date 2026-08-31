# 勤怠管理システム デプロイ手順書

**作成日**: 2026-06-01
**対象環境**: XServer VPS (cbasakura / 162.43.90.89)
**新ドメイン**: `clog-work.jp`
**既存ドメイン**: `attendance-demo-2026.xvps.jp`（併存・自動継続）

---

## 📦 デプロイパッケージ

`C:\勤怠管理\deploy-package\attendance-web.zip` (約 2.6 MB)

> 含まれていないもの（サーバ側で `composer install` / `npm install` で再生成）:
> - `vendor/`（PHP依存パッケージ）
> - `node_modules/`（npm依存パッケージ）
> - `.env`（環境別に異なるため）
> - `storage/logs/`（ログ）

---

## 🔐 Step 0: SSH を有効化（クライアント様の作業）

**XServer VPS パネル**にログイン → コンソールを開いて以下を root で実行:

```bash
# 1. SSH パスワード認証を有効化
sed -i.bak 's/^#*PermitRootLogin.*/PermitRootLogin yes/' /etc/ssh/sshd_config
sed -i.bak 's/^#*PasswordAuthentication.*/PasswordAuthentication yes/' /etc/ssh/sshd_config

# 2. SSH 再起動
systemctl restart sshd

# 3. 確認
echo "SSH 設定変更完了"
sshd -T | grep -iE 'permitrootlogin|passwordauthentication'
```

期待出力:
```
permitrootlogin yes
passwordauthentication yes
SSH 設定変更完了
```

---

## 🔍 Step 1: SSH 接続確認

ローカルPC（Windows）から:

```powershell
$plink = 'C:\Program Files\PuTTY\plink.exe'
$pwFile = 'C:\temp\sshpw.txt'
[System.IO.File]::WriteAllText($pwFile, '<REDACTED>')
& $plink -ssh root@162.43.90.89 -pwfile $pwFile -hostkey "ssh-ed25519 255 <REDACTED>" "echo OK && uname -a"
Remove-Item $pwFile -Force
```

`OK` と OS情報が表示されれば接続成功。

---

## 🔎 Step 2: サーバ環境調査

```bash
# OS バージョン
cat /etc/os-release | grep -E 'PRETTY_NAME|VERSION_ID'

# PHP / Composer / Node / MySQL の確認
php -v
composer -V
node -v
npm -v
mysql --version

# 現行アプリの場所を探す
find / -name 'artisan' -type f 2>/dev/null | head -5

# Web サーバ
systemctl status nginx --no-pager | head -10
systemctl status apache2 --no-pager | head -10
systemctl status php*-fpm --no-pager 2>/dev/null | head -10

# DB 設定（root が空パスでログインできるか確認）
mysql -u root -e "SHOW DATABASES;" 2>&1 | head -10

# 既存 .env の確認（DB 情報を取得）
find / -name '.env' -path '*/attendance*' 2>/dev/null
```

---

## 💾 Step 3: 完全バックアップ

### 3.1 既存コード
```bash
APPDIR="/var/www/attendance-web"  # 実際の配置パスに置き換え
BKDIR="/root/backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p $BKDIR

# コードバックアップ
tar -czf $BKDIR/code.tar.gz -C $(dirname $APPDIR) $(basename $APPDIR)
echo "Code backup: $BKDIR/code.tar.gz"
ls -lh $BKDIR/code.tar.gz
```

### 3.2 データベース
```bash
# DB情報を取得（.env から）
cd $APPDIR
DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d= -f2)
DB_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2)
DB_PASS=$(grep '^DB_PASSWORD=' .env | cut -d= -f2)

# ダンプ
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > $BKDIR/db.sql
echo "DB backup: $BKDIR/db.sql"
ls -lh $BKDIR/db.sql
```

---

## 🚀 Step 4: 新コードを反映

### 4.1 アップロード（ローカルから）

ローカル PC で:
```powershell
$pwFile = 'C:\temp\sshpw.txt'
[System.IO.File]::WriteAllText($pwFile, '<REDACTED>')
& 'C:\Program Files\PuTTY\pscp.exe' -pwfile $pwFile -hostkey "ssh-ed25519 255 <REDACTED>" 'C:\勤怠管理\deploy-package\attendance-web.zip' root@162.43.90.89:/root/
Remove-Item $pwFile -Force
```

### 4.2 展開・置き換え（サーバ側）

```bash
# メンテナンスモード ON（既存アプリで）
APPDIR="/var/www/attendance-web"
cd $APPDIR
php artisan down --message="システムメンテナンス中です。10〜15分ほどで復旧予定です。" --retry=60 || true

# 旧コードを退避
mv $APPDIR ${APPDIR}_old_$(date +%Y%m%d_%H%M%S)

# 新コードを展開
mkdir -p $APPDIR
cd $APPDIR
unzip -q /root/attendance-web.zip

# 既存の .env を引き継ぐ
cp ${APPDIR}_old_*/.env $APPDIR/.env

# storage/ と bootstrap/cache を引き継ぐ（権限維持）
cp -r ${APPDIR}_old_*/storage/app $APPDIR/storage/ 2>/dev/null || true
chown -R www-data:www-data $APPDIR
chmod -R 775 $APPDIR/storage $APPDIR/bootstrap/cache
```

### 4.3 依存パッケージのインストール

```bash
cd $APPDIR

# Composer
composer install --no-dev --optimize-autoloader --no-interaction

# Node ビルド
npm ci --no-audit --no-fund
npm run build

# キャッシュクリア
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### 4.4 DB スキーマ更新（FeliCa 用カラム追加）

```bash
cd $APPDIR

# FeliCa 用カラムが存在しないことを確認してから追加
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<'SQL'
ALTER TABLE users
ADD COLUMN IF NOT EXISTS felica_idm VARCHAR(16) DEFAULT NULL COMMENT 'FeliCa IDm（16進数16桁）',
ADD COLUMN IF NOT EXISTS felica_registered_at TIMESTAMP NULL DEFAULT NULL COMMENT 'FeliCaカード登録日時';

-- ユニークキー追加（既に存在する場合は無視）
ALTER TABLE users ADD UNIQUE KEY uk_users_felica_idm (felica_idm);
SQL

# 確認
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE users;" | grep felica
```

### 4.5 メンテナンスモード OFF + 動作確認

```bash
cd $APPDIR
php artisan up
echo "メンテナンスモード解除"

# スモークテスト
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost/admin/login
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost/staff/login
```

両方 200 が返れば成功。

---

## 🌐 Step 5: 新ドメイン `clog-work.jp` の設定

### 5.1 DNS設定（XServer ドメイン管理画面で）

XServer のドメイン管理 → `clog-work.jp` → DNS設定 → 以下を追加:

```
種別: A
ホスト: @
内容: 162.43.90.89
TTL:  3600
```

```
種別: A
ホスト: www
内容: 162.43.90.89
TTL:  3600
```

反映まで通常 1〜3 時間（最大 24 時間）。

### 5.2 Nginx 設定追加

既存の `attendance-demo-2026.xvps.jp` の設定ファイルを複製:

```bash
# 既存設定の場所を特定
ls /etc/nginx/sites-available/
ls /etc/nginx/conf.d/

# 例: /etc/nginx/sites-available/attendance-demo-2026.xvps.jp.conf があると仮定
SRC="/etc/nginx/sites-available/attendance-demo-2026.xvps.jp.conf"
DST="/etc/nginx/sites-available/clog-work.jp.conf"

cp $SRC $DST

# ドメイン名を新ドメインに置き換え
sed -i 's/attendance-demo-2026.xvps.jp/clog-work.jp/g' $DST

# 有効化
ln -sf $DST /etc/nginx/sites-enabled/

# 構文チェック
nginx -t

# 反映
systemctl reload nginx
```

### 5.3 SSL 証明書取得（Let's Encrypt）

DNS が反映されてから実行:

```bash
# certbot が無ければインストール
which certbot || apt install -y certbot python3-certbot-nginx

# 証明書取得（メールアドレスは適宜変更）
certbot --nginx -d clog-work.jp -d www.clog-work.jp --non-interactive --agree-tos -m admin@clog-work.jp --redirect

# 自動更新の確認
systemctl status certbot.timer --no-pager
```

### 5.4 アプリ側の APP_URL 設定

```bash
cd $APPDIR
# .env の APP_URL を変更
sed -i 's|^APP_URL=.*|APP_URL=https://clog-work.jp|' .env
php artisan config:clear
```

### 5.5 動作確認

```bash
curl -sI https://clog-work.jp/admin/login | head -5
```

`HTTP/2 200` または `HTTP/1.1 200` が返れば成功。

---

## 🔄 Step 6: 旧URL継続 or リダイレクト

**選択肢A: 両方併存（推奨）**
- 何もしない。旧URLからも引き続きアクセス可能

**選択肢B: 旧URLから新URLへ自動リダイレクト**
- Nginx の旧URL設定で `return 301 https://clog-work.jp$request_uri;`

---

## 🆘 ロールバック手順

何か問題が発生した場合:

```bash
APPDIR="/var/www/attendance-web"
BKDIR="/root/backup_YYYYMMDD_HHMMSS"  # 実際のバックアップディレクトリに置換

# アプリ停止
cd $APPDIR && php artisan down

# コード復元
rm -rf $APPDIR
mkdir -p $APPDIR
tar -xzf $BKDIR/code.tar.gz -C $(dirname $APPDIR)

# DB復元
DB_NAME=$(grep '^DB_DATABASE=' $APPDIR/.env | cut -d= -f2)
DB_USER=$(grep '^DB_USERNAME=' $APPDIR/.env | cut -d= -f2)
DB_PASS=$(grep '^DB_PASSWORD=' $APPDIR/.env | cut -d= -f2)
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < $BKDIR/db.sql

# 復旧
cd $APPDIR && php artisan up

echo "ロールバック完了"
```

---

## ✅ 完了チェックリスト

- [ ] SSH接続確認（Step 1）
- [ ] サーバ環境調査（Step 2）
- [ ] バックアップ取得（Step 3）
- [ ] コード反映（Step 4.1 〜 4.3）
- [ ] DB スキーマ更新（Step 4.4）
- [ ] 動作確認: `attendance-demo-2026.xvps.jp` で各画面正常表示（Step 4.5）
- [ ] 新ドメイン DNS 反映確認（Step 5.1）
- [ ] Nginx 設定追加（Step 5.2）
- [ ] SSL 証明書取得（Step 5.3）
- [ ] `https://clog-work.jp` で動作確認（Step 5.5）
- [ ] FeliCa 連携の動作確認（管理画面ユーザ編集にカード登録UIあるか）
- [ ] クライアント様への完了報告
- [ ] **クライアント様にパスワード変更を依頼**

---

## 📝 注意事項

1. **作業中はメンテナンスモード**で他ユーザーのアクセスを止める
2. **バックアップは必ず取得**してから作業に入る
3. **DB スキーマ変更は IF NOT EXISTS で冪等化済み**（複数回実行しても安全）
4. **問題発生時は迷わずロールバック**
5. **作業完了後、すべてのパスワードを変更**するようクライアント様に依頼

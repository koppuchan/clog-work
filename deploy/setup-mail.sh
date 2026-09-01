#!/usr/bin/env bash
#
# メール送信の構築（Postfix + OpenDKIM）
#
#   bash setup-mail.sh clog-work.jp
#
# Gmail は送信ドメイン認証（SPF または DKIM）が通らない差出人を拒否する。
# アプリと同じサーバーから送るため、DKIM 署名を付けたうえで
# SPF / DMARC を DNS に登録する必要がある。
#
# 実行後、表示される3つのレコードを DNS へ登録すること。
#
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

DOMAIN="${1:?ドメインを指定してください（例: clog-work.jp）}"
SELECTOR="${SELECTOR:-mail}"
IP="$(curl -s -4 --max-time 10 ifconfig.me || echo '')"

echo "===== パッケージ導入 ====="
apt-get update -qq
apt-get install -y -qq postfix opendkim opendkim-tools >/dev/null

echo "===== OpenDKIM の鍵を生成 ====="
install -d -o opendkim -g opendkim -m 750 "/etc/opendkim/keys/${DOMAIN}"
if [ ! -f "/etc/opendkim/keys/${DOMAIN}/${SELECTOR}.private" ]; then
    opendkim-genkey -b 2048 -d "$DOMAIN" -D "/etc/opendkim/keys/${DOMAIN}" -s "$SELECTOR" -v >/dev/null 2>&1
    chown opendkim:opendkim "/etc/opendkim/keys/${DOMAIN}/${SELECTOR}.private"
    chmod 600 "/etc/opendkim/keys/${DOMAIN}/${SELECTOR}.private"
fi

echo "===== OpenDKIM の設定 ====="
cat > /etc/opendkim.conf <<CONF
Syslog                  yes
UMask                   007
Mode                    sv
Canonicalization        relaxed/simple
OversignHeaders         From
Socket                  inet:8891@localhost
PidFile                 /run/opendkim/opendkim.pid
UserID                  opendkim
KeyTable                /etc/opendkim/key.table
SigningTable            refile:/etc/opendkim/signing.table
ExternalIgnoreList      /etc/opendkim/trusted.hosts
InternalHosts           /etc/opendkim/trusted.hosts
CONF

echo "${SELECTOR}._domainkey.${DOMAIN} ${DOMAIN}:${SELECTOR}:/etc/opendkim/keys/${DOMAIN}/${SELECTOR}.private" > /etc/opendkim/key.table
echo "*@${DOMAIN} ${SELECTOR}._domainkey.${DOMAIN}" > /etc/opendkim/signing.table
printf '127.0.0.1\nlocalhost\n%s\n%s\n' "$IP" "$DOMAIN" > /etc/opendkim/trusted.hosts
chown -R opendkim:opendkim /etc/opendkim
chmod 640 /etc/opendkim/key.table /etc/opendkim/signing.table /etc/opendkim/trusted.hosts

echo "===== Postfix の設定 ====="
postconf -e "myhostname = ${DOMAIN}"
postconf -e "mydomain = ${DOMAIN}"
postconf -e "myorigin = \$mydomain"
postconf -e "inet_interfaces = loopback-only"
postconf -e "mydestination = localhost"
postconf -e "smtpd_milters = inet:localhost:8891"
postconf -e "non_smtpd_milters = inet:localhost:8891"
postconf -e "milter_default_action = accept"
postconf -e "milter_protocol = 6"

systemctl enable --now opendkim >/dev/null 2>&1
systemctl restart opendkim
systemctl restart postfix

echo "===== 状態 ====="
systemctl is-active opendkim postfix | tr '\n' ' '; echo

echo
echo "=============================================================="
echo " DNS に以下の3件を登録してください"
echo "=============================================================="
echo
echo "【1】SPF   種別: TXT   ホスト名: @（${DOMAIN}）"
echo "     v=spf1 ip4:${IP} ~all"
echo
echo "【2】DKIM  種別: TXT   ホスト名: ${SELECTOR}._domainkey"
# 括弧内の引用符付き文字列だけを取り出し、DNS に貼れる1行にする
awk 'BEGIN{RS=")"} NR==1' "/etc/opendkim/keys/${DOMAIN}/${SELECTOR}.txt" \
    | grep -o '"[^"]*"' | tr -d '"\n' | sed 's/[[:space:]]\+//g; s/^/     /'
echo
echo
echo "【3】DMARC 種別: TXT   ホスト名: _dmarc"
echo "     v=DMARC1; p=none; rua=mailto:postmaster@${DOMAIN}"
echo
echo "=============================================================="
echo " あわせて、VPSパネルで逆引きホスト名を ${DOMAIN} に設定してください"
echo "=============================================================="

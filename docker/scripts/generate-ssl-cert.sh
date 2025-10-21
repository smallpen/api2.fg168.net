#!/bin/bash
# 生成自簽 SSL 憑證（僅用於開發和測試環境）
# Production 環境請使用 Let's Encrypt 或其他 CA 簽發的憑證

SSL_DIR="./docker/nginx/ssl"
DAYS=365
COUNTRY="TW"
STATE="Taiwan"
CITY="Taipei"
ORG="Dynamic API Manager"
CN="localhost"

# 創建 SSL 目錄
mkdir -p "$SSL_DIR"

# 生成私鑰和自簽憑證
openssl req -x509 -nodes -days $DAYS -newkey rsa:2048 \
    -keyout "$SSL_DIR/key.pem" \
    -out "$SSL_DIR/cert.pem" \
    -subj "/C=$COUNTRY/ST=$STATE/L=$CITY/O=$ORG/CN=$CN"

# 設定權限
chmod 600 "$SSL_DIR/key.pem"
chmod 644 "$SSL_DIR/cert.pem"

echo "SSL 憑證已生成於 $SSL_DIR"
echo "注意：這是自簽憑證，僅適用於開發和測試環境"
echo "Production 環境請使用正式的 SSL 憑證"

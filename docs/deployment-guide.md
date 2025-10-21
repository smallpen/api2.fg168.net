# 部署指南

本文檔說明如何將 Dynamic API Manager 部署到 Production 環境。

## 目錄

1. [環境需求](#環境需求)
2. [部署前準備](#部署前準備)
3. [Docker 部署](#docker-部署)
4. [SSL 憑證設定](#ssl-憑證設定)
5. [環境變數配置](#環境變數配置)
6. [資料庫初始化](#資料庫初始化)
7. [效能優化](#效能優化)
8. [安全加固](#安全加固)
9. [監控和日誌](#監控和日誌)
10. [備份策略](#備份策略)

## 環境需求

### 硬體需求

- **CPU**: 最少 2 核心，建議 4 核心以上
- **記憶體**: 最少 4GB，建議 8GB 以上
- **硬碟**: 最少 20GB，建議 50GB 以上（SSD 優先）

### 軟體需求

- **作業系統**: Linux (Ubuntu 20.04+ / CentOS 8+ / Debian 11+)
- **Docker**: 20.10+
- **Docker Compose**: 2.0+
- **Git**: 2.0+

## 部署前準備

### 1. 克隆專案

```bash
git clone <repository-url>
cd dynamic-api-manager
```

### 2. 生成 SSL 憑證

#### 使用 Let's Encrypt（推薦）

```bash
# 安裝 Certbot
sudo apt-get update
sudo apt-get install certbot

# 生成憑證
sudo certbot certonly --standalone -d api.example.com

# 複製憑證到專案目錄
sudo cp /etc/letsencrypt/live/api.example.com/fullchain.pem docker/nginx/ssl/cert.pem
sudo cp /etc/letsencrypt/live/api.example.com/privkey.pem docker/nginx/ssl/key.pem
sudo chmod 644 docker/nginx/ssl/cert.pem
sudo chmod 600 docker/nginx/ssl/key.pem
```

#### 使用自簽憑證（僅測試環境）

```bash
chmod +x docker/scripts/generate-ssl-cert.sh
./docker/scripts/generate-ssl-cert.sh
```

### 3. 配置環境變數

```bash
# 複製環境變數範例檔案
cp .env.production .env.production.local

# 編輯環境變數
nano .env.production.local
```

必須設定的環境變數：

- `APP_KEY`: 使用 `php artisan key:generate` 生成
- `DB_PASSWORD`: 資料庫密碼
- `DB_ROOT_PASSWORD`: 資料庫 root 密碼
- `REDIS_PASSWORD`: Redis 密碼
- `JWT_SECRET`: JWT 密鑰

## Docker 部署

### 1. 建構 Docker 映像

```bash
# 建構 Production 映像
docker-compose -f docker-compose.prod.yml build --no-cache
```

### 2. 啟動服務

```bash
# 啟動所有服務
docker-compose -f docker-compose.prod.yml up -d

# 檢查服務狀態
docker-compose -f docker-compose.prod.yml ps

# 查看日誌
docker-compose -f docker-compose.prod.yml logs -f
```

### 3. 驗證部署

```bash
# 檢查健康狀態
curl http://localhost/health

# 檢查 HTTPS
curl https://api.example.com/health
```

## SSL 憑證設定

### 自動更新 Let's Encrypt 憑證

創建 Cron Job 自動更新憑證：

```bash
# 編輯 crontab
sudo crontab -e

# 添加以下行（每天凌晨 2 點檢查並更新憑證）
0 2 * * * certbot renew --quiet && cp /etc/letsencrypt/live/api.example.com/fullchain.pem /path/to/project/docker/nginx/ssl/cert.pem && cp /etc/letsencrypt/live/api.example.com/privkey.pem /path/to/project/docker/nginx/ssl/key.pem && docker-compose -f /path/to/project/docker-compose.prod.yml restart nginx
```

## 環境變數配置

### Production 環境變數範例

```env
# 應用程式設定
APP_NAME="Dynamic API Manager"
APP_ENV=production
APP_KEY=base64:your-generated-key-here
APP_DEBUG=false
APP_URL=https://api.example.com

# 資料庫設定
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=api_manager_prod
DB_USERNAME=api_user_prod
DB_PASSWORD=your-secure-password-here
DB_ROOT_PASSWORD=your-secure-root-password-here

# Redis 設定
REDIS_HOST=redis
REDIS_PASSWORD=your-redis-password-here
REDIS_PORT=6379

# 快取設定
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# 日誌設定
LOG_CHANNEL=stack
LOG_LEVEL=warning

# JWT 設定
JWT_SECRET=your-jwt-secret-here
JWT_TTL=60
JWT_REFRESH_TTL=20160

# 安全設定
CORS_ALLOWED_ORIGINS=https://admin.example.com,https://app.example.com
IP_WHITELIST_ADMIN=192.168.1.0/24,10.0.0.1
SQL_INJECTION_PROTECTION=true
XSS_PROTECTION=true
SECURITY_HEADERS_ENABLED=true
HSTS_ENABLED=true
```

## 資料庫初始化

### 1. 執行 Migration

```bash
# 進入應用容器
docker-compose -f docker-compose.prod.yml exec app bash

# 執行 Migration
php artisan migrate --force

# 執行 Seeder（建立初始資料）
php artisan db:seed --force
```

### 2. 優化資料庫

```bash
# 執行索引優化 Migration
php artisan migrate --path=database/migrations/2025_10_21_000001_optimize_database_indexes.php --force

# 分析資料表
php artisan db:analyze
```

## 效能優化

### 1. Laravel 優化

```bash
# 進入應用容器
docker-compose -f docker-compose.prod.yml exec app bash

# 快取配置
php artisan config:cache

# 快取路由
php artisan route:cache

# 快取視圖
php artisan view:cache

# 優化 Composer autoload
composer install --optimize-autoloader --no-dev
```

### 2. OPcache 驗證

檢查 OPcache 是否啟用：

```bash
docker-compose -f docker-compose.prod.yml exec app php -i | grep opcache
```

### 3. Redis 優化

Redis 配置已在 `docker/redis/redis.conf` 中優化，包括：

- 記憶體管理（LRU 策略）
- 持久化設定（RDB + AOF）
- 執行緒 I/O 優化

### 4. MySQL 優化

MySQL 配置已在 `docker/mysql/my.cnf` 中優化，包括：

- InnoDB 緩衝池大小
- 連線池設定
- 查詢快取優化

## 安全加固

### 1. 防火牆設定

```bash
# 安裝 UFW
sudo apt-get install ufw

# 允許 SSH
sudo ufw allow 22/tcp

# 允許 HTTP 和 HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# 啟用防火牆
sudo ufw enable

# 檢查狀態
sudo ufw status
```

### 2. IP 白名單

在 `.env.production.local` 中設定 IP 白名單：

```env
# Admin UI 只允許特定 IP 存取
IP_WHITELIST_ADMIN=192.168.1.0/24,10.0.0.1

# API 白名單（空值表示允許所有）
IP_WHITELIST_API=
```

### 3. 定期更新

```bash
# 更新系統套件
sudo apt-get update && sudo apt-get upgrade -y

# 更新 Docker 映像
docker-compose -f docker-compose.prod.yml pull
docker-compose -f docker-compose.prod.yml up -d
```

### 4. 安全檢查清單

- [ ] 已設定強密碼（資料庫、Redis、JWT）
- [ ] 已啟用 HTTPS
- [ ] 已設定 HSTS
- [ ] 已配置防火牆
- [ ] 已設定 IP 白名單（Admin UI）
- [ ] 已關閉 Debug 模式
- [ ] 已移除預設帳號
- [ ] 已設定日誌監控
- [ ] 已配置自動備份

## 監控和日誌

### 1. 查看應用日誌

```bash
# 即時查看日誌
docker-compose -f docker-compose.prod.yml logs -f app

# 查看 Nginx 日誌
docker-compose -f docker-compose.prod.yml logs -f nginx

# 查看 MySQL 慢查詢日誌
docker-compose -f docker-compose.prod.yml exec mysql tail -f /var/log/mysql/slow-query.log
```

### 2. 日誌輪替

創建日誌輪替配置：

```bash
sudo nano /etc/logrotate.d/dynamic-api-manager
```

內容：

```
/path/to/project/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        docker-compose -f /path/to/project/docker-compose.prod.yml exec app php artisan cache:clear > /dev/null
    endscript
}
```

### 3. 健康檢查

設定定期健康檢查：

```bash
# 創建健康檢查腳本
nano /usr/local/bin/api-health-check.sh
```

內容：

```bash
#!/bin/bash
HEALTH_URL="https://api.example.com/health"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" $HEALTH_URL)

if [ $RESPONSE -ne 200 ]; then
    echo "Health check failed: HTTP $RESPONSE"
    # 發送告警（例如：發送郵件或 Slack 通知）
    exit 1
fi

echo "Health check passed"
exit 0
```

```bash
# 設定執行權限
sudo chmod +x /usr/local/bin/api-health-check.sh

# 添加到 Cron（每 5 分鐘檢查一次）
sudo crontab -e
*/5 * * * * /usr/local/bin/api-health-check.sh
```

## 備份策略

### 1. 資料庫備份

創建自動備份腳本：

```bash
nano /usr/local/bin/backup-database.sh
```

內容：

```bash
#!/bin/bash
BACKUP_DIR="/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
CONTAINER="api-manager-mysql-prod"
DB_NAME="api_manager_prod"
DB_USER="root"
DB_PASSWORD="your-root-password"

# 創建備份目錄
mkdir -p $BACKUP_DIR

# 執行備份
docker exec $CONTAINER mysqldump -u$DB_USER -p$DB_PASSWORD $DB_NAME | gzip > $BACKUP_DIR/backup_$DATE.sql.gz

# 刪除 30 天前的備份
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete

echo "Database backup completed: backup_$DATE.sql.gz"
```

```bash
# 設定執行權限
sudo chmod +x /usr/local/bin/backup-database.sh

# 添加到 Cron（每天凌晨 3 點備份）
sudo crontab -e
0 3 * * * /usr/local/bin/backup-database.sh
```

### 2. Redis 備份

Redis 已配置 RDB 和 AOF 持久化，資料會自動儲存到 Volume。

定期備份 Redis 資料：

```bash
# 手動觸發 RDB 快照
docker-compose -f docker-compose.prod.yml exec redis redis-cli BGSAVE

# 複製 RDB 檔案
docker cp api-manager-redis-prod:/data/dump.rdb /backups/redis/dump_$(date +%Y%m%d).rdb
```

### 3. 應用程式碼備份

```bash
# 備份整個專案（排除 vendor 和 node_modules）
tar -czf /backups/app/app_$(date +%Y%m%d).tar.gz \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage/logs' \
    /path/to/project
```

## 故障排除

### 常見問題

#### 1. 容器無法啟動

```bash
# 檢查容器日誌
docker-compose -f docker-compose.prod.yml logs app

# 檢查容器狀態
docker-compose -f docker-compose.prod.yml ps
```

#### 2. 資料庫連線失敗

```bash
# 檢查 MySQL 容器是否運行
docker-compose -f docker-compose.prod.yml ps mysql

# 測試資料庫連線
docker-compose -f docker-compose.prod.yml exec app php artisan tinker
>>> DB::connection()->getPdo();
```

#### 3. Redis 連線失敗

```bash
# 檢查 Redis 容器
docker-compose -f docker-compose.prod.yml ps redis

# 測試 Redis 連線
docker-compose -f docker-compose.prod.yml exec redis redis-cli ping
```

#### 4. SSL 憑證問題

```bash
# 檢查憑證有效期
openssl x509 -in docker/nginx/ssl/cert.pem -noout -dates

# 測試 SSL 連線
openssl s_client -connect api.example.com:443
```

## 效能調校

### 1. 調整 PHP-FPM 工作進程

編輯 `docker/php/production.ini`：

```ini
; 根據伺服器 CPU 核心數調整
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

### 2. 調整 Nginx 工作進程

編輯 `docker/nginx/nginx.conf`：

```nginx
# 設定為 CPU 核心數
worker_processes auto;

# 調整連線數
events {
    worker_connections 2048;
}
```

### 3. 調整 MySQL 緩衝區

根據伺服器記憶體調整 `docker/mysql/my.cnf`：

```ini
# 8GB 記憶體的伺服器建議值
innodb_buffer_pool_size = 4G
innodb_log_file_size = 256M
```

## 維護計畫

### 每日

- 檢查健康狀態
- 檢查日誌錯誤
- 檢查磁碟空間

### 每週

- 檢查慢查詢日誌
- 檢查安全日誌
- 檢查備份完整性

### 每月

- 更新系統套件
- 檢查 SSL 憑證有效期
- 檢查效能指標
- 清理舊日誌和備份

### 每季

- 進行安全審計
- 檢查並更新依賴套件
- 進行災難恢復演練

## 聯絡資訊

如有問題或需要支援，請聯絡：

- 技術支援：support@example.com
- 緊急聯絡：emergency@example.com

# Dynamic API Manager

動態 API 管理平台 - 基於 Laravel 框架的動態 API 配置系統

## 系統需求

- Docker 20.10+
- Docker Compose 2.0+

## 快速開始

### 1. 複製專案

```bash
git clone <repository-url>
cd dynamic-api-manager
```

### 2. 設定環境變數

```bash
cp .env.example .env
```

### 3. 啟動 Docker 容器

```bash
docker-compose up -d
```

### 4. 安裝 Laravel 依賴

```bash
docker-compose exec app composer install
```

### 5. 產生應用程式金鑰

```bash
docker-compose exec app php artisan key:generate
```

### 6. 執行資料庫遷移

```bash
docker-compose exec app php artisan migrate
```

### 7. 存取應用程式

- API Server: http://localhost:8080
- MySQL: localhost:3306
- Redis: localhost:6379

## Docker 服務說明

- **app**: PHP 8.2 FPM 應用服務
- **nginx**: Nginx Web 伺服器
- **mysql**: MySQL 8.0 資料庫
- **redis**: Redis 7 快取服務

## 常用指令

```bash
# 啟動所有服務
docker-compose up -d

# 停止所有服務
docker-compose down

# 查看服務日誌
docker-compose logs -f

# 進入應用容器
docker-compose exec app bash

# 執行 Artisan 命令
docker-compose exec app php artisan <command>

# 執行 Composer 命令
docker-compose exec app composer <command>
```

## 專案結構

```
.
├── app/                    # 應用程式核心程式碼
├── bootstrap/              # 框架啟動檔案
├── config/                 # 配置檔案
├── database/               # 資料庫遷移和種子
├── docker/                 # Docker 配置檔案
│   ├── Dockerfile         # PHP-FPM Dockerfile
│   ├── nginx/             # Nginx 配置
│   ├── mysql/             # MySQL 配置
│   └── php/               # PHP 配置
├── public/                 # 公開目錄
├── routes/                 # 路由定義
├── storage/                # 儲存目錄
├── tests/                  # 測試檔案
├── docker-compose.yml      # Docker Compose 配置
└── .env                    # 環境變數
```

## 授權

MIT License

#!/bin/bash

# Dynamic API Manager 安裝腳本
# 此腳本會自動設定 Docker 環境並初始化 Laravel 應用程式

echo "========================================="
echo "Dynamic API Manager - 安裝腳本"
echo "========================================="
echo ""

# 檢查 Docker 是否已安裝
if ! command -v docker &> /dev/null; then
    echo "錯誤: Docker 未安裝，請先安裝 Docker"
    exit 1
fi

# 檢查 Docker Compose 是否已安裝
if ! command -v docker-compose &> /dev/null; then
    echo "錯誤: Docker Compose 未安裝，請先安裝 Docker Compose"
    exit 1
fi

echo "✓ Docker 和 Docker Compose 已安裝"
echo ""

# 複製環境變數檔案
if [ ! -f .env ]; then
    echo "正在建立 .env 檔案..."
    cp .env.example .env
    echo "✓ .env 檔案已建立"
else
    echo "✓ .env 檔案已存在"
fi
echo ""

# 啟動 Docker 容器
echo "正在啟動 Docker 容器..."
docker-compose up -d
echo "✓ Docker 容器已啟動"
echo ""

# 等待 MySQL 啟動
echo "等待 MySQL 啟動..."
sleep 10
echo "✓ MySQL 已就緒"
echo ""

# 安裝 Composer 依賴
echo "正在安裝 Composer 依賴..."
docker-compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader
echo "✓ Composer 依賴已安裝"
echo ""

# 產生應用程式金鑰
echo "正在產生應用程式金鑰..."
docker-compose exec -T app php artisan key:generate
echo "✓ 應用程式金鑰已產生"
echo ""

# 執行資料庫遷移
echo "正在執行資料庫遷移..."
docker-compose exec -T app php artisan migrate --force
echo "✓ 資料庫遷移已完成"
echo ""

# 清除快取
echo "正在清除快取..."
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan cache:clear
docker-compose exec -T app php artisan route:clear
docker-compose exec -T app php artisan view:clear
echo "✓ 快取已清除"
echo ""

echo "========================================="
echo "安裝完成！"
echo "========================================="
echo ""
echo "應用程式已成功啟動，您可以透過以下方式存取："
echo ""
echo "  API Server: http://localhost:8080"
echo "  Health Check: http://localhost:8080/api/health"
echo ""
echo "常用指令："
echo "  啟動服務: docker-compose up -d"
echo "  停止服務: docker-compose down"
echo "  查看日誌: docker-compose logs -f"
echo "  進入容器: docker-compose exec app bash"
echo ""

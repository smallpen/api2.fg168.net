#!/bin/bash

# 驗證 Docker 環境設定腳本

echo "========================================="
echo "驗證 Docker 環境設定"
echo "========================================="
echo ""

# 檢查容器狀態
echo "檢查容器狀態..."
docker-compose ps
echo ""

# 檢查 PHP 版本
echo "檢查 PHP 版本..."
docker-compose exec -T app php -v
echo ""

# 檢查 PHP 擴展
echo "檢查 PHP 擴展..."
docker-compose exec -T app php -m | grep -E "(pdo_mysql|redis|mbstring|zip)"
echo ""

# 檢查 MySQL 連線
echo "檢查 MySQL 連線..."
docker-compose exec -T mysql mysql -u${DB_USERNAME:-api_user} -p${DB_PASSWORD:-secret} -e "SELECT VERSION();"
echo ""

# 檢查 Redis 連線
echo "檢查 Redis 連線..."
docker-compose exec -T redis redis-cli ping
echo ""

# 檢查 Nginx 配置
echo "檢查 Nginx 配置..."
docker-compose exec -T nginx nginx -t
echo ""

echo "========================================="
echo "驗證完成"
echo "========================================="

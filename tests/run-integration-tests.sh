#!/bin/bash

# 整合測試執行腳本
# 此腳本會啟動測試環境並執行整合測試

set -e

echo "========================================="
echo "啟動整合測試環境"
echo "========================================="

# 啟動測試用 Docker 容器
echo "啟動 Docker 測試容器..."
docker-compose -f docker-compose.test.yml up -d

# 等待資料庫就緒
echo "等待資料庫就緒..."
sleep 10

# 檢查資料庫連線
echo "檢查資料庫連線..."
docker-compose -f docker-compose.test.yml exec -T mysql-test mysqladmin ping -h localhost -u test_user -ptest_secret || {
    echo "資料庫連線失敗"
    exit 1
}

echo "========================================="
echo "執行整合測試"
echo "========================================="

# 在容器內執行測試
docker-compose -f docker-compose.test.yml exec -T app-test php artisan test --testsuite=Integration

TEST_EXIT_CODE=$?

echo "========================================="
echo "清理測試環境"
echo "========================================="

# 停止並移除測試容器
docker-compose -f docker-compose.test.yml down -v

echo "========================================="
echo "測試完成"
echo "========================================="

exit $TEST_EXIT_CODE

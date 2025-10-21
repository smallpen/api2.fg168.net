# Dynamic API Manager - Makefile
# 提供常用的開發指令快捷方式

.PHONY: help install up down restart logs shell test migrate seed fresh cache-clear

# 預設目標：顯示幫助訊息
help:
	@echo "Dynamic API Manager - 可用指令："
	@echo ""
	@echo "  make install      - 初始化專案（首次安裝）"
	@echo "  make up           - 啟動所有 Docker 容器"
	@echo "  make down         - 停止所有 Docker 容器"
	@echo "  make restart      - 重啟所有 Docker 容器"
	@echo "  make logs         - 查看容器日誌"
	@echo "  make shell        - 進入應用容器 Shell"
	@echo "  make test         - 執行測試"
	@echo "  make migrate      - 執行資料庫遷移"
	@echo "  make seed         - 執行資料庫種子"
	@echo "  make fresh        - 重置資料庫並執行遷移和種子"
	@echo "  make cache-clear  - 清除所有快取"
	@echo ""

# 初始化專案
install:
	@echo "正在初始化專案..."
	@chmod +x setup.sh
	@./setup.sh

# 啟動容器
up:
	@echo "正在啟動 Docker 容器..."
	@docker-compose up -d

# 停止容器
down:
	@echo "正在停止 Docker 容器..."
	@docker-compose down

# 重啟容器
restart:
	@echo "正在重啟 Docker 容器..."
	@docker-compose restart

# 查看日誌
logs:
	@docker-compose logs -f

# 進入應用容器
shell:
	@docker-compose exec app bash

# 執行測試
test:
	@docker-compose exec app php artisan test

# 執行資料庫遷移
migrate:
	@docker-compose exec app php artisan migrate

# 執行資料庫種子
seed:
	@docker-compose exec app php artisan db:seed

# 重置資料庫
fresh:
	@docker-compose exec app php artisan migrate:fresh --seed

# 清除快取
cache-clear:
	@docker-compose exec app php artisan config:clear
	@docker-compose exec app php artisan cache:clear
	@docker-compose exec app php artisan route:clear
	@docker-compose exec app php artisan view:clear
	@echo "快取已清除"

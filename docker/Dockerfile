# 多階段建構 - 基礎映像
FROM php:8.2-fpm AS base

# 設定工作目錄
WORKDIR /var/www/html

# 安裝系統依賴套件（優化快取層）
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 安裝 PHP 擴展（優化快取層）
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# 安裝 Redis 擴展
RUN pecl install redis \
    && docker-php-ext-enable redis

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ============================================
# 開發環境階段
# ============================================
FROM base AS development

# 複製開發環境 PHP 配置
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# 設定檔案權限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 切換到 www-data 使用者
USER www-data

# 暴露 PHP-FPM 埠
EXPOSE 9000

# 健康檢查
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# 啟動 PHP-FPM
CMD ["php-fpm"]

# ============================================
# Production 環境階段
# ============================================
FROM base AS production

# 複製 Production 環境 PHP 配置
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini

# 複製應用程式檔案（優化建構快取）
COPY --chown=www-data:www-data composer.json composer.lock ./

# 安裝 Production 依賴套件（無開發套件）
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist \
    && composer clear-cache

# 複製應用程式程式碼
COPY --chown=www-data:www-data . .

# 優化 Laravel
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# 設定檔案權限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# 切換到 www-data 使用者
USER www-data

# 暴露 PHP-FPM 埠
EXPOSE 9000

# 健康檢查
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# 啟動 PHP-FPM
CMD ["php-fpm"]

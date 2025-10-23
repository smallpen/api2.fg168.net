# 響應式設計部署檢查清單

## 📋 部署前檢查

### 1. 編譯前端資源

```bash
# 開發環境
npm run dev

# 生產環境
npm run build
```

### 2. 清除快取

```bash
# 清除 Laravel 快取
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 清除路由快取
php artisan route:clear
```

### 3. 檢查檔案權限

確保以下目錄可寫入：
- `storage/`
- `bootstrap/cache/`
- `public/build/`

### 4. 測試響應式功能

#### 桌面測試（≥ 1025px）
- [ ] 側邊欄固定顯示
- [ ] 統計卡片 4 欄顯示
- [ ] 表格顯示所有欄位
- [ ] 使用者名稱完整顯示

#### 平板測試（641px - 1024px）
- [ ] 側邊欄縮小但可見
- [ ] 統計卡片 2 欄顯示
- [ ] 表格保留主要欄位
- [ ] 頂部導航欄正常顯示

#### 手機測試（≤ 640px）
- [ ] 選單按鈕顯示在左上角
- [ ] 點擊選單按鈕可開啟側邊欄
- [ ] 側邊欄從左側滑出
- [ ] 背景顯示半透明遮罩
- [ ] 點擊遮罩可關閉選單
- [ ] 點擊選單項目後自動關閉
- [ ] 統計卡片單欄顯示
- [ ] 表格隱藏次要欄位
- [ ] 按鈕易於點擊（最小 44px）
- [ ] 輸入框易於操作（最小 44px）
- [ ] 使用者名稱在小螢幕隱藏

### 5. 瀏覽器相容性測試

#### 桌面瀏覽器
- [ ] Chrome 90+
- [ ] Firefox 88+
- [ ] Safari 14+
- [ ] Edge 90+

#### 移動瀏覽器
- [ ] iOS Safari 14+
- [ ] Chrome for Android 90+
- [ ] Firefox for Android 88+

### 6. 功能測試

#### 導航功能
- [ ] 所有選單項目可正常導航
- [ ] 路由切換正常
- [ ] 頁面標題正確顯示

#### 資料顯示
- [ ] 儀表板統計資料正確顯示
- [ ] 表格資料正確載入
- [ ] 圖表正確渲染

#### 表單操作
- [ ] 輸入框可正常輸入
- [ ] 下拉選單可正常選擇
- [ ] 按鈕可正常點擊
- [ ] 表單驗證正常運作

#### 互動功能
- [ ] 搜尋功能正常
- [ ] 篩選功能正常
- [ ] 分頁功能正常
- [ ] 排序功能正常

### 7. 效能檢查

- [ ] 頁面載入時間 < 3 秒
- [ ] 選單動畫流暢（無卡頓）
- [ ] 滾動效能良好
- [ ] 無記憶體洩漏

### 8. 視覺檢查

- [ ] 無橫向滾動條（除非必要）
- [ ] 文字大小適中，易於閱讀
- [ ] 按鈕和連結易於點擊
- [ ] 間距和對齊正確
- [ ] 顏色對比度足夠

### 9. 錯誤處理

- [ ] 網路錯誤有適當提示
- [ ] 載入狀態正確顯示
- [ ] 空狀態有友善提示
- [ ] 錯誤訊息清晰易懂

### 10. 安全性檢查

- [ ] CSRF Token 正確設定
- [ ] API 請求需要認證
- [ ] 敏感資料不在前端暴露
- [ ] XSS 防護正常運作

## 🚀 部署步驟

### 1. 備份現有系統

```bash
# 備份資料庫
php artisan backup:run

# 備份檔案
tar -czf backup-$(date +%Y%m%d).tar.gz .
```

### 2. 更新程式碼

```bash
# 拉取最新程式碼
git pull origin main

# 安裝依賴
composer install --no-dev --optimize-autoloader
npm install
```

### 3. 編譯資源

```bash
# 編譯前端資源
npm run build
```

### 4. 更新資料庫

```bash
# 執行遷移（如有需要）
php artisan migrate --force
```

### 5. 清除快取

```bash
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. 設定檔案權限

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 7. 重啟服務

```bash
# 重啟 PHP-FPM
sudo systemctl restart php-fpm

# 重啟 Nginx
sudo systemctl restart nginx

# 重啟佇列工作者（如有使用）
php artisan queue:restart
```

## 🧪 部署後測試

### 1. 基本功能測試

- [ ] 可以正常登入
- [ ] 儀表板正常顯示
- [ ] 所有頁面可正常訪問

### 2. 響應式測試

使用不同裝置測試：
- [ ] iPhone（直向/橫向）
- [ ] iPad（直向/橫向）
- [ ] Android 手機
- [ ] 桌面瀏覽器

### 3. 效能測試

- [ ] 使用 Chrome DevTools 檢查效能
- [ ] 使用 Lighthouse 進行評分
- [ ] 檢查網路請求數量和大小

### 4. 錯誤監控

- [ ] 檢查錯誤日誌
- [ ] 監控伺服器資源使用
- [ ] 確認無 JavaScript 錯誤

## 📊 效能指標

### 目標指標

- **首次內容繪製（FCP）**: < 1.8 秒
- **最大內容繪製（LCP）**: < 2.5 秒
- **首次輸入延遲（FID）**: < 100 毫秒
- **累積佈局偏移（CLS）**: < 0.1

### 檢查工具

- Chrome DevTools
- Lighthouse
- WebPageTest
- GTmetrix

## 🔍 常見問題排查

### 問題：選單按鈕不顯示

**檢查項目：**
1. 前端資源是否正確編譯
2. CSS 是否正確載入
3. 瀏覽器快取是否清除

### 問題：選單無法開啟

**檢查項目：**
1. JavaScript 是否正確載入
2. 瀏覽器控制台是否有錯誤
3. Vue 元件是否正確掛載

### 問題：樣式錯亂

**檢查項目：**
1. CSS 檔案是否正確編譯
2. 快取是否清除
3. 瀏覽器是否支援 CSS Grid

### 問題：在某些裝置上顯示異常

**檢查項目：**
1. viewport meta 標籤是否正確
2. 瀏覽器版本是否支援
3. 是否有裝置特定的 CSS 問題

## 📝 回滾計畫

如果部署後發現嚴重問題：

```bash
# 1. 回滾程式碼
git reset --hard <previous-commit>

# 2. 重新編譯
npm run build

# 3. 清除快取
php artisan cache:clear
php artisan config:cache

# 4. 重啟服務
sudo systemctl restart php-fpm nginx
```

## 📞 支援聯絡

如遇到問題，請聯絡：
- 技術支援：[support@example.com]
- 緊急聯絡：[emergency@example.com]

## ✅ 部署完成確認

- [ ] 所有測試項目通過
- [ ] 效能指標達標
- [ ] 無嚴重錯誤
- [ ] 團隊成員已通知
- [ ] 文件已更新
- [ ] 監控已設定

---

**部署日期：** _______________  
**部署人員：** _______________  
**版本號碼：** _______________  
**備註：** _______________

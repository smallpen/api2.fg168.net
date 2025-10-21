# Admin UI 設定指南

## 概述

本文件說明如何設定和使用 Dynamic API Manager 的管理介面（Admin UI）。

## 前置需求

- Node.js 18.x 或更高版本
- npm 或 yarn
- 已完成 Laravel 專案的基本設定

## 安裝步驟

### 1. 安裝前端依賴套件

```bash
npm install
```

### 2. 執行資料庫 Migration

確保已建立 user_roles 資料表：

```bash
php artisan migrate
```

### 3. 建立管理員使用者（開發環境）

在開發環境中，您可以使用 tinker 建立管理員使用者：

```bash
php artisan tinker
```

然後執行以下程式碼：

```php
// 建立管理員角色
$adminRole = \App\Models\Role::findOrCreateByName('admin', '系統管理員');

// 建立管理員使用者
$admin = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
]);

// 指派管理員角色
$admin->assignRole($adminRole);
```

### 4. 啟動開發伺服器

#### 啟動 Laravel 開發伺服器

```bash
php artisan serve
```

#### 啟動 Vite 開發伺服器（另一個終端視窗）

```bash
npm run dev
```

## 存取管理介面

開發環境：
- 登入頁面：http://localhost:8000/admin/login
- 儀表板：http://localhost:8000/admin/dashboard

預設登入資訊（開發環境）：
- 電子郵件：admin@example.com
- 密碼：password

## 功能說明

### 儀表板

儀表板提供系統概覽，包括：

1. **統計卡片**
   - API Functions 總數（啟用/停用）
   - API 客戶端總數（啟用/停用）
   - 今日請求總數（成功/失敗）
   - 平均回應時間

2. **請求趨勢圖表**
   - 顯示最近 7 天的 API 請求趨勢
   - 區分成功和失敗的請求

3. **最常使用的 API Functions**
   - 顯示最近 7 天最常被調用的 API Functions
   - 包含請求次數統計

### 導航選單

側邊欄提供以下導航選項：
- 儀表板：系統概覽和統計資訊
- API Functions：管理 API 功能（後續任務實作）
- 客戶端管理：管理 API 客戶端（後續任務實作）
- 日誌查詢：查詢 API 請求日誌（後續任務實作）

## 建置生產版本

### 建置前端資源

```bash
npm run build
```

建置完成後，靜態資源會輸出到 `public/build` 目錄。

### 部署注意事項

1. 確保 `.env` 檔案中的 `APP_ENV` 設定為 `production`
2. 執行 `php artisan config:cache` 快取配置
3. 執行 `php artisan route:cache` 快取路由
4. 確保 `storage` 和 `bootstrap/cache` 目錄有寫入權限

## 安全性建議

1. **變更預設密碼**
   - 在生產環境中，務必變更管理員的預設密碼

2. **啟用 HTTPS**
   - 在生產環境中，務必使用 HTTPS 加密通訊

3. **設定 CSRF 保護**
   - Laravel 預設已啟用 CSRF 保護，請勿停用

4. **限制存取 IP**
   - 考慮在 Nginx 或防火牆層級限制管理介面的存取 IP

## 疑難排解

### 問題：無法載入 Vue 元件

**解決方案：**
1. 確認 Vite 開發伺服器正在執行
2. 檢查瀏覽器控制台是否有錯誤訊息
3. 清除瀏覽器快取並重新載入頁面

### 問題：登入後顯示權限不足

**解決方案：**
1. 確認使用者已被指派 'admin' 角色
2. 檢查 `user_roles` 資料表中的資料
3. 使用 tinker 重新指派角色

### 問題：統計資料顯示為 0

**解決方案：**
1. 確認資料庫中有相關資料
2. 檢查 API 請求日誌是否正常記錄
3. 查看 Laravel 日誌檔案（`storage/logs/laravel.log`）

## 技術架構

### 前端技術棧

- **Vue.js 3**：前端框架
- **Vue Router 4**：路由管理
- **Axios**：HTTP 請求
- **Vite**：建置工具

### 後端技術棧

- **Laravel 10.x**：後端框架
- **Blade**：模板引擎
- **MySQL**：資料庫

### 目錄結構

```
resources/
├── css/
│   └── app.css              # 全域樣式
├── js/
│   ├── app.js               # Vue 應用程式入口
│   ├── components/
│   │   └── AdminLayout.vue  # 主要 Layout 元件
│   └── pages/
│       └── Dashboard.vue    # 儀表板頁面
└── views/
    └── admin/
        ├── dashboard.blade.php  # 儀表板視圖
        └── login.blade.php      # 登入頁面視圖
```

## 下一步

完成基礎架構後，您可以繼續實作以下功能：

1. **API Functions 管理**（任務 12）
   - Function 列表和搜尋
   - Function 編輯器
   - 參數建構器
   - 回應映射器

2. **客戶端管理**（任務 13）
   - 客戶端列表
   - API Key 生成
   - 權限配置

3. **日誌查詢**（任務 14）
   - 日誌列表和篩選
   - 日誌詳情檢視

## 相關文件

- [API Gateway 使用說明](./api-gateway-usage.md)
- [Rate Limiting 實作說明](./rate-limiting-implementation.md)
- [Logging 使用說明](./logging-usage.md)

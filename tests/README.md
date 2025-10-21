# 整合測試說明文件

## 概述

本專案包含完整的整合測試套件，用於驗證 Dynamic API Manager 的各項功能。測試涵蓋 API Gateway、驗證機制、授權機制、錯誤處理、Function 管理、客戶端管理和權限配置等核心功能。

## 測試環境

### 環境需求

- Docker 和 Docker Compose
- PHP 8.2+
- MySQL 8.0
- Redis 7.x

### 測試資料庫

整合測試使用獨立的測試資料庫和 Redis 實例，與開發環境完全隔離：

- **測試資料庫**: `api_manager_test` (Port 3307)
- **測試 Redis**: Port 6380

## 執行測試

### 方法一：使用測試腳本（推薦）

#### Windows

```cmd
tests\run-integration-tests.bat
```

#### Linux/Mac

```bash
chmod +x tests/run-integration-tests.sh
./tests/run-integration-tests.sh
```

測試腳本會自動：
1. 啟動測試用 Docker 容器
2. 等待資料庫就緒
3. 執行整合測試
4. 清理測試環境

### 方法二：手動執行

1. 啟動測試環境：

```bash
docker-compose -f docker-compose.test.yml up -d
```

2. 等待資料庫就緒（約 10 秒）

3. 執行測試：

```bash
# 執行所有整合測試
docker-compose -f docker-compose.test.yml exec app-test php artisan test --testsuite=Integration

# 執行特定測試類別
docker-compose -f docker-compose.test.yml exec app-test php artisan test --filter=ApiGatewayIntegrationTest

# 執行特定測試方法
docker-compose -f docker-compose.test.yml exec app-test php artisan test --filter=test_complete_api_request_flow
```

4. 清理環境：

```bash
docker-compose -f docker-compose.test.yml down -v
```

### 方法三：本地執行（需要本地環境）

如果您已經在本地配置了測試資料庫和 Redis：

```bash
# 執行所有整合測試
php artisan test --testsuite=Integration

# 執行特定測試
php artisan test --filter=ApiGatewayIntegrationTest
```

## 測試套件結構

### Integration 測試套件

位於 `tests/Integration/` 目錄，包含以下測試類別：

#### 1. API Gateway 測試

**ApiGatewayIntegrationTest.php**
- 完整的 API 請求流程
- 參數驗證
- Function 查找和執行
- 回應格式化
- Rate Limiting

**測試覆蓋的需求**: 6.1, 6.2, 6.3, 6.4, 6.5

#### 2. 驗證機制測試

**AuthenticationIntegrationTest.php**
- Bearer Token 驗證
- API Key 驗證
- Token 過期檢查
- Token 撤銷
- 驗證失敗記錄

**測試覆蓋的需求**: 11.1, 11.2, 11.3, 11.4, 11.5

#### 3. 授權機制測試

**AuthorizationIntegrationTest.php**
- 基於角色的權限控制
- Function 層級權限
- 權限快取
- 授權失敗記錄

**測試覆蓋的需求**: 13.1, 13.2, 13.3, 13.4

#### 4. 錯誤處理測試

**ErrorHandlingIntegrationTest.php**
- 錯誤回應格式
- 資料庫錯誤映射
- 參數驗證錯誤
- 內部伺服器錯誤
- 錯誤日誌記錄

**測試覆蓋的需求**: 6.4, 11.3, 11.4, 13.4, 14.3

#### 5. Function 管理測試

**FunctionManagementIntegrationTest.php**
- Function CRUD 操作
- 參數管理
- 搜尋和篩選
- 啟用/停用
- 審計日誌

**測試覆蓋的需求**: 2.1, 2.2, 2.3, 2.4, 2.5, 7.1, 7.2, 7.3, 7.4, 7.5, 8.1, 8.2, 8.3, 8.4, 8.5

#### 6. 客戶端管理測試

**ClientManagementIntegrationTest.php**
- 客戶端 CRUD 操作
- API Key 生成和重新生成
- Token 管理
- 客戶端統計
- 審計日誌

**測試覆蓋的需求**: 12.1, 12.2, 12.3, 12.4, 12.5

#### 7. 權限管理測試

**PermissionManagementIntegrationTest.php**
- 角色管理
- 權限指派
- Function 權限矩陣
- 批次權限設定
- 權限快取清除

**測試覆蓋的需求**: 13.1, 13.2, 13.3, 13.4

## 測試基礎類別

### IntegrationTestCase

所有整合測試都繼承自 `IntegrationTestCase`，提供以下功能：

- 自動資料庫遷移和重置
- Redis 快取清理
- 測試資料建立輔助方法
- API 回應斷言輔助方法

#### 常用輔助方法

```php
// 建立測試客戶端
$client = $this->createTestClient(['is_active' => true]);

// 建立測試 Function
$function = $this->createTestFunction(['identifier' => 'test.func']);

// 產生 Bearer Token
$token = $this->generateBearerToken($client);

// 產生 API Key
$apiKey = $this->generateApiKey($client);

// 建立具有權限的客戶端
$client = $this->createClientWithPermissions([
    ['resource_type' => 'function', 'action' => 'execute']
]);

// 斷言 API 回應格式
$this->assertApiResponse($response, true);

// 斷言錯誤回應
$this->assertErrorResponse($response, 'ERROR_CODE', 400);
```

## 測試資料

### Factory 使用

測試使用 Laravel Factory 建立測試資料：

```php
// 建立單一記錄
$client = ApiClient::factory()->create();

// 建立多筆記錄
$clients = ApiClient::factory()->count(5)->create();

// 建立並指定屬性
$client = ApiClient::factory()->create([
    'name' => '測試客戶端',
    'is_active' => true
]);

// 建立關聯資料
$token = ApiToken::factory()->for($client)->create();
```

### Seeder

測試環境會自動執行以下 Seeders：

- `RoleSeeder`: 建立預設角色
- `AdminUserSeeder`: 建立管理員帳號
- `ApiClientSeeder`: 建立測試客戶端

## 測試配置

### PHPUnit 配置

測試配置位於 `phpunit.xml`：

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="mysql"/>
    <env name="DB_HOST" value="127.0.0.1"/>
    <env name="DB_PORT" value="3307"/>
    <env name="DB_DATABASE" value="api_manager_test"/>
    <env name="REDIS_HOST" value="127.0.0.1"/>
    <env name="REDIS_PORT" value="6380"/>
</php>
```

### 環境變數

測試環境變數位於 `.env.testing`，包含：

- 測試資料庫連線設定
- 測試 Redis 設定
- 較低的 Bcrypt rounds（加快測試速度）
- 停用的 OAuth 功能

## 測試最佳實踐

### 1. 測試隔離

每個測試方法都是獨立的：
- 使用 `RefreshDatabase` trait 確保資料庫乾淨
- 每次測試前清除 Redis 快取
- 不依賴其他測試的執行順序

### 2. 測試命名

測試方法使用描述性命名：
```php
public function test_complete_api_request_flow_with_valid_credentials()
public function test_request_without_authentication_credentials()
```

### 3. 斷言清晰

使用明確的斷言方法：
```php
$response->assertStatus(200);
$response->assertJsonStructure(['data', 'meta']);
$this->assertDatabaseHas('api_clients', ['name' => '測試']);
```

### 4. 測試覆蓋

每個測試應該：
- 測試一個明確的功能或場景
- 包含正面和負面測試案例
- 驗證資料庫狀態變更
- 檢查日誌記錄

## 持續整合

### GitHub Actions 範例

```yaml
name: Integration Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Start test environment
        run: docker-compose -f docker-compose.test.yml up -d
      
      - name: Wait for database
        run: sleep 10
      
      - name: Run tests
        run: docker-compose -f docker-compose.test.yml exec -T app-test php artisan test --testsuite=Integration
      
      - name: Cleanup
        run: docker-compose -f docker-compose.test.yml down -v
```

## 疑難排解

### 資料庫連線失敗

如果測試無法連線到資料庫：

1. 確認測試容器已啟動：
   ```bash
   docker-compose -f docker-compose.test.yml ps
   ```

2. 檢查資料庫健康狀態：
   ```bash
   docker-compose -f docker-compose.test.yml exec mysql-test mysqladmin ping
   ```

3. 增加等待時間（在測試腳本中）

### Redis 連線失敗

如果 Redis 連線失敗：

1. 確認 Redis 容器運行中
2. 檢查 Port 6380 是否被佔用
3. 驗證 `.env.testing` 中的 Redis 設定

### 測試執行緩慢

優化建議：

1. 使用較低的 Bcrypt rounds（已在 `.env.testing` 設定）
2. 減少不必要的資料庫查詢
3. 使用 `--parallel` 選項平行執行測試（需要額外配置）

### Port 衝突

如果 Port 3307 或 6380 已被佔用：

1. 修改 `docker-compose.test.yml` 中的 Port 映射
2. 更新 `phpunit.xml` 中對應的 Port 設定

## 測試報告

### 產生覆蓋率報告

```bash
docker-compose -f docker-compose.test.yml exec app-test php artisan test --coverage
```

### 產生 HTML 報告

```bash
docker-compose -f docker-compose.test.yml exec app-test php artisan test --coverage-html coverage
```

報告會產生在 `coverage/` 目錄中。

## 維護指南

### 新增測試

1. 在 `tests/Integration/` 建立新的測試類別
2. 繼承 `IntegrationTestCase`
3. 使用 `@test` 註解標記測試方法
4. 執行測試確認通過

### 更新測試

當功能變更時：

1. 更新相關的測試案例
2. 確保所有測試通過
3. 更新測試文件

### 測試維護

定期檢查：

- 測試執行時間（避免過慢）
- 測試覆蓋率（目標 > 80%）
- 失敗的測試（及時修復）
- 過時的測試（移除或更新）

## 參考資源

- [Laravel Testing 文件](https://laravel.com/docs/testing)
- [PHPUnit 文件](https://phpunit.de/documentation.html)
- [Docker Compose 文件](https://docs.docker.com/compose/)

## 聯絡資訊

如有測試相關問題，請聯絡開發團隊或提交 Issue。

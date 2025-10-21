# 日誌系統使用指南

## 概述

本系統提供完整的日誌記錄功能，包含 API 請求日誌、安全日誌和審計日誌。所有日誌都會自動記錄到資料庫中，並提供查詢和清理功能。

## 日誌類型

### 1. API 請求日誌 (ApiRequestLog)

記錄所有通過 API Gateway 的請求資訊。

**記錄內容：**
- 客戶端 ID
- Function ID
- 請求資料
- 回應資料
- HTTP 狀態碼
- 執行時間
- IP 位址
- User Agent

**使用範例：**

```php
use App\Services\Logging\LoggingService;

// 注入 LoggingService
public function __construct(LoggingService $loggingService)
{
    $this->loggingService = $loggingService;
}

// 記錄成功的請求
$this->loggingService->api()->logSuccess(
    $clientId,
    $functionId,
    $requestData,
    $responseData,
    $executionTime,
    $request->ip(),
    $request->userAgent()
);

// 記錄失敗的請求
$this->loggingService->api()->logFailure(
    $clientId,
    $functionId,
    $requestData,
    $errorData,
    $httpStatus,
    $executionTime,
    $request->ip(),
    $request->userAgent()
);

// 查詢日誌
$logs = $this->loggingService->api()->queryLogs([
    'client_id' => 1,
    'function_id' => 5,
    'http_status' => 200,
    'start_date' => '2025-10-01',
    'end_date' => '2025-10-21',
], 50);
```

### 2. 安全日誌 (SecurityLog)

記錄系統安全相關事件，如驗證失敗、權限拒絕等。

**事件類型：**
- `auth_failed` - 驗證失敗
- `auth_success` - 驗證成功
- `permission_denied` - 權限拒絕
- `rate_limit_exceeded` - 超過速率限制
- `invalid_token` - 無效的 Token
- `token_expired` - Token 過期
- `suspicious_activity` - 可疑活動

**使用範例：**

```php
use App\Services\Logging\LoggingService;

// 記錄驗證失敗
$this->loggingService->security()->logAuthenticationFailed(
    $request->ip(),
    ['reason' => 'Invalid credentials', 'username' => $username]
);

// 記錄驗證成功
$this->loggingService->security()->logAuthenticationSuccess(
    $clientId,
    $request->ip(),
    ['method' => 'Bearer Token']
);

// 記錄權限拒絕
$this->loggingService->security()->logPermissionDenied(
    $clientId,
    $request->ip(),
    ['function' => $functionIdentifier, 'action' => 'execute']
);

// 記錄速率限制超過
$this->loggingService->security()->logRateLimitExceeded(
    $clientId,
    $request->ip(),
    ['limit' => 60, 'window' => '1 minute']
);

// 查詢日誌
$logs = $this->loggingService->security()->queryLogs([
    'event_type' => 'auth_failed',
    'ip_address' => '192.168.1.100',
    'start_date' => '2025-10-01',
], 50);
```

### 3. 審計日誌 (AuditLog)

記錄系統配置變更和重要操作。

**操作類型：**
- `create` - 創建
- `update` - 更新
- `delete` - 刪除
- `enable` - 啟用
- `disable` - 停用
- `revoke` - 撤銷

**資源類型：**
- `api_function` - API Function
- `api_client` - API 客戶端
- `api_token` - API Token
- `permission` - 權限
- `role` - 角色
- `parameter` - 參數
- `response` - 回應

**使用範例：**

```php
use App\Services\Logging\LoggingService;

// 記錄創建操作
$this->loggingService->audit()->logCreate(
    $userId,
    'api_function',
    $function->id,
    $function->toArray()
);

// 記錄更新操作
$this->loggingService->audit()->logUpdate(
    $userId,
    'api_function',
    $function->id,
    $oldData,
    $newData
);

// 記錄刪除操作
$this->loggingService->audit()->logDelete(
    $userId,
    'api_function',
    $function->id,
    $function->toArray()
);

// 記錄啟用操作
$this->loggingService->audit()->logEnable(
    $userId,
    'api_function',
    $function->id
);

// 記錄停用操作
$this->loggingService->audit()->logDisable(
    $userId,
    'api_function',
    $function->id
);

// 查詢日誌
$logs = $this->loggingService->audit()->queryLogs([
    'user_id' => 1,
    'action' => 'update',
    'resource_type' => 'api_function',
    'start_date' => '2025-10-01',
], 50);
```

## 自動日誌記錄

### API Gateway 自動記錄

API Gateway 會自動記錄所有請求，無需手動調用。日誌記錄採用非同步方式，不會影響 API 回應時間。

**記錄時機：**
- 請求成功時記錄完整的請求和回應資料
- 請求失敗時記錄錯誤資訊
- 參數驗證失敗時記錄驗證錯誤
- Function 不存在或停用時記錄錯誤

### 中介軟體自動記錄

驗證和授權中介軟體會自動記錄安全事件：

- 驗證失敗自動記錄到安全日誌
- 權限拒絕自動記錄到安全日誌
- 速率限制超過自動記錄到安全日誌

## 日誌清理

系統提供自動清理功能，定期刪除舊的日誌資料。

### 自動清理

系統預設每天凌晨 2 點自動清理 30 天前的日誌資料。

**排程設定：** `app/Console/Kernel.php`

```php
$schedule->command('logs:cleanup --days=30 --type=all')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
```

### 手動清理

可以使用命令手動清理日誌：

```bash
# 清理 30 天前的所有日誌
php artisan logs:cleanup --days=30 --type=all

# 僅清理 API 請求日誌
php artisan logs:cleanup --days=30 --type=api

# 僅清理錯誤日誌
php artisan logs:cleanup --days=30 --type=error

# 僅清理安全日誌
php artisan logs:cleanup --days=30 --type=security

# 僅清理審計日誌（建議保留較長時間）
php artisan logs:cleanup --days=90 --type=audit

# 模擬模式（不實際刪除，僅顯示將要刪除的記錄數）
php artisan logs:cleanup --days=30 --type=all --dry-run
```

## 日誌查詢

### 使用 Service 查詢

```php
use App\Services\Logging\LoggingService;

// 查詢 API 請求日誌
$apiLogs = $loggingService->api()->queryLogs([
    'client_id' => 1,
    'function_id' => 5,
    'http_status' => 200,
    'start_date' => '2025-10-01',
    'end_date' => '2025-10-21',
    'ip_address' => '192.168.1.100',
], 50);

// 查詢安全日誌
$securityLogs = $loggingService->security()->queryLogs([
    'event_type' => 'auth_failed',
    'client_id' => 1,
    'ip_address' => '192.168.1.100',
    'start_date' => '2025-10-01',
], 50);

// 查詢審計日誌
$auditLogs = $loggingService->audit()->queryLogs([
    'user_id' => 1,
    'action' => 'update',
    'resource_type' => 'api_function',
    'resource_id' => 5,
    'start_date' => '2025-10-01',
], 50);
```

### 使用 Model 直接查詢

```php
use App\Models\ApiRequestLog;
use App\Models\SecurityLog;
use App\Models\AuditLog;

// 查詢最近的 API 請求
$recentRequests = ApiRequestLog::with(['client', 'function'])
    ->orderBy('created_at', 'desc')
    ->limit(100)
    ->get();

// 查詢特定客戶端的請求
$clientRequests = ApiRequestLog::where('client_id', 1)
    ->where('http_status', 200)
    ->get();

// 查詢失敗的請求
$failedRequests = ApiRequestLog::where('http_status', '>=', 400)
    ->orderBy('created_at', 'desc')
    ->get();

// 查詢安全事件
$securityEvents = SecurityLog::where('event_type', 'auth_failed')
    ->where('created_at', '>=', now()->subDays(7))
    ->get();

// 查詢審計記錄
$auditRecords = AuditLog::with('user')
    ->where('resource_type', 'api_function')
    ->where('action', 'update')
    ->get();
```

## 效能考量

### 非同步日誌記錄

API Gateway 使用 `dispatch()->afterResponse()` 非同步記錄日誌，確保日誌記錄不會影響 API 回應時間。

```php
dispatch(function () use ($data) {
    $this->loggingService->api()->logRequest($data);
})->afterResponse();
```

### 日誌索引

資料表已建立適當的索引以提升查詢效能：

- `api_request_logs`: client_id, function_id, http_status, created_at
- `security_logs`: event_type, client_id, ip_address, created_at
- `audit_logs`: user_id, action, resource_type, created_at

### 定期清理

建議定期清理舊日誌資料，避免資料庫過度膨脹：

- API 請求日誌：保留 30 天
- 錯誤日誌：保留 30 天
- 安全日誌：保留 30 天
- 審計日誌：保留 90 天或更長

## 最佳實踐

1. **使用 LoggingService**：統一使用 LoggingService 進行日誌記錄，避免直接操作 Model
2. **非同步記錄**：在關鍵路徑上使用非同步方式記錄日誌
3. **適當的日誌級別**：根據事件重要性選擇適當的日誌類型
4. **定期清理**：設定自動清理排程，避免資料庫膨脹
5. **監控告警**：對重要的安全事件設定監控告警
6. **隱私保護**：避免在日誌中記錄敏感資訊（如密碼、信用卡號等）

## 故障排除

### 日誌記錄失敗

如果日誌記錄失敗，系統會將錯誤記錄到 Laravel 的系統日誌中，但不會影響主流程。

檢查系統日誌：

```bash
tail -f storage/logs/laravel.log
```

### 日誌查詢緩慢

如果日誌查詢緩慢，可以：

1. 檢查資料表索引是否正確建立
2. 定期清理舊日誌資料
3. 考慮使用更精確的查詢條件
4. 考慮將日誌資料歸檔到其他儲存系統

### 磁碟空間不足

如果磁碟空間不足，可以：

1. 立即執行日誌清理命令
2. 調整自動清理的保留天數
3. 考慮將日誌資料歸檔到其他儲存系統

# Rate Limiting 使用說明

## 概述

Rate Limiting 服務提供了基於 Redis 的 Sliding Window 演算法來限制 API 請求頻率，防止濫用和保護系統資源。

## 功能特性

- **Sliding Window 演算法**：使用 Redis Sorted Set 實作精確的滑動時間窗口
- **客戶端層級限制**：支援為不同客戶端設定不同的速率限制
- **自動清理**：自動移除過期的請求記錄
- **標準化標頭**：在回應中包含標準的速率限制資訊標頭
- **靈活配置**：支援通過配置檔案或中介軟體參數設定限制

## 配置

### 配置檔案

在 `config/ratelimit.php` 中配置預設設定：

```php
return [
    'default' => [
        'max_attempts' => 60,      // 預設每分鐘 60 次請求
        'decay_seconds' => 60,     // 時間窗口 60 秒
    ],
    
    'limits' => [
        'default' => '60/minute',
        'premium' => '1000/minute',
        'enterprise' => '10000/minute',
    ],
];
```

### 環境變數

在 `.env` 檔案中設定：

```env
RATE_LIMIT_DEFAULT=60
RATE_LIMIT_REDIS_CONNECTION=default
RATE_LIMIT_PREFIX=rate_limit:
```

## 使用方式

### 1. 在路由中使用 Middleware

#### 使用預設限制

```php
Route::middleware(['auth.api', 'throttle.api'])->group(function () {
    Route::post('/api/v1/execute', [ApiGatewayController::class, 'execute']);
});
```

#### 自訂限制參數

```php
// 每分鐘 100 次請求
Route::middleware(['auth.api', 'throttle.api:100,60'])->group(function () {
    Route::post('/api/v1/premium', [ApiController::class, 'premium']);
});

// 每小時 1000 次請求
Route::middleware(['auth.api', 'throttle.api:1000,3600'])->group(function () {
    Route::post('/api/v1/enterprise', [ApiController::class, 'enterprise']);
});
```

### 2. 在控制器中使用

```php
use App\Services\RateLimit\RateLimiter;

class ApiController extends Controller
{
    protected RateLimiter $rateLimiter;
    
    public function __construct(RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }
    
    public function execute(Request $request)
    {
        $clientId = $request->get('authenticated_client')->id;
        
        // 檢查速率限制
        if ($this->rateLimiter->tooManyAttempts("client:{$clientId}", 60, 60)) {
            return response()->json([
                'error' => '超過請求頻率限制'
            ], 429);
        }
        
        // 增加請求計數
        $this->rateLimiter->hit("client:{$clientId}", 60);
        
        // 處理請求...
    }
}
```

### 3. 基於客戶端配置的動態限制

系統會自動從已驗證的客戶端讀取 `rate_limit` 欄位：

```php
// 在 api_clients 資料表中設定
$client->rate_limit = '1000/minute';  // 每分鐘 1000 次
$client->save();
```

Middleware 會自動應用該客戶端的速率限制。

## 回應標頭

當請求通過 Rate Limiting Middleware 時，回應會包含以下標頭：

```
X-RateLimit-Limit: 60           # 最大請求次數
X-RateLimit-Remaining: 45       # 剩餘請求次數
X-RateLimit-Reset: 1729512000   # 重置時間（Unix timestamp）
```

當超過限制時，還會包含：

```
Retry-After: 30                 # 建議重試時間（秒）
```

## 錯誤回應

當超過速率限制時，API 會返回 HTTP 429 狀態碼：

```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "超過請求頻率限制，請稍後再試",
    "details": {
      "max_attempts": 60,
      "retry_after": 30
    }
  },
  "meta": {
    "request_id": "req_abc123...",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

## RateLimiter 類別 API

### 主要方法

#### `tooManyAttempts(string $clientId, int $maxAttempts, int $decaySeconds): bool`

檢查客戶端是否超過速率限制。

```php
$exceeded = $rateLimiter->tooManyAttempts('client:123', 60, 60);
```

#### `hit(string $clientId, int $decaySeconds): int`

增加客戶端的請求計數，返回當前請求次數。

```php
$attempts = $rateLimiter->hit('client:123', 60);
```

#### `attempts(string $clientId, int $decaySeconds): int`

獲取客戶端當前的請求次數。

```php
$current = $rateLimiter->attempts('client:123', 60);
```

#### `remaining(string $clientId, int $maxAttempts): int`

獲取客戶端剩餘的請求次數。

```php
$remaining = $rateLimiter->remaining('client:123', 60);
```

#### `availableIn(string $clientId, int $decaySeconds): int`

獲取速率限制重置的時間（秒）。

```php
$resetIn = $rateLimiter->availableIn('client:123', 60);
```

#### `resetAttempts(string $clientId): void`

重置客戶端的請求計數。

```php
$rateLimiter->resetAttempts('client:123');
```

#### `clear(string $clientId): void`

清除客戶端的速率限制（與 resetAttempts 相同）。

```php
$rateLimiter->clear('client:123');
```

## 最佳實踐

### 1. 為不同的 API 端點設定不同的限制

```php
// 公開 API - 較嚴格的限制
Route::middleware(['throttle.api:30,60'])->group(function () {
    Route::get('/api/v1/public/data', [PublicController::class, 'data']);
});

// 已驗證 API - 較寬鬆的限制
Route::middleware(['auth.api', 'throttle.api:100,60'])->group(function () {
    Route::post('/api/v1/execute', [ApiGatewayController::class, 'execute']);
});

// 管理 API - 更寬鬆的限制
Route::middleware(['auth.api', 'authorize.api', 'throttle.api:1000,60'])->group(function () {
    Route::post('/api/v1/admin/functions', [FunctionController::class, 'store']);
});
```

### 2. 監控速率限制事件

系統會自動記錄超過速率限制的事件到日誌：

```php
// 在 storage/logs/laravel.log 中查看
[2025-10-21 10:30:00] local.WARNING: Rate limit exceeded 
{
    "client_id": "client:123",
    "max_attempts": 60,
    "ip_address": "192.168.1.1",
    "path": "/api/v1/execute"
}
```

### 3. 為 VIP 客戶端設定更高的限制

在資料庫中為客戶端設定自訂限制：

```sql
UPDATE api_clients 
SET rate_limit = '10000/minute' 
WHERE client_type = 'enterprise';
```

### 4. 使用 IP 地址作為備用識別

如果請求未經驗證，Middleware 會自動使用 IP 地址作為識別：

```php
// 未驗證的請求會使用 "ip:192.168.1.1" 作為識別碼
Route::middleware(['throttle.api:10,60'])->group(function () {
    Route::get('/api/v1/public/status', [StatusController::class, 'index']);
});
```

## 故障排除

### Redis 連線問題

確保 Redis 服務正在運行：

```bash
# 檢查 Redis 連線
php artisan tinker
>>> Redis::connection()->ping();
```

### 速率限制未生效

1. 確認 Middleware 已註冊在 `app/Http/Kernel.php`
2. 確認路由中已套用 `throttle.api` Middleware
3. 檢查 Redis 配置是否正確

### 清除所有速率限制資料

```bash
# 使用 Redis CLI
redis-cli
> KEYS rate_limit:*
> DEL rate_limit:client:123
```

或使用 Artisan 命令：

```bash
php artisan cache:clear
```

## 效能考量

- **Redis 效能**：Sliding Window 演算法使用 Redis Sorted Set，效能優異
- **自動清理**：每次檢查時會自動清理過期記錄，避免記憶體浪費
- **過期時間**：Redis 鍵會自動設定過期時間，確保資料不會永久佔用空間

## 安全建議

1. **設定合理的預設限制**：避免過於寬鬆導致系統被濫用
2. **監控異常流量**：定期檢查日誌中的速率限制事件
3. **結合其他安全措施**：Rate Limiting 應與驗證、授權等機制配合使用
4. **為不同端點設定不同限制**：根據端點的重要性和資源消耗設定適當的限制

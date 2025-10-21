# 快取機制使用指南

## 快速開始

本系統實作了完整的三層快取機制，所有快取操作都應在 Docker 容器內執行。

## 基本命令

### 1. 查看快取統計

```bash
docker-compose exec app php artisan api:cache-stats
```

輸出範例：
```
=== API 快取統計資訊 ===

【配置快取】
+------------+-------------+
| 項目       | 數值        |
+------------+-------------+
| 快取項目數 | 15          |
| 快取前綴   | api_config: |
| 預設 TTL   | 3600 秒     |
+------------+-------------+

【權限快取】
+-------------------+---------+
| 項目              | 數值    |
+-------------------+---------+
| 客戶端權限快取    | 8       |
| 角色權限快取      | 3       |
| Function 權限快取 | 45      |
| 總計              | 56      |
| 預設 TTL          | 1800 秒 |
+-------------------+---------+

【查詢結果快取】
+------------+---------------+
| 項目       | 數值          |
+------------+---------------+
| 快取項目數 | 120           |
| 快取前綴   | query_result: |
| 預設 TTL   | 300 秒        |
+------------+---------------+
```

### 2. 清除快取

#### 清除所有快取
```bash
docker-compose exec app php artisan api:cache-clear all
```

#### 清除配置快取
```bash
docker-compose exec app php artisan api:cache-clear configuration
```

#### 清除權限快取
```bash
docker-compose exec app php artisan api:cache-clear permission
```

#### 清除查詢結果快取
```bash
docker-compose exec app php artisan api:cache-clear query
```

#### 清除特定 Function 的快取
```bash
docker-compose exec app php artisan api:cache-clear --function=user.create
```

#### 清除特定客戶端的權限快取
```bash
docker-compose exec app php artisan api:cache-clear permission --client=123
```

#### 清除特定角色的權限快取
```bash
docker-compose exec app php artisan api:cache-clear permission --role=5
```

### 3. 預熱快取

#### 預熱所有啟用的 Function
```bash
docker-compose exec app php artisan api:cache-warmup
```

#### 預熱特定 Function
```bash
docker-compose exec app php artisan api:cache-warmup --functions=user.create --functions=user.list
```

## 程式碼使用範例

### 1. 使用配置快取

```php
use App\Services\Configuration\ConfigurationCache;

class ExampleController extends Controller
{
    protected ConfigurationCache $configCache;
    
    public function __construct(ConfigurationCache $configCache)
    {
        $this->configCache = $configCache;
    }
    
    public function getFunction(string $identifier)
    {
        // 嘗試從快取取得
        $function = $this->configCache->get($identifier);
        
        if (!$function) {
            // 快取未命中，從資料庫載入
            $function = ApiFunction::where('identifier', $identifier)->first();
            
            // 儲存到快取
            $this->configCache->put($identifier, $function, 3600);
        }
        
        return $function;
    }
}
```

### 2. 使用權限快取

```php
use App\Services\Authorization\PermissionCache;

class AuthorizationService
{
    protected PermissionCache $permCache;
    
    public function __construct(PermissionCache $permCache)
    {
        $this->permCache = $permCache;
    }
    
    public function checkPermission(int $clientId, int $functionId): bool
    {
        // 先從快取檢查
        $cached = $this->permCache->checkFunctionPermission($clientId, $functionId);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // 快取未命中，執行權限檢查
        $hasPermission = $this->performPermissionCheck($clientId, $functionId);
        
        // 儲存到快取
        $this->permCache->putFunctionPermission($clientId, $functionId, $hasPermission);
        
        return $hasPermission;
    }
}
```

### 3. 使用查詢結果快取

```php
use App\Services\Database\QueryResultCache;

class UserService
{
    protected QueryResultCache $queryCache;
    
    public function __construct(QueryResultCache $queryCache)
    {
        $this->queryCache = $queryCache;
    }
    
    public function getActiveUsers(array $filters = [])
    {
        // 生成快取鍵
        $cacheKey = $this->queryCache->generateCacheKey('user.list', $filters);
        
        // 使用記憶化查詢
        return $this->queryCache->remember($cacheKey, function() use ($filters) {
            return DB::table('users')
                ->where('is_active', true)
                ->where($filters)
                ->get();
        }, 300); // 快取 5 分鐘
    }
}
```

### 4. 使用快取管理器

```php
use App\Services\Cache\CacheManager;

class FunctionController extends Controller
{
    protected CacheManager $cacheManager;
    
    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }
    
    public function update(Request $request, int $id)
    {
        $function = ApiFunction::findOrFail($id);
        $function->update($request->all());
        
        // 更新後自動清除相關快取
        $this->cacheManager->invalidateFunction(
            $function->identifier,
            $function->id
        );
        
        return response()->json(['success' => true]);
    }
}
```

## 快取配置

### 環境變數設定

在 `.env` 檔案中配置快取參數：

```env
# 配置快取
CACHE_CONFIGURATION_ENABLED=true
CACHE_CONFIGURATION_TTL=3600

# 權限快取
CACHE_PERMISSION_ENABLED=true
CACHE_PERMISSION_TTL=1800

# 查詢結果快取
CACHE_QUERY_RESULT_ENABLED=true
CACHE_QUERY_RESULT_TTL=300

# 自動清除快取
CACHE_AUTO_CLEAR_ON_UPDATE=true
CACHE_AUTO_CLEAR_ON_PERMISSION=true
CACHE_AUTO_CLEAR_ON_ROLE=true
```

### 配置檔案

在 `config/apicache.php` 中進行詳細配置：

```php
return [
    'configuration' => [
        'enabled' => env('CACHE_CONFIGURATION_ENABLED', true),
        'ttl' => env('CACHE_CONFIGURATION_TTL', 3600),
    ],
    
    'permission' => [
        'enabled' => env('CACHE_PERMISSION_ENABLED', true),
        'ttl' => env('CACHE_PERMISSION_TTL', 1800),
    ],
    
    'query_result' => [
        'enabled' => env('CACHE_QUERY_RESULT_ENABLED', true),
        'ttl' => env('CACHE_QUERY_RESULT_TTL', 300),
        'cacheable_functions' => [],
        'non_cacheable_functions' => [],
    ],
];
```

## 常見使用場景

### 場景 1: 部署新版本後清除快取

```bash
# 清除所有快取
docker-compose exec app php artisan api:cache-clear all

# 預熱重要配置
docker-compose exec app php artisan api:cache-warmup
```

### 場景 2: 更新 Function 配置後

```bash
# 清除特定 Function 的所有快取
docker-compose exec app php artisan api:cache-clear --function=user.create
```

### 場景 3: 修改權限設定後

```bash
# 清除所有權限快取
docker-compose exec app php artisan api:cache-clear permission
```

### 場景 4: 定期維護

```bash
# 查看快取使用情況
docker-compose exec app php artisan api:cache-stats

# 如果快取項目過多，清除舊快取
docker-compose exec app php artisan api:cache-clear query
```

## 監控和除錯

### 檢查 Redis 連線

```bash
docker-compose exec redis redis-cli ping
```

應該返回 `PONG`

### 查看 Redis 中的快取鍵

```bash
# 查看所有配置快取
docker-compose exec redis redis-cli KEYS "api_config:*"

# 查看所有權限快取
docker-compose exec redis redis-cli KEYS "client_perm:*"
docker-compose exec redis redis-cli KEYS "role_perm:*"
docker-compose exec redis redis-cli KEYS "func_perm:*"

# 查看所有查詢結果快取
docker-compose exec redis redis-cli KEYS "query_result:*"
```

### 查看特定快取內容

```bash
docker-compose exec redis redis-cli GET "api_config:user.create"
```

### 查看快取 TTL

```bash
docker-compose exec redis redis-cli TTL "api_config:user.create"
```

## 效能優化建議

### 1. 調整 TTL 設定
- **配置快取**: 變更不頻繁，可設定較長時間（1-2 小時）
- **權限快取**: 中等頻率變更，建議 30 分鐘
- **查詢結果快取**: 資料變更頻繁，建議 5-10 分鐘

### 2. 選擇性快取
對於經常變更的資料，可以不快取或設定較短的 TTL：

```php
// 在 config/apicache.php 中設定
'query_result' => [
    'non_cacheable_functions' => [
        'realtime.data',
        'user.current.status',
    ],
],
```

### 3. 預熱策略
在系統啟動或低峰時段預熱常用配置：

```bash
# 設定 Cron Job
0 */6 * * * docker-compose exec app php artisan api:cache-warmup
```

### 4. 監控快取命中率
定期檢查快取統計，調整快取策略：

```bash
docker-compose exec app php artisan api:cache-stats
```

## 故障排除

### 問題 1: 快取未生效

**檢查步驟**:
1. 確認 Redis 服務運行正常
2. 檢查 `.env` 中 `CACHE_DRIVER=redis`
3. 查看日誌檔案是否有錯誤

```bash
docker-compose exec app tail -f storage/logs/laravel.log
```

### 問題 2: 資料不一致

**解決方法**:
```bash
# 清除所有快取
docker-compose exec app php artisan api:cache-clear all

# 重新載入配置
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
```

### 問題 3: Redis 記憶體不足

**檢查 Redis 記憶體使用**:
```bash
docker-compose exec redis redis-cli INFO memory
```

**清除不必要的快取**:
```bash
docker-compose exec app php artisan api:cache-clear query
```

## 最佳實踐

1. **定期監控**: 每天檢查快取統計，了解使用情況
2. **合理設定 TTL**: 根據資料變更頻率調整快取時間
3. **自動失效**: 確保資料變更時自動清除相關快取
4. **預熱重要資料**: 系統啟動時預熱常用配置
5. **記錄日誌**: 啟用快取操作日誌，便於除錯

## 注意事項

⚠️ **重要提醒**:
- 所有快取命令必須在 Docker 容器內執行
- 修改配置後需要清除相關快取
- 定期檢查 Redis 記憶體使用情況
- 在生產環境中謹慎使用 `cache-clear all`
- 快取失效可能導致短暫的效能下降

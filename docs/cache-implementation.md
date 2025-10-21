# 快取機制實作文件

## 概述

本系統實作了三層快取機制，用於提升 API 效能和減少資料庫負載：

1. **配置快取** - 快取 API Function 配置資訊
2. **權限快取** - 快取權限檢查結果
3. **查詢結果快取** - 快取資料庫查詢結果

## 快取架構

### 1. 配置快取 (ConfigurationCache)

**用途**: 快取 API Function 的配置資訊，包含參數定義、回應映射和錯誤映射。

**快取鍵格式**: `api_config:{function_identifier}`

**預設 TTL**: 3600 秒（1 小時）

**主要方法**:
```php
// 取得配置
$function = $configurationCache->get('user.create');

// 儲存配置
$configurationCache->put('user.create', $function, 3600);

// 移除配置
$configurationCache->forget('user.create');

// 清除所有配置快取
$configurationCache->flush();
```

### 2. 權限快取 (PermissionCache)

**用途**: 快取權限檢查結果，減少資料庫查詢次數。

**快取鍵格式**:
- 客戶端權限: `client_perm:{client_id}`
- 角色權限: `role_perm:{role_id}`
- Function 權限: `func_perm:{client_id}:{function_id}`

**預設 TTL**: 1800 秒（30 分鐘）

**主要方法**:
```php
// 檢查 Function 權限
$hasPermission = $permissionCache->checkFunctionPermission($clientId, $functionId);

// 儲存權限檢查結果
$permissionCache->putFunctionPermission($clientId, $functionId, true, 1800);

// 清除客戶端權限快取
$permissionCache->forgetClientPermissions($clientId);

// 快取失效處理
$permissionCache->invalidate($clientId, $roleId, $functionId);
```

### 3. 查詢結果快取 (QueryResultCache)

**用途**: 快取常用的資料庫查詢結果。

**快取鍵格式**: `query_result:{function_identifier}:{param_hash}`

**預設 TTL**: 300 秒（5 分鐘）

**主要方法**:
```php
// 生成快取鍵
$cacheKey = $queryResultCache->generateCacheKey('user.list', ['status' => 'active']);

// 記憶化查詢
$result = $queryResultCache->remember($cacheKey, function() {
    return DB::select('CALL sp_get_users(?)');
}, 300);

// 清除 Function 的所有查詢快取
$queryResultCache->forgetByFunction('user.list');
```

## 快取管理器 (CacheManager)

統一管理所有快取操作的中央管理器。

**主要功能**:

### 清除所有快取
```php
$cacheManager->flushAll();
```

### Function 更新時的快取失效
```php
$cacheManager->invalidateFunction('user.create', $functionId);
```

### 客戶端更新時的快取失效
```php
$cacheManager->invalidateClient($clientId);
```

### 角色更新時的快取失效
```php
$cacheManager->invalidateRole($roleId);
```

### 取得快取統計資訊
```php
$stats = $cacheManager->getStats();
```

## Artisan 命令

### 清除快取

```bash
# 清除所有快取
php artisan api:cache-clear

# 清除配置快取
php artisan api:cache-clear configuration

# 清除權限快取
php artisan api:cache-clear permission

# 清除查詢結果快取
php artisan api:cache-clear query

# 清除特定 Function 的快取
php artisan api:cache-clear --function=user.create

# 清除特定客戶端的權限快取
php artisan api:cache-clear permission --client=123

# 清除特定角色的權限快取
php artisan api:cache-clear permission --role=5
```

### 查看快取統計

```bash
php artisan api:cache-stats
```

### 預熱快取

```bash
# 預熱所有啟用的 Function
php artisan api:cache-warmup

# 預熱特定 Function
php artisan api:cache-warmup --functions=user.create --functions=user.list
```

## 配置檔案

快取設定位於 `config/apicache.php`：

```php
return [
    // 配置快取設定
    'configuration' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
    
    // 權限快取設定
    'permission' => [
        'enabled' => true,
        'ttl' => 1800,
    ],
    
    // 查詢結果快取設定
    'query_result' => [
        'enabled' => true,
        'ttl' => 300,
        'cacheable_functions' => [],
        'non_cacheable_functions' => [],
    ],
    
    // 快取失效策略
    'invalidation' => [
        'auto_clear_on_function_update' => true,
        'auto_clear_on_permission_change' => true,
        'auto_clear_on_role_change' => true,
    ],
];
```

## 環境變數

可在 `.env` 檔案中配置快取參數：

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

## 自動快取失效

系統實作了事件監聽器，當資料變更時自動清除相關快取：

### Function 更新時
- 清除配置快取
- 清除該 Function 的權限快取
- 清除該 Function 的查詢結果快取

### 客戶端更新時
- 清除客戶端權限快取
- 清除客戶端的 Function 權限快取

### 角色更新時
- 清除角色權限快取
- 清除所有擁有該角色的客戶端快取

## 最佳實踐

### 1. 快取鍵命名規範
- 使用有意義的前綴
- 包含必要的識別資訊
- 保持一致性

### 2. TTL 設定建議
- 配置資訊：較長（1 小時）
- 權限資訊：中等（30 分鐘）
- 查詢結果：較短（5 分鐘）

### 3. 快取失效策略
- 資料變更時立即失效相關快取
- 定期清理過期快取
- 監控快取命中率

### 4. 效能優化
- 使用 Redis 作為快取驅動
- 批次操作減少網路往返
- 預熱常用配置

## 監控和除錯

### 查看快取統計
```php
$stats = $cacheManager->getStats();
```

### 健康檢查
```php
$health = $cacheManager->healthCheck();
```

### 日誌記錄
系統會記錄以下快取操作：
- 快取命中/未命中
- 快取寫入/刪除
- 快取失效處理
- 錯誤和異常

## 故障排除

### 快取未生效
1. 檢查 Redis 連線狀態
2. 確認快取驅動配置正確
3. 檢查快取是否被意外清除

### 快取資料不一致
1. 檢查快取失效邏輯是否正確執行
2. 確認 TTL 設定合理
3. 手動清除快取並重新載入

### 效能問題
1. 監控快取命中率
2. 調整 TTL 設定
3. 考慮增加快取容量

## 注意事項

1. **快取一致性**: 確保資料變更時正確清除相關快取
2. **記憶體使用**: 監控 Redis 記憶體使用情況
3. **快取穿透**: 對不存在的資料也應快取（設定較短 TTL）
4. **快取雪崩**: 避免大量快取同時過期
5. **快取預熱**: 系統啟動時預熱重要配置

## 未來改進

1. 實作快取命中率統計
2. 支援分散式快取
3. 實作快取預熱排程
4. 增加快取壓縮功能
5. 實作快取版本控制

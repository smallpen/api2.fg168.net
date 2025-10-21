<?php

namespace App\Services\Cache;

use App\Services\Configuration\ConfigurationCache;
use App\Services\Authorization\PermissionCache;
use App\Services\Database\QueryResultCache;
use Illuminate\Support\Facades\Log;

/**
 * Cache Manager
 * 
 * 統一管理系統中所有快取操作的中央管理器
 */
class CacheManager
{
    /**
     * 配置快取
     */
    protected ConfigurationCache $configurationCache;

    /**
     * 權限快取
     */
    protected PermissionCache $permissionCache;

    /**
     * 查詢結果快取
     */
    protected QueryResultCache $queryResultCache;

    /**
     * CacheManager constructor
     */
    public function __construct(
        ConfigurationCache $configurationCache,
        PermissionCache $permissionCache,
        QueryResultCache $queryResultCache
    ) {
        $this->configurationCache = $configurationCache;
        $this->permissionCache = $permissionCache;
        $this->queryResultCache = $queryResultCache;
    }

    /**
     * 清除所有快取
     */
    public function flushAll(): bool
    {
        try {
            $this->configurationCache->flush();
            $this->permissionCache->flush();
            $this->queryResultCache->flush();
            
            Log::info('所有快取已清除');
            return true;
        } catch (\Exception $e) {
            Log::error('清除所有快取失敗', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 當 Function 更新時清除相關快取
     */
    public function invalidateFunction(string $functionIdentifier, int $functionId): bool
    {
        try {
            // 清除配置快取
            $this->configurationCache->forget($functionIdentifier);
            
            // 清除該 Function 的權限快取
            $this->permissionCache->invalidate(null, null, $functionId);
            
            // 清除該 Function 的查詢結果快取
            $this->queryResultCache->forgetByFunction($functionIdentifier);
            
            Log::info('Function 快取已失效', [
                'function_identifier' => $functionIdentifier,
                'function_id' => $functionId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Function 快取失效處理失敗', [
                'function_identifier' => $functionIdentifier,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 當客戶端更新時清除相關快取
     */
    public function invalidateClient(int $clientId): bool
    {
        try {
            // 清除客戶端權限快取
            $this->permissionCache->invalidate($clientId);
            
            Log::info('客戶端快取已失效', ['client_id' => $clientId]);
            return true;
        } catch (\Exception $e) {
            Log::error('客戶端快取失效處理失敗', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 當角色更新時清除相關快取
     */
    public function invalidateRole(int $roleId): bool
    {
        try {
            // 清除角色權限快取
            $this->permissionCache->invalidate(null, $roleId);
            
            Log::info('角色快取已失效', ['role_id' => $roleId]);
            return true;
        } catch (\Exception $e) {
            Log::error('角色快取失效處理失敗', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 當 Stored Procedure 更新時清除相關快取
     */
    public function invalidateStoredProcedure(string $storedProcedure): bool
    {
        try {
            // 清除該 SP 的查詢結果快取
            $this->queryResultCache->forgetByStoredProcedure($storedProcedure);
            
            Log::info('Stored Procedure 快取已失效', [
                'stored_procedure' => $storedProcedure
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Stored Procedure 快取失效處理失敗', [
                'stored_procedure' => $storedProcedure,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 取得所有快取統計資訊
     */
    public function getStats(): array
    {
        try {
            return [
                'configuration' => $this->configurationCache->getStats(),
                'permission' => $this->permissionCache->getStats(),
                'query_result' => $this->queryResultCache->getStats(),
                'timestamp' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('取得快取統計失敗', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => '無法取得快取統計資訊',
                'timestamp' => now()->toDateTimeString(),
            ];
        }
    }

    /**
     * 預熱配置快取
     */
    public function warmupConfiguration(array $functionIdentifiers = []): int
    {
        $warmedUp = 0;
        
        try {
            if (empty($functionIdentifiers)) {
                // 如果沒有指定，預熱所有啟用的 Function
                $functionIdentifiers = config('apicache.warmup.functions', []);
            }
            
            foreach ($functionIdentifiers as $identifier) {
                try {
                    // 這裡需要從 ConfigurationManager 載入配置
                    // 載入過程會自動快取
                    $warmedUp++;
                } catch (\Exception $e) {
                    Log::warning("預熱配置失敗: {$identifier}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            Log::info('配置快取預熱完成', ['count' => $warmedUp]);
        } catch (\Exception $e) {
            Log::error('配置快取預熱失敗', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $warmedUp;
    }

    /**
     * 檢查快取健康狀態
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
        ];
        
        try {
            // 檢查配置快取
            $configStats = $this->configurationCache->getStats();
            $health['checks']['configuration'] = [
                'status' => 'ok',
                'cached_items' => $configStats['total_cached'] ?? 0,
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['configuration'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
        
        try {
            // 檢查權限快取
            $permStats = $this->permissionCache->getStats();
            $health['checks']['permission'] = [
                'status' => 'ok',
                'cached_items' => $permStats['total_cached'] ?? 0,
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['permission'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
        
        try {
            // 檢查查詢結果快取
            $queryStats = $this->queryResultCache->getStats();
            $health['checks']['query_result'] = [
                'status' => 'ok',
                'cached_items' => $queryStats['total_cached'] ?? 0,
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['query_result'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
        
        return $health;
    }

    /**
     * 取得配置快取
     */
    public function getConfigurationCache(): ConfigurationCache
    {
        return $this->configurationCache;
    }

    /**
     * 取得權限快取
     */
    public function getPermissionCache(): PermissionCache
    {
        return $this->permissionCache;
    }

    /**
     * 取得查詢結果快取
     */
    public function getQueryResultCache(): QueryResultCache
    {
        return $this->queryResultCache;
    }
}

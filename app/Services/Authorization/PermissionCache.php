<?php

namespace App\Services\Authorization;

use App\Models\ApiClient;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Cache;

/**
 * Permission Cache
 * 
 * 使用 Redis 快取權限檢查結果，提升授權效能
 */
class PermissionCache
{
    /**
     * 快取鍵前綴
     */
    protected const CACHE_PREFIX = 'permission:';

    /**
     * 客戶端權限快取前綴
     */
    protected const CLIENT_PERMISSION_PREFIX = 'client_perm:';

    /**
     * 角色權限快取前綴
     */
    protected const ROLE_PERMISSION_PREFIX = 'role_perm:';

    /**
     * Function 權限快取前綴
     */
    protected const FUNCTION_PERMISSION_PREFIX = 'func_perm:';

    /**
     * 預設快取時間（秒）- 30 分鐘
     */
    protected const DEFAULT_TTL = 1800;

    /**
     * 快取驅動
     */
    protected $cache;

    /**
     * PermissionCache constructor
     */
    public function __construct()
    {
        $this->cache = Cache::store(config('cache.default'));
    }

    /**
     * 取得客戶端的所有權限（快取）
     * 
     * @param int $clientId 客戶端 ID
     * @return array 權限陣列
     */
    public function getClientPermissions(int $clientId): ?array
    {
        $key = $this->getClientPermissionKey($clientId);
        
        try {
            return $this->cache->get($key);
        } catch (\Exception $e) {
            \Log::warning("權限快取讀取失敗: client_{$clientId}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 儲存客戶端權限到快取
     * 
     * @param int $clientId 客戶端 ID
     * @param array $permissions 權限陣列
     * @param int|null $ttl 快取時間（秒）
     * @return bool
     */
    public function putClientPermissions(int $clientId, array $permissions, ?int $ttl = null): bool
    {
        $key = $this->getClientPermissionKey($clientId);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            return $this->cache->put($key, $permissions, $ttl);
        } catch (\Exception $e) {
            \Log::error("權限快取寫入失敗: client_{$clientId}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除客戶端權限快取
     * 
     * @param int $clientId 客戶端 ID
     * @return bool
     */
    public function forgetClientPermissions(int $clientId): bool
    {
        $key = $this->getClientPermissionKey($clientId);
        
        try {
            return $this->cache->forget($key);
        } catch (\Exception $e) {
            \Log::warning("權限快取刪除失敗: client_{$clientId}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 取得角色的所有權限（快取）
     * 
     * @param int $roleId 角色 ID
     * @return array|null 權限陣列
     */
    public function getRolePermissions(int $roleId): ?array
    {
        $key = $this->getRolePermissionKey($roleId);
        
        try {
            return $this->cache->get($key);
        } catch (\Exception $e) {
            \Log::warning("角色權限快取讀取失敗: role_{$roleId}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 儲存角色權限到快取
     * 
     * @param int $roleId 角色 ID
     * @param array $permissions 權限陣列
     * @param int|null $ttl 快取時間（秒）
     * @return bool
     */
    public function putRolePermissions(int $roleId, array $permissions, ?int $ttl = null): bool
    {
        $key = $this->getRolePermissionKey($roleId);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            return $this->cache->put($key, $permissions, $ttl);
        } catch (\Exception $e) {
            \Log::error("角色權限快取寫入失敗: role_{$roleId}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除角色權限快取
     * 
     * @param int $roleId 角色 ID
     * @return bool
     */
    public function forgetRolePermissions(int $roleId): bool
    {
        $key = $this->getRolePermissionKey($roleId);
        
        try {
            return $this->cache->forget($key);
        } catch (\Exception $e) {
            \Log::warning("角色權限快取刪除失敗: role_{$roleId}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 檢查客戶端是否有權限存取 Function（快取）
     * 
     * @param int $clientId 客戶端 ID
     * @param int $functionId Function ID
     * @return bool|null null 表示快取未命中
     */
    public function checkFunctionPermission(int $clientId, int $functionId): ?bool
    {
        $key = $this->getFunctionPermissionKey($clientId, $functionId);
        
        try {
            $cached = $this->cache->get($key);
            return $cached !== null ? (bool) $cached : null;
        } catch (\Exception $e) {
            \Log::warning("Function 權限快取讀取失敗: client_{$clientId}_func_{$functionId}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 儲存 Function 權限檢查結果到快取
     * 
     * @param int $clientId 客戶端 ID
     * @param int $functionId Function ID
     * @param bool $hasPermission 是否有權限
     * @param int|null $ttl 快取時間（秒）
     * @return bool
     */
    public function putFunctionPermission(int $clientId, int $functionId, bool $hasPermission, ?int $ttl = null): bool
    {
        $key = $this->getFunctionPermissionKey($clientId, $functionId);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            return $this->cache->put($key, $hasPermission, $ttl);
        } catch (\Exception $e) {
            \Log::error("Function 權限快取寫入失敗: client_{$clientId}_func_{$functionId}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除 Function 權限快取
     * 
     * @param int $clientId 客戶端 ID
     * @param int|null $functionId Function ID，null 表示清除該客戶端所有 Function 權限
     * @return bool
     */
    public function forgetFunctionPermission(int $clientId, ?int $functionId = null): bool
    {
        try {
            if ($functionId === null) {
                // 清除該客戶端所有 Function 權限快取
                $pattern = self::FUNCTION_PERMISSION_PREFIX . $clientId . ':*';
                return $this->forgetByPattern($pattern);
            } else {
                $key = $this->getFunctionPermissionKey($clientId, $functionId);
                return $this->cache->forget($key);
            }
        } catch (\Exception $e) {
            \Log::warning("Function 權限快取刪除失敗", [
                'client_id' => $clientId,
                'function_id' => $functionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除所有權限快取
     * 
     * @return bool
     */
    public function flush(): bool
    {
        try {
            // 清除所有權限相關快取
            $this->forgetByPattern(self::CACHE_PREFIX . '*');
            $this->forgetByPattern(self::CLIENT_PERMISSION_PREFIX . '*');
            $this->forgetByPattern(self::ROLE_PERMISSION_PREFIX . '*');
            $this->forgetByPattern(self::FUNCTION_PERMISSION_PREFIX . '*');
            
            return true;
        } catch (\Exception $e) {
            \Log::error("權限快取清除失敗", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 當權限變更時，清除相關快取
     * 
     * @param int|null $clientId 客戶端 ID
     * @param int|null $roleId 角色 ID
     * @param int|null $functionId Function ID
     * @return bool
     */
    public function invalidate(?int $clientId = null, ?int $roleId = null, ?int $functionId = null): bool
    {
        try {
            if ($clientId !== null) {
                $this->forgetClientPermissions($clientId);
                $this->forgetFunctionPermission($clientId);
            }

            if ($roleId !== null) {
                $this->forgetRolePermissions($roleId);
                
                // 清除所有擁有此角色的客戶端快取
                $this->invalidateClientsByRole($roleId);
            }

            if ($functionId !== null) {
                // 清除所有客戶端對此 Function 的權限快取
                $this->invalidateFunctionPermissions($functionId);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error("權限快取失效處理失敗", [
                'client_id' => $clientId,
                'role_id' => $roleId,
                'function_id' => $functionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除擁有特定角色的所有客戶端快取
     */
    protected function invalidateClientsByRole(int $roleId): void
    {
        try {
            $role = Role::with('clients')->find($roleId);
            
            if ($role) {
                foreach ($role->clients as $client) {
                    $this->forgetClientPermissions($client->id);
                    $this->forgetFunctionPermission($client->id);
                }
            }
        } catch (\Exception $e) {
            \Log::warning("清除角色客戶端快取失敗: role_{$roleId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 清除特定 Function 的所有權限快取
     */
    protected function invalidateFunctionPermissions(int $functionId): void
    {
        try {
            $pattern = self::FUNCTION_PERMISSION_PREFIX . '*:' . $functionId;
            $this->forgetByPattern($pattern);
        } catch (\Exception $e) {
            \Log::warning("清除 Function 權限快取失敗: func_{$functionId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 根據模式刪除快取
     */
    protected function forgetByPattern(string $pattern): bool
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = \Illuminate\Support\Facades\Redis::connection();
                $keys = $redis->keys($pattern);
                
                if (!empty($keys)) {
                    foreach ($keys as $key) {
                        $redis->del($key);
                    }
                }
                
                return true;
            } else {
                // 如果不是 Redis，清除所有快取
                $this->cache->flush();
                return true;
            }
        } catch (\Exception $e) {
            \Log::error("模式刪除快取失敗: {$pattern}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 取得客戶端權限快取鍵
     */
    protected function getClientPermissionKey(int $clientId): string
    {
        return self::CLIENT_PERMISSION_PREFIX . $clientId;
    }

    /**
     * 取得角色權限快取鍵
     */
    protected function getRolePermissionKey(int $roleId): string
    {
        return self::ROLE_PERMISSION_PREFIX . $roleId;
    }

    /**
     * 取得 Function 權限快取鍵
     */
    protected function getFunctionPermissionKey(int $clientId, int $functionId): string
    {
        return self::FUNCTION_PERMISSION_PREFIX . $clientId . ':' . $functionId;
    }

    /**
     * 取得快取統計資訊
     */
    public function getStats(): array
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = \Illuminate\Support\Facades\Redis::connection();
                
                $clientPermKeys = $redis->keys(self::CLIENT_PERMISSION_PREFIX . '*');
                $rolePermKeys = $redis->keys(self::ROLE_PERMISSION_PREFIX . '*');
                $funcPermKeys = $redis->keys(self::FUNCTION_PERMISSION_PREFIX . '*');
                
                return [
                    'client_permissions_cached' => count($clientPermKeys),
                    'role_permissions_cached' => count($rolePermKeys),
                    'function_permissions_cached' => count($funcPermKeys),
                    'total_cached' => count($clientPermKeys) + count($rolePermKeys) + count($funcPermKeys),
                    'default_ttl' => self::DEFAULT_TTL,
                ];
            }
        } catch (\Exception $e) {
            \Log::warning("無法取得權限快取統計", [
                'error' => $e->getMessage()
            ]);
        }

        return [
            'client_permissions_cached' => 0,
            'role_permissions_cached' => 0,
            'function_permissions_cached' => 0,
            'total_cached' => 0,
            'default_ttl' => self::DEFAULT_TTL,
        ];
    }
}

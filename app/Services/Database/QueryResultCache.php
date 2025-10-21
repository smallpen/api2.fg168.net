<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Query Result Cache
 * 
 * 快取常用的資料庫查詢結果，提升 API 回應效能
 */
class QueryResultCache
{
    /**
     * 快取鍵前綴
     */
    protected const CACHE_PREFIX = 'query_result:';

    /**
     * 預設快取時間（秒）- 5 分鐘
     */
    protected const DEFAULT_TTL = 300;

    /**
     * 快取驅動
     */
    protected $cache;

    /**
     * QueryResultCache constructor
     */
    public function __construct()
    {
        $this->cache = Cache::store(config('cache.default'));
    }

    /**
     * 取得快取的查詢結果
     * 
     * @param string $cacheKey 快取鍵
     * @return mixed|null
     */
    public function get(string $cacheKey)
    {
        $key = $this->getCacheKey($cacheKey);
        
        try {
            $cached = $this->cache->get($key);
            
            if ($cached !== null) {
                Log::debug('查詢結果快取命中', ['cache_key' => $cacheKey]);
            }
            
            return $cached;
        } catch (\Exception $e) {
            Log::warning("查詢結果快取讀取失敗: {$cacheKey}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 儲存查詢結果到快取
     * 
     * @param string $cacheKey 快取鍵
     * @param mixed $result 查詢結果
     * @param int|null $ttl 快取時間（秒）
     * @return bool
     */
    public function put(string $cacheKey, $result, ?int $ttl = null): bool
    {
        $key = $this->getCacheKey($cacheKey);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            $success = $this->cache->put($key, $result, $ttl);
            
            if ($success) {
                Log::debug('查詢結果已快取', [
                    'cache_key' => $cacheKey,
                    'ttl' => $ttl
                ]);
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error("查詢結果快取寫入失敗: {$cacheKey}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 記憶化查詢（如果快取存在則返回，否則執行查詢並快取）
     * 
     * @param string $cacheKey 快取鍵
     * @param callable $callback 查詢回呼函數
     * @param int|null $ttl 快取時間（秒）
     * @return mixed
     */
    public function remember(string $cacheKey, callable $callback, ?int $ttl = null)
    {
        $key = $this->getCacheKey($cacheKey);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            return $this->cache->remember($key, $ttl, function () use ($cacheKey, $callback) {
                Log::debug('執行查詢並快取結果', ['cache_key' => $cacheKey]);
                return $callback();
            });
        } catch (\Exception $e) {
            Log::error("查詢結果記憶化失敗: {$cacheKey}", [
                'error' => $e->getMessage()
            ]);
            // 如果快取失敗，直接執行查詢
            return $callback();
        }
    }

    /**
     * 移除快取的查詢結果
     * 
     * @param string $cacheKey 快取鍵
     * @return bool
     */
    public function forget(string $cacheKey): bool
    {
        $key = $this->getCacheKey($cacheKey);
        
        try {
            return $this->cache->forget($key);
        } catch (\Exception $e) {
            Log::warning("查詢結果快取刪除失敗: {$cacheKey}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 檢查快取是否存在
     * 
     * @param string $cacheKey 快取鍵
     * @return bool
     */
    public function has(string $cacheKey): bool
    {
        $key = $this->getCacheKey($cacheKey);
        
        try {
            return $this->cache->has($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 根據 Function 和參數生成快取鍵
     * 
     * @param string $functionIdentifier Function 識別碼
     * @param array $parameters 查詢參數
     * @return string
     */
    public function generateCacheKey(string $functionIdentifier, array $parameters = []): string
    {
        // 排序參數以確保相同參數產生相同的快取鍵
        ksort($parameters);
        
        // 生成參數的雜湊值
        $paramHash = md5(json_encode($parameters));
        
        return "{$functionIdentifier}:{$paramHash}";
    }

    /**
     * 根據 Stored Procedure 和參數生成快取鍵
     * 
     * @param string $storedProcedure SP 名稱
     * @param array $parameters SP 參數
     * @return string
     */
    public function generateSpCacheKey(string $storedProcedure, array $parameters = []): string
    {
        // 排序參數以確保相同參數產生相同的快取鍵
        ksort($parameters);
        
        // 生成參數的雜湊值
        $paramHash = md5(json_encode($parameters));
        
        return "sp:{$storedProcedure}:{$paramHash}";
    }

    /**
     * 清除 Function 的所有快取結果
     * 
     * @param string $functionIdentifier Function 識別碼
     * @return bool
     */
    public function forgetByFunction(string $functionIdentifier): bool
    {
        try {
            $pattern = self::CACHE_PREFIX . $functionIdentifier . ':*';
            return $this->forgetByPattern($pattern);
        } catch (\Exception $e) {
            Log::error("清除 Function 查詢快取失敗: {$functionIdentifier}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除 Stored Procedure 的所有快取結果
     * 
     * @param string $storedProcedure SP 名稱
     * @return bool
     */
    public function forgetByStoredProcedure(string $storedProcedure): bool
    {
        try {
            $pattern = self::CACHE_PREFIX . "sp:{$storedProcedure}:*";
            return $this->forgetByPattern($pattern);
        } catch (\Exception $e) {
            Log::error("清除 SP 查詢快取失敗: {$storedProcedure}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除所有查詢結果快取
     * 
     * @return bool
     */
    public function flush(): bool
    {
        try {
            $pattern = self::CACHE_PREFIX . '*';
            return $this->forgetByPattern($pattern);
        } catch (\Exception $e) {
            Log::error("清除所有查詢快取失敗", [
                'error' => $e->getMessage()
            ]);
            return false;
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
                    
                    Log::debug('批次刪除快取', [
                        'pattern' => $pattern,
                        'count' => count($keys)
                    ]);
                }
                
                return true;
            } else {
                // 如果不是 Redis，清除所有快取
                $this->cache->flush();
                return true;
            }
        } catch (\Exception $e) {
            Log::error("模式刪除快取失敗: {$pattern}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 取得完整的快取鍵
     */
    protected function getCacheKey(string $cacheKey): string
    {
        return self::CACHE_PREFIX . $cacheKey;
    }

    /**
     * 取得快取統計資訊
     */
    public function getStats(): array
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = \Illuminate\Support\Facades\Redis::connection();
                $keys = $redis->keys(self::CACHE_PREFIX . '*');
                
                return [
                    'total_cached' => count($keys),
                    'cache_prefix' => self::CACHE_PREFIX,
                    'default_ttl' => self::DEFAULT_TTL,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("無法取得查詢快取統計", [
                'error' => $e->getMessage()
            ]);
        }

        return [
            'total_cached' => 0,
            'cache_prefix' => self::CACHE_PREFIX,
            'default_ttl' => self::DEFAULT_TTL,
        ];
    }

    /**
     * 設定快取標籤（用於批次清除）
     * 
     * @param array $tags 標籤陣列
     * @return self
     */
    public function tags(array $tags): self
    {
        if (method_exists($this->cache, 'tags')) {
            $this->cache = $this->cache->tags($tags);
        }
        
        return $this;
    }
}

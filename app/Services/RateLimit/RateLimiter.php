<?php

namespace App\Services\RateLimit;

use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

/**
 * Rate Limiter 服務
 * 
 * 使用 Redis 實作 Sliding Window 演算法來限制 API 請求頻率
 */
class RateLimiter
{
    /**
     * Redis 連線名稱
     */
    protected string $connection;

    /**
     * 快取鍵前綴
     */
    protected string $prefix;

    /**
     * 建構函數
     */
    public function __construct()
    {
        $this->connection = config('ratelimit.redis.connection', 'default');
        $this->prefix = config('ratelimit.prefix', 'rate_limit:');
    }

    /**
     * 檢查客戶端是否超過速率限制
     * 
     * @param string $clientId 客戶端 ID
     * @param int $maxAttempts 最大請求次數
     * @param int $decaySeconds 時間窗口（秒）
     * @return bool 是否允許請求
     */
    public function tooManyAttempts(string $clientId, int $maxAttempts, int $decaySeconds): bool
    {
        $key = $this->resolveKey($clientId);
        $currentAttempts = $this->attempts($clientId);

        return $currentAttempts >= $maxAttempts;
    }

    /**
     * 增加客戶端的請求計數
     * 
     * @param string $clientId 客戶端 ID
     * @param int $decaySeconds 時間窗口（秒）
     * @return int 當前請求次數
     */
    public function hit(string $clientId, int $decaySeconds = 60): int
    {
        $key = $this->resolveKey($clientId);
        $timestamp = Carbon::now()->timestamp;

        // 使用 Redis Sorted Set 實作 Sliding Window
        $redis = Redis::connection($this->connection);
        
        // 移除時間窗口外的舊記錄
        $windowStart = $timestamp - $decaySeconds;
        $redis->zremrangebyscore($key, 0, $windowStart);

        // 新增當前請求
        $redis->zadd($key, $timestamp, $timestamp . ':' . uniqid());

        // 設定過期時間
        $redis->expire($key, $decaySeconds + 10);

        // 返回當前時間窗口內的請求次數
        return $redis->zcount($key, $windowStart, $timestamp);
    }

    /**
     * 獲取客戶端當前的請求次數
     * 
     * @param string $clientId 客戶端 ID
     * @param int $decaySeconds 時間窗口（秒）
     * @return int 當前請求次數
     */
    public function attempts(string $clientId, int $decaySeconds = 60): int
    {
        $key = $this->resolveKey($clientId);
        $timestamp = Carbon::now()->timestamp;
        $windowStart = $timestamp - $decaySeconds;

        $redis = Redis::connection($this->connection);
        
        // 移除時間窗口外的舊記錄
        $redis->zremrangebyscore($key, 0, $windowStart);

        // 返回當前時間窗口內的請求次數
        return $redis->zcount($key, $windowStart, $timestamp);
    }

    /**
     * 重置客戶端的請求計數
     * 
     * @param string $clientId 客戶端 ID
     * @return void
     */
    public function resetAttempts(string $clientId): void
    {
        $key = $this->resolveKey($clientId);
        Redis::connection($this->connection)->del($key);
    }

    /**
     * 獲取客戶端剩餘的請求次數
     * 
     * @param string $clientId 客戶端 ID
     * @param int $maxAttempts 最大請求次數
     * @return int 剩餘請求次數
     */
    public function remaining(string $clientId, int $maxAttempts): int
    {
        $attempts = $this->attempts($clientId);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * 獲取速率限制重置的時間（秒）
     * 
     * @param string $clientId 客戶端 ID
     * @param int $decaySeconds 時間窗口（秒）
     * @return int 重置時間（秒）
     */
    public function availableIn(string $clientId, int $decaySeconds = 60): int
    {
        $key = $this->resolveKey($clientId);
        $redis = Redis::connection($this->connection);
        
        // 獲取最早的請求時間戳
        $earliest = $redis->zrange($key, 0, 0, 'WITHSCORES');
        
        if (empty($earliest)) {
            return 0;
        }

        $earliestTimestamp = (int) array_values($earliest)[0];
        $resetTime = $earliestTimestamp + $decaySeconds;
        $now = Carbon::now()->timestamp;

        return max(0, $resetTime - $now);
    }

    /**
     * 清除客戶端的速率限制
     * 
     * @param string $clientId 客戶端 ID
     * @return void
     */
    public function clear(string $clientId): void
    {
        $this->resetAttempts($clientId);
    }

    /**
     * 解析快取鍵
     * 
     * @param string $clientId 客戶端 ID
     * @return string 完整的快取鍵
     */
    protected function resolveKey(string $clientId): string
    {
        return $this->prefix . $clientId;
    }

    /**
     * 設定 Redis 連線名稱
     * 
     * @param string $connection 連線名稱
     * @return self
     */
    public function setConnection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * 設定快取鍵前綴
     * 
     * @param string $prefix 前綴
     * @return self
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }
}

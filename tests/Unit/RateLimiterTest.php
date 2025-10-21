<?php

namespace Tests\Unit;

use App\Services\RateLimit\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Rate Limiter 單元測試
 */
class RateLimiterTest extends TestCase
{
    protected RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimiter = new RateLimiter();
    }

    protected function tearDown(): void
    {
        // 清理測試資料
        Redis::connection('default')->flushdb();
        parent::tearDown();
    }

    /**
     * 測試基本的速率限制功能
     */
    public function test_rate_limiter_allows_requests_within_limit(): void
    {
        $clientId = 'test_client_1';
        $maxAttempts = 5;
        $decaySeconds = 60;

        // 前 5 次請求應該被允許
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->assertFalse(
                $this->rateLimiter->tooManyAttempts($clientId, $maxAttempts, $decaySeconds),
                "第 " . ($i + 1) . " 次請求應該被允許"
            );
            $this->rateLimiter->hit($clientId, $decaySeconds);
        }

        // 第 6 次請求應該被拒絕
        $this->assertTrue(
            $this->rateLimiter->tooManyAttempts($clientId, $maxAttempts, $decaySeconds),
            "超過限制的請求應該被拒絕"
        );
    }

    /**
     * 測試剩餘請求次數計算
     */
    public function test_rate_limiter_calculates_remaining_attempts(): void
    {
        $clientId = 'test_client_2';
        $maxAttempts = 10;
        $decaySeconds = 60;

        // 初始應該有 10 次剩餘
        $this->assertEquals(10, $this->rateLimiter->remaining($clientId, $maxAttempts));

        // 發送 3 次請求
        for ($i = 0; $i < 3; $i++) {
            $this->rateLimiter->hit($clientId, $decaySeconds);
        }

        // 應該剩餘 7 次
        $this->assertEquals(7, $this->rateLimiter->remaining($clientId, $maxAttempts));
    }

    /**
     * 測試重置功能
     */
    public function test_rate_limiter_can_reset_attempts(): void
    {
        $clientId = 'test_client_3';
        $maxAttempts = 5;
        $decaySeconds = 60;

        // 發送 5 次請求
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->rateLimiter->hit($clientId, $decaySeconds);
        }

        // 確認已達到限制
        $this->assertTrue($this->rateLimiter->tooManyAttempts($clientId, $maxAttempts, $decaySeconds));

        // 重置
        $this->rateLimiter->resetAttempts($clientId);

        // 應該可以再次請求
        $this->assertFalse($this->rateLimiter->tooManyAttempts($clientId, $maxAttempts, $decaySeconds));
    }
}

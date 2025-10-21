<?php

namespace App\Http\Middleware;

use App\Services\RateLimit\RateLimiter;
use App\Services\RateLimit\RateLimitException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Rate Limiting Middleware
 * 
 * 限制 API 請求頻率，防止濫用和保護系統資源
 */
class ThrottleApi
{
    /**
     * Rate Limiter 服務
     */
    protected RateLimiter $rateLimiter;

    /**
     * 預設最大請求次數
     */
    protected int $defaultMaxAttempts;

    /**
     * 預設時間窗口（秒）
     */
    protected int $defaultDecaySeconds;

    /**
     * 建構函數
     * 
     * @param RateLimiter $rateLimiter Rate Limiter 服務
     */
    public function __construct(RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
        $this->defaultMaxAttempts = config('ratelimit.default.max_attempts', 60);
        $this->defaultDecaySeconds = config('ratelimit.default.decay_seconds', 60);
    }

    /**
     * 處理傳入的請求
     *
     * @param Request $request HTTP 請求物件
     * @param Closure $next 下一個 Middleware
     * @param int|null $maxAttempts 最大請求次數（可選）
     * @param int|null $decaySeconds 時間窗口秒數（可選）
     * @return Response HTTP 回應
     */
    public function handle(Request $request, Closure $next, ?int $maxAttempts = null, ?int $decaySeconds = null): Response
    {
        // 獲取客戶端識別碼
        $clientId = $this->resolveClientId($request);

        // 獲取速率限制配置
        $maxAttempts = $maxAttempts ?? $this->getClientRateLimit($request);
        $decaySeconds = $decaySeconds ?? $this->defaultDecaySeconds;

        // 檢查是否超過速率限制
        if ($this->rateLimiter->tooManyAttempts($clientId, $maxAttempts, $decaySeconds)) {
            return $this->handleRateLimitExceeded($request, $clientId, $maxAttempts, $decaySeconds);
        }

        // 增加請求計數
        $this->rateLimiter->hit($clientId, $decaySeconds);

        // 處理請求
        $response = $next($request);

        // 在回應標頭中加入速率限制資訊
        return $this->addRateLimitHeaders(
            $response,
            $clientId,
            $maxAttempts,
            $decaySeconds
        );
    }

    /**
     * 處理超過速率限制的情況
     * 
     * @param Request $request HTTP 請求物件
     * @param string $clientId 客戶端 ID
     * @param int $maxAttempts 最大請求次數
     * @param int $decaySeconds 時間窗口（秒）
     * @return Response HTTP 回應
     */
    protected function handleRateLimitExceeded(
        Request $request,
        string $clientId,
        int $maxAttempts,
        int $decaySeconds
    ): Response {
        // 獲取重試時間
        $retryAfter = $this->rateLimiter->availableIn($clientId, $decaySeconds);
        $remaining = 0;

        // 記錄速率限制事件
        $this->logRateLimitExceeded($request, $clientId, $maxAttempts);

        // 建立錯誤回應
        $response = response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => '超過請求頻率限制，請稍後再試',
                'details' => [
                    'max_attempts' => $maxAttempts,
                    'retry_after' => $retryAfter,
                ],
            ],
            'meta' => [
                'request_id' => $this->generateRequestId(),
                'timestamp' => now()->toIso8601String(),
            ],
        ], 429);

        // 加入速率限制標頭
        return $this->addRateLimitHeaders(
            $response,
            $clientId,
            $maxAttempts,
            $decaySeconds,
            $retryAfter
        );
    }

    /**
     * 在回應中加入速率限制標頭
     * 
     * @param Response $response HTTP 回應
     * @param string $clientId 客戶端 ID
     * @param int $maxAttempts 最大請求次數
     * @param int $decaySeconds 時間窗口（秒）
     * @param int|null $retryAfter 重試時間（秒）
     * @return Response HTTP 回應
     */
    protected function addRateLimitHeaders(
        Response $response,
        string $clientId,
        int $maxAttempts,
        int $decaySeconds,
        ?int $retryAfter = null
    ): Response {
        // 獲取剩餘請求次數
        $remaining = $this->rateLimiter->remaining($clientId, $maxAttempts);
        
        // 如果沒有提供重試時間，計算下一次重置時間
        if ($retryAfter === null) {
            $retryAfter = $this->rateLimiter->availableIn($clientId, $decaySeconds);
            if ($retryAfter === 0) {
                $retryAfter = $decaySeconds;
            }
        }

        // 加入標準的速率限制標頭
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', now()->addSeconds($retryAfter)->timestamp);
        
        // 如果超過限制，加入 Retry-After 標頭
        if ($remaining <= 0) {
            $response->headers->set('Retry-After', $retryAfter);
        }

        return $response;
    }

    /**
     * 解析客戶端識別碼
     * 
     * @param Request $request HTTP 請求物件
     * @return string 客戶端識別碼
     */
    protected function resolveClientId(Request $request): string
    {
        // 優先使用已驗證的客戶端 ID
        $client = $request->attributes->get('api_client') ?? $request->get('authenticated_client');
        
        if ($client && isset($client->id)) {
            return 'client:' . $client->id;
        }

        // 如果沒有驗證的客戶端，使用 IP 地址
        return 'ip:' . $request->ip();
    }

    /**
     * 獲取客戶端的速率限制配置
     * 
     * @param Request $request HTTP 請求物件
     * @return int 最大請求次數
     */
    protected function getClientRateLimit(Request $request): int
    {
        // 從已驗證的客戶端獲取速率限制配置
        $client = $request->attributes->get('api_client') ?? $request->get('authenticated_client');
        
        if ($client && isset($client->rate_limit)) {
            return $this->parseRateLimit($client->rate_limit);
        }

        // 返回預設值
        return $this->defaultMaxAttempts;
    }

    /**
     * 解析速率限制字串
     * 
     * @param string|int $rateLimit 速率限制（例如："60/minute" 或 60）
     * @return int 最大請求次數
     */
    protected function parseRateLimit($rateLimit): int
    {
        if (is_numeric($rateLimit)) {
            return (int) $rateLimit;
        }

        // 解析 "60/minute" 格式
        if (is_string($rateLimit) && str_contains($rateLimit, '/')) {
            [$limit, $period] = explode('/', $rateLimit, 2);
            return (int) $limit;
        }

        return $this->defaultMaxAttempts;
    }

    /**
     * 記錄速率限制超過事件
     * 
     * @param Request $request HTTP 請求物件
     * @param string $clientId 客戶端 ID
     * @param int $maxAttempts 最大請求次數
     * @return void
     */
    protected function logRateLimitExceeded(Request $request, string $clientId, int $maxAttempts): void
    {
        \Log::warning('Rate limit exceeded', [
            'client_id' => $clientId,
            'max_attempts' => $maxAttempts,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
        ]);
    }

    /**
     * 生成請求 ID
     * 
     * @return string 請求 ID
     */
    protected function generateRequestId(): string
    {
        return 'req_' . bin2hex(random_bytes(16));
    }
}

<?php

namespace App\Http\Middleware;

use App\Helpers\SecurityHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * IP 白名單中介軟體
 * 
 * 限制只有白名單中的 IP 可以存取特定路由
 */
class IpWhitelist
{
    /**
     * 處理傳入的請求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $whitelist
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ?string $whitelist = null): Response
    {
        // 取得 IP 白名單
        $allowedIps = $this->getWhitelist($whitelist);

        // 如果沒有設定白名單，允許所有 IP
        if (empty($allowedIps)) {
            return $next($request);
        }

        // 取得客戶端 IP
        $clientIp = $request->ip();

        // 檢查 IP 是否在白名單中
        if (!SecurityHelper::isIpWhitelisted($clientIp, $allowedIps)) {
            // 記錄未授權的存取嘗試
            SecurityHelper::logSecurityEvent('ip_blocked', [
                'ip_address' => $clientIp,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IP_NOT_ALLOWED',
                    'message' => '您的 IP 位址不被允許存取此資源',
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-ID'),
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403);
        }

        return $next($request);
    }

    /**
     * 取得 IP 白名單
     *
     * @param  string|null  $whitelist
     * @return array
     */
    protected function getWhitelist(?string $whitelist): array
    {
        // 如果指定了白名單名稱，從配置中取得
        if ($whitelist) {
            return config("security.ip_whitelists.{$whitelist}", []);
        }

        // 否則使用預設白名單
        return config('security.ip_whitelists.default', []);
    }
}

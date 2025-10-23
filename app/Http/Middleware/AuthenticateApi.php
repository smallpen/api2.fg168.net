<?php

namespace App\Http\Middleware;

use App\Services\Authentication\AuthenticationManager;
use App\Services\Authentication\AuthenticationException;
use App\Services\Logging\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API 驗證 Middleware
 * 
 * 驗證所有 API 請求，確保只有已驗證的客戶端可以存取
 */
class AuthenticateApi
{
    /**
     * 驗證管理器
     */
    protected AuthenticationManager $authManager;

    /**
     * 安全日誌記錄器
     */
    protected SecurityLogger $securityLogger;

    /**
     * 建構函數
     */
    public function __construct(
        AuthenticationManager $authManager,
        SecurityLogger $securityLogger
    ) {
        $this->authManager = $authManager;
        $this->securityLogger = $securityLogger;
    }

    /**
     * 處理傳入的請求
     *
     * @param Request $request HTTP 請求物件
     * @param Closure $next 下一個 Middleware
     * @return Response HTTP 回應
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // 驗證請求
            $client = $this->authManager->authenticate($request);

            // 將已驗證的客戶端附加到請求中
            $request->attributes->set('api_client', $client);
            $request->merge(['authenticated_client' => $client]);

            // 記錄驗證成功
            $this->logAuthenticationSuccess($request, $client);

            return $next($request);

        } catch (AuthenticationException $e) {
            // 記錄驗證失敗
            $this->logAuthenticationFailure($request, $e);

            // 返回錯誤回應
            return $this->handleAuthenticationFailure($e);
        }
    }

    /**
     * 處理驗證失敗
     * 
     * @param AuthenticationException $exception 驗證例外
     * @return Response HTTP 回應
     */
    protected function handleAuthenticationFailure(AuthenticationException $exception): Response
    {
        $statusCode = $exception->getCode() ?: 401;

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ],
            'meta' => [
                'request_id' => $this->generateRequestId(),
                'timestamp' => now()->toIso8601String(),
            ],
        ], $statusCode);
    }

    /**
     * 記錄驗證成功
     * 
     * @param Request $request HTTP 請求物件
     * @param mixed $client 已驗證的客戶端
     * @return void
     */
    protected function logAuthenticationSuccess(Request $request, $client): void
    {
        // 記錄驗證成功的安全日誌
        $this->securityLogger->logAuthenticationSuccess(
            $client->id,
            $request->ip(),
            [
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]
        );
    }

    /**
     * 記錄驗證失敗
     * 
     * @param Request $request HTTP 請求物件
     * @param AuthenticationException $exception 驗證例外
     * @return void
     */
    protected function logAuthenticationFailure(Request $request, AuthenticationException $exception): void
    {
        // 記錄驗證失敗的安全日誌
        $this->securityLogger->logAuthenticationFailed(
            $request->ip(),
            [
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]
        );
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

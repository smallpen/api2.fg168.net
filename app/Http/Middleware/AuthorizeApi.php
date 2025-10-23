<?php

namespace App\Http\Middleware;

use App\Exceptions\AuthorizationException;
use App\Models\ApiClient;
use App\Models\ApiFunction;
use App\Services\Authorization\AuthorizationManager;
use App\Services\Logging\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorize API Middleware
 * 
 * 檢查客戶端是否有權限存取 API Function
 */
class AuthorizeApi
{
    /**
     * 授權管理器
     */
    protected AuthorizationManager $authorizationManager;

    /**
     * 安全日誌記錄器
     */
    protected SecurityLogger $securityLogger;

    /**
     * 建構函數
     */
    public function __construct(
        AuthorizationManager $authorizationManager,
        SecurityLogger $securityLogger
    ) {
        $this->authorizationManager = $authorizationManager;
        $this->securityLogger = $securityLogger;
    }

    /**
     * 處理傳入的請求
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 取得已驗證的客戶端（由 AuthenticateApi Middleware 設定）
        $client = $request->attributes->get('api_client');

        if (!$client instanceof ApiClient) {
            Log::error('授權檢查失敗：找不到已驗證的客戶端');
            throw new AuthorizationException('驗證資訊無效', 'AUTHENTICATION_REQUIRED', 401);
        }

        // 檢查客戶端是否啟用
        if (!$client->isActive()) {
            Log::warning('授權失敗：客戶端未啟用', [
                'client_id' => $client->id,
                'client_name' => $client->name,
            ]);
            throw AuthorizationException::clientDisabled();
        }

        // 取得要執行的 Function（從請求中取得）
        $function = $this->getFunctionFromRequest($request);

        if (!$function) {
            // 如果沒有指定 Function，繼續處理（可能是其他類型的請求）
            return $next($request);
        }

        // 檢查 Function 是否啟用
        if (!$function->is_active) {
            Log::warning('授權失敗：Function 未啟用', [
                'function_id' => $function->id,
                'function_name' => $function->name,
                'client_id' => $client->id,
            ]);
            throw AuthorizationException::functionDisabled($function->name);
        }

        // 執行授權檢查
        $authorized = $this->authorizationManager->authorize($client, $function);

        if (!$authorized) {
            Log::warning('授權失敗：權限不足', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'function_id' => $function->id,
                'function_name' => $function->name,
            ]);

            // 記錄到安全日誌
            $this->logSecurityEvent($client, $function, 'permission_denied');

            throw AuthorizationException::noFunctionAccess($function->name);
        }

        // 授權成功，將 Function 加入請求屬性
        $request->attributes->set('api_function', $function);

        Log::debug('授權成功', [
            'client_id' => $client->id,
            'function_id' => $function->id,
        ]);

        return $next($request);
    }

    /**
     * 從請求中取得 API Function
     */
    protected function getFunctionFromRequest(Request $request): ?ApiFunction
    {
        // 從請求 body 中取得 function identifier
        $functionIdentifier = $request->input('function');

        if (!$functionIdentifier) {
            // 也可以從路由參數中取得
            $functionIdentifier = $request->route('function');
        }

        if (!$functionIdentifier) {
            return null;
        }

        // 查找 Function
        $function = ApiFunction::where('identifier', $functionIdentifier)->first();

        if (!$function) {
            Log::warning('找不到指定的 Function', [
                'identifier' => $functionIdentifier,
            ]);
        }

        return $function;
    }

    /**
     * 記錄安全事件
     */
    protected function logSecurityEvent(ApiClient $client, ApiFunction $function, string $eventType): void
    {
        try {
            // 記錄到 security_logs 資料表
            $this->securityLogger->logPermissionDenied(
                $client->id,
                request()->ip(),
                [
                    'function_id' => $function->id,
                    'function_name' => $function->name,
                    'user_agent' => request()->userAgent(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('記錄安全事件失敗', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

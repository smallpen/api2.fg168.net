<?php

namespace App\Services\Authorization;

use App\Models\ApiClient;
use App\Models\ApiFunction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Authorization Manager
 * 
 * 負責管理和協調所有授權相關的操作
 */
class AuthorizationManager
{
    /**
     * 權限檢查器
     */
    protected PermissionChecker $permissionChecker;

    /**
     * 角色管理器
     */
    protected RoleManager $roleManager;

    /**
     * 權限快取
     */
    protected PermissionCache $permissionCache;

    /**
     * 快取過期時間（秒）
     */
    protected int $cacheExpiration = 3600;

    /**
     * 建構函數
     */
    public function __construct(
        PermissionChecker $permissionChecker,
        RoleManager $roleManager,
        PermissionCache $permissionCache
    ) {
        $this->permissionChecker = $permissionChecker;
        $this->roleManager = $roleManager;
        $this->permissionCache = $permissionCache;
    }

    /**
     * 檢查客戶端是否有權限執行指定的 API Function
     */
    public function authorize(ApiClient $client, ApiFunction $function): bool
    {
        // 檢查客戶端是否啟用
        if (!$client->isActive()) {
            Log::warning('授權失敗：客戶端未啟用', [
                'client_id' => $client->id,
                'client_name' => $client->name,
            ]);
            return false;
        }

        // 檢查 Function 是否啟用
        if (!$function->is_active) {
            Log::warning('授權失敗：Function 未啟用', [
                'function_id' => $function->id,
                'function_name' => $function->name,
            ]);
            return false;
        }

        // 先嘗試從快取取得權限檢查結果
        $cached = $this->permissionCache->checkFunctionPermission($client->id, $function->id);
        
        if ($cached !== null) {
            return $cached;
        }

        // 快取未命中，執行權限檢查
        $hasPermission = $this->permissionChecker->check($client, $function);
        
        // 儲存到快取
        $this->permissionCache->putFunctionPermission(
            $client->id,
            $function->id,
            $hasPermission,
            $this->cacheExpiration
        );
        
        return $hasPermission;
    }

    /**
     * 檢查客戶端是否有指定的權限
     */
    public function checkPermission(
        ApiClient $client,
        string $resourceType,
        ?int $resourceId = null,
        string $action = 'execute'
    ): bool {
        return $this->permissionChecker->checkPermission(
            $client,
            $resourceType,
            $resourceId,
            $action
        );
    }

    /**
     * 為客戶端授予角色
     */
    public function assignRole(ApiClient $client, string $roleName): bool
    {
        $result = $this->roleManager->assignRoleToClient($client, $roleName);
        
        if ($result) {
            // 使用新的快取失效機制
            $this->permissionCache->invalidate($client->id);
            
            Log::info('角色已授予客戶端', [
                'client_id' => $client->id,
                'role_name' => $roleName,
            ]);
        }
        
        return $result;
    }

    /**
     * 從客戶端移除角色
     */
    public function removeRole(ApiClient $client, string $roleName): bool
    {
        $result = $this->roleManager->removeRoleFromClient($client, $roleName);
        
        if ($result) {
            // 使用新的快取失效機制
            $this->permissionCache->invalidate($client->id);
            
            Log::info('角色已從客戶端移除', [
                'client_id' => $client->id,
                'role_name' => $roleName,
            ]);
        }
        
        return $result;
    }

    /**
     * 取得客戶端的所有角色
     */
    public function getClientRoles(ApiClient $client): array
    {
        return $this->roleManager->getClientRoles($client);
    }

    /**
     * 檢查客戶端是否有指定角色
     */
    public function hasRole(ApiClient $client, string $roleName): bool
    {
        return $this->roleManager->clientHasRole($client, $roleName);
    }

    /**
     * 清除客戶端的權限快取
     */
    public function clearClientPermissionCache(int $clientId): void
    {
        $this->permissionCache->invalidate($clientId);
        Log::debug('客戶端權限快取已清除', ['client_id' => $clientId]);
    }

    /**
     * 清除 Function 的權限快取
     */
    public function clearFunctionPermissionCache(int $functionId): void
    {
        $this->permissionCache->invalidate(null, null, $functionId);
        Log::debug('Function 權限快取已清除', ['function_id' => $functionId]);
    }

    /**
     * 清除角色的權限快取
     */
    public function clearRolePermissionCache(int $roleId): void
    {
        $this->permissionCache->invalidate(null, $roleId);
        Log::debug('角色權限快取已清除', ['role_id' => $roleId]);
    }

    /**
     * 清除所有權限快取
     */
    public function clearAllPermissionCache(): void
    {
        $this->permissionCache->flush();
        Log::info('所有權限快取已清除');
    }

    /**
     * 取得權限快取統計資訊
     */
    public function getPermissionCacheStats(): array
    {
        return $this->permissionCache->getStats();
    }

    /**
     * 設定快取過期時間
     */
    public function setCacheExpiration(int $seconds): void
    {
        $this->cacheExpiration = $seconds;
    }

    /**
     * 取得權限檢查器
     */
    public function getPermissionChecker(): PermissionChecker
    {
        return $this->permissionChecker;
    }

    /**
     * 取得角色管理器
     */
    public function getRoleManager(): RoleManager
    {
        return $this->roleManager;
    }

    /**
     * 取得權限快取
     */
    public function getPermissionCache(): PermissionCache
    {
        return $this->permissionCache;
    }
}

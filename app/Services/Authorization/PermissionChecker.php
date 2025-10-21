<?php

namespace App\Services\Authorization;

use App\Models\ApiClient;
use App\Models\ApiFunction;
use App\Models\FunctionPermission;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;

/**
 * Permission Checker
 * 
 * 負責檢查客戶端的權限邏輯
 */
class PermissionChecker
{
    /**
     * 檢查客戶端是否有權限執行指定的 API Function
     */
    public function check(ApiClient $client, ApiFunction $function): bool
    {
        // 1. 檢查是否有明確的 Function 權限設定（優先級最高）
        $explicitPermission = $this->checkExplicitFunctionPermission($client, $function);
        if (!is_null($explicitPermission)) {
            Log::debug('使用明確的 Function 權限', [
                'client_id' => $client->id,
                'function_id' => $function->id,
                'allowed' => $explicitPermission,
            ]);
            return $explicitPermission;
        }

        // 2. 檢查角色權限
        $rolePermission = $this->checkRolePermission($client, $function);
        if ($rolePermission) {
            Log::debug('透過角色權限授權', [
                'client_id' => $client->id,
                'function_id' => $function->id,
            ]);
            return true;
        }

        // 3. 預設拒絕
        Log::debug('權限檢查失敗：無匹配的權限', [
            'client_id' => $client->id,
            'function_id' => $function->id,
        ]);
        return false;
    }

    /**
     * 檢查明確的 Function 權限設定
     */
    protected function checkExplicitFunctionPermission(ApiClient $client, ApiFunction $function): ?bool
    {
        $permission = FunctionPermission::where('client_id', $client->id)
            ->where('function_id', $function->id)
            ->first();

        return $permission ? $permission->allowed : null;
    }

    /**
     * 檢查角色權限
     */
    protected function checkRolePermission(ApiClient $client, ApiFunction $function): bool
    {
        // 載入客戶端的所有角色
        $roles = $client->roles;

        if ($roles->isEmpty()) {
            return false;
        }

        // 檢查任一角色是否有權限
        foreach ($roles as $role) {
            // 檢查角色是否有此 Function 的執行權限
            if ($role->hasPermission(Permission::RESOURCE_FUNCTION, $function->id, Permission::ACTION_EXECUTE)) {
                return true;
            }

            // 檢查角色是否有所有 Function 的執行權限（萬用字元）
            if ($role->hasPermission(Permission::RESOURCE_FUNCTION, null, Permission::ACTION_EXECUTE)) {
                return true;
            }

            // 檢查角色是否有所有權限（超級管理員）
            if ($role->hasPermission(Permission::RESOURCE_FUNCTION, null, Permission::ACTION_ALL)) {
                return true;
            }
        }

        return false;
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
        // 驗證資源類型和動作
        if (!Permission::isValidResourceType($resourceType)) {
            Log::warning('無效的資源類型', ['resource_type' => $resourceType]);
            return false;
        }

        if (!Permission::isValidAction($action)) {
            Log::warning('無效的動作', ['action' => $action]);
            return false;
        }

        // 載入客戶端的所有角色
        $roles = $client->roles;

        if ($roles->isEmpty()) {
            return false;
        }

        // 檢查任一角色是否有權限
        foreach ($roles as $role) {
            if ($role->hasPermission($resourceType, $resourceId, $action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 取得客戶端可存取的所有 Function ID
     */
    public function getAccessibleFunctionIds(ApiClient $client): array
    {
        $functionIds = [];

        // 1. 取得明確允許的 Function
        $explicitPermissions = FunctionPermission::where('client_id', $client->id)
            ->where('allowed', true)
            ->pluck('function_id')
            ->toArray();

        $functionIds = array_merge($functionIds, $explicitPermissions);

        // 2. 取得透過角色可存取的 Function
        $roles = $client->roles;
        foreach ($roles as $role) {
            // 如果角色有所有 Function 的權限
            if ($role->hasPermission(Permission::RESOURCE_FUNCTION, null, Permission::ACTION_EXECUTE) ||
                $role->hasPermission(Permission::RESOURCE_FUNCTION, null, Permission::ACTION_ALL)) {
                // 返回所有啟用的 Function
                return ApiFunction::where('is_active', true)->pluck('id')->toArray();
            }

            // 取得角色的特定 Function 權限
            $rolePermissions = $role->permissions()
                ->where('resource_type', Permission::RESOURCE_FUNCTION)
                ->whereNotNull('resource_id')
                ->where(function ($query) {
                    $query->where('action', Permission::ACTION_EXECUTE)
                          ->orWhere('action', Permission::ACTION_ALL);
                })
                ->pluck('resource_id')
                ->toArray();

            $functionIds = array_merge($functionIds, $rolePermissions);
        }

        // 3. 移除明確拒絕的 Function
        $deniedFunctions = FunctionPermission::where('client_id', $client->id)
            ->where('allowed', false)
            ->pluck('function_id')
            ->toArray();

        $functionIds = array_diff($functionIds, $deniedFunctions);

        return array_unique($functionIds);
    }

    /**
     * 檢查客戶端是否為管理員
     */
    public function isAdmin(ApiClient $client): bool
    {
        return $client->roles()->where('name', 'admin')->exists();
    }

    /**
     * 檢查客戶端是否有任何權限
     */
    public function hasAnyPermission(ApiClient $client): bool
    {
        // 檢查是否有角色
        if ($client->roles()->exists()) {
            return true;
        }

        // 檢查是否有明確的 Function 權限
        if ($client->functionPermissions()->where('allowed', true)->exists()) {
            return true;
        }

        return false;
    }
}

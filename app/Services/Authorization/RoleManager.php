<?php

namespace App\Services\Authorization;

use App\Models\ApiClient;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Role Manager
 * 
 * 負責管理角色和角色權限
 */
class RoleManager
{
    /**
     * 為客戶端指派角色
     */
    public function assignRoleToClient(ApiClient $client, string $roleName): bool
    {
        $role = Role::findByName($roleName);

        if (!$role) {
            Log::warning('角色不存在', ['role_name' => $roleName]);
            return false;
        }

        // 檢查是否已經有此角色
        if ($client->roles()->where('role_id', $role->id)->exists()) {
            Log::debug('客戶端已擁有此角色', [
                'client_id' => $client->id,
                'role_name' => $roleName,
            ]);
            return true;
        }

        // 指派角色
        $client->roles()->attach($role->id);

        Log::info('角色已指派給客戶端', [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'role_name' => $roleName,
        ]);

        return true;
    }

    /**
     * 從客戶端移除角色
     */
    public function removeRoleFromClient(ApiClient $client, string $roleName): bool
    {
        $role = Role::findByName($roleName);

        if (!$role) {
            Log::warning('角色不存在', ['role_name' => $roleName]);
            return false;
        }

        // 移除角色
        $client->roles()->detach($role->id);

        Log::info('角色已從客戶端移除', [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'role_name' => $roleName,
        ]);

        return true;
    }

    /**
     * 同步客戶端的角色
     */
    public function syncClientRoles(ApiClient $client, array $roleNames): bool
    {
        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();

        if (count($roleIds) !== count($roleNames)) {
            Log::warning('部分角色不存在', [
                'requested_roles' => $roleNames,
                'found_roles' => count($roleIds),
            ]);
        }

        $client->roles()->sync($roleIds);

        Log::info('客戶端角色已同步', [
            'client_id' => $client->id,
            'roles' => $roleNames,
        ]);

        return true;
    }

    /**
     * 取得客戶端的所有角色
     */
    public function getClientRoles(ApiClient $client): array
    {
        return $client->roles()->pluck('name')->toArray();
    }

    /**
     * 檢查客戶端是否有指定角色
     */
    public function clientHasRole(ApiClient $client, string $roleName): bool
    {
        return $client->roles()->where('name', $roleName)->exists();
    }

    /**
     * 建立新角色
     */
    public function createRole(string $name, ?string $description = null): Role
    {
        $role = Role::create([
            'name' => $name,
            'description' => $description ?? $name,
        ]);

        Log::info('角色已建立', [
            'role_id' => $role->id,
            'role_name' => $name,
        ]);

        return $role;
    }

    /**
     * 更新角色
     */
    public function updateRole(Role $role, array $data): bool
    {
        $result = $role->update($data);

        if ($result) {
            Log::info('角色已更新', [
                'role_id' => $role->id,
                'role_name' => $role->name,
            ]);
        }

        return $result;
    }

    /**
     * 刪除角色
     */
    public function deleteRole(Role $role): bool
    {
        // 檢查是否有客戶端使用此角色
        $clientCount = $role->clients()->count();
        if ($clientCount > 0) {
            Log::warning('無法刪除角色：仍有客戶端使用', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'client_count' => $clientCount,
            ]);
            return false;
        }

        $roleName = $role->name;
        $result = $role->delete();

        if ($result) {
            Log::info('角色已刪除', ['role_name' => $roleName]);
        }

        return $result;
    }

    /**
     * 為角色授予權限
     */
    public function grantPermissionToRole(Role $role, Permission $permission): bool
    {
        $role->grantPermission($permission);

        Log::info('權限已授予角色', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permission_id' => $permission->id,
            'permission' => $permission->getDescription(),
        ]);

        return true;
    }

    /**
     * 從角色移除權限
     */
    public function revokePermissionFromRole(Role $role, Permission $permission): bool
    {
        $role->revokePermission($permission);

        Log::info('權限已從角色移除', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permission_id' => $permission->id,
            'permission' => $permission->getDescription(),
        ]);

        return true;
    }

    /**
     * 同步角色的權限
     */
    public function syncRolePermissions(Role $role, array $permissionIds): bool
    {
        $role->syncPermissions($permissionIds);

        Log::info('角色權限已同步', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permission_count' => count($permissionIds),
        ]);

        return true;
    }

    /**
     * 取得角色的所有權限
     */
    public function getRolePermissions(Role $role): Collection
    {
        return $role->permissions;
    }

    /**
     * 取得所有角色
     */
    public function getAllRoles(): Collection
    {
        return Role::all();
    }

    /**
     * 根據名稱查找角色
     */
    public function findRoleByName(string $name): ?Role
    {
        return Role::findByName($name);
    }

    /**
     * 取得或建立角色
     */
    public function findOrCreateRole(string $name, ?string $description = null): Role
    {
        return Role::findOrCreateByName($name, $description);
    }

    /**
     * 批次建立預設角色
     */
    public function createDefaultRoles(): array
    {
        $defaultRoles = [
            [
                'name' => Role::ROLE_ADMIN,
                'description' => '系統管理員，擁有所有權限',
            ],
            [
                'name' => Role::ROLE_USER,
                'description' => '一般使用者，擁有基本權限',
            ],
            [
                'name' => Role::ROLE_GUEST,
                'description' => '訪客，擁有最低權限',
            ],
        ];

        $roles = [];
        foreach ($defaultRoles as $roleData) {
            $roles[] = $this->findOrCreateRole($roleData['name'], $roleData['description']);
        }

        Log::info('預設角色已建立', ['count' => count($roles)]);

        return $roles;
    }

    /**
     * 為管理員角色設定完整權限
     */
    public function setupAdminPermissions(): bool
    {
        $adminRole = $this->findOrCreateRole(Role::ROLE_ADMIN, '系統管理員');

        // 建立所有資源的完整權限
        $permission = Permission::findOrCreate(
            Permission::RESOURCE_FUNCTION,
            null,
            Permission::ACTION_ALL
        );

        $this->grantPermissionToRole($adminRole, $permission);

        Log::info('管理員權限已設定');

        return true;
    }

    /**
     * 取得角色的客戶端數量
     */
    public function getRoleClientCount(Role $role): int
    {
        return $role->clients()->count();
    }

    /**
     * 取得使用特定角色的所有客戶端
     */
    public function getClientsWithRole(string $roleName): Collection
    {
        $role = Role::findByName($roleName);

        if (!$role) {
            return collect([]);
        }

        return $role->clients;
    }

    /**
     * 批次為多個客戶端指派角色
     */
    public function assignRoleToMultipleClients(array $clientIds, string $roleName): int
    {
        $role = Role::findByName($roleName);

        if (!$role) {
            Log::warning('角色不存在', ['role_name' => $roleName]);
            return 0;
        }

        $count = 0;
        foreach ($clientIds as $clientId) {
            $client = ApiClient::find($clientId);
            if ($client && $this->assignRoleToClient($client, $roleName)) {
                $count++;
            }
        }

        Log::info('批次角色指派完成', [
            'role_name' => $roleName,
            'success_count' => $count,
            'total_count' => count($clientIds),
        ]);

        return $count;
    }
}

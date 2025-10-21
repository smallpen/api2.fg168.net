<?php

namespace App\Repositories;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

/**
 * Permission Repository
 * 
 * 提供 Permission 的資料存取操作
 */
class PermissionRepository extends BaseRepository
{
    /**
     * PermissionRepository constructor
     */
    public function __construct(Permission $model)
    {
        parent::__construct($model);
    }

    /**
     * 根據資源類型查找權限
     */
    public function findByResourceType(string $resourceType): Collection
    {
        return $this->model
            ->where('resource_type', $resourceType)
            ->orderBy('resource_id')
            ->orderBy('action')
            ->get();
    }

    /**
     * 根據資源類型和 ID 查找權限
     */
    public function findByResource(string $resourceType, ?int $resourceId = null): Collection
    {
        $query = $this->model->where('resource_type', $resourceType);

        if (!is_null($resourceId)) {
            $query->where(function ($q) use ($resourceId) {
                $q->where('resource_id', $resourceId)
                  ->orWhereNull('resource_id');
            });
        }

        return $query->get();
    }

    /**
     * 查找或建立權限
     */
    public function findOrCreate(string $resourceType, ?int $resourceId, string $action): Permission
    {
        return Permission::findOrCreate($resourceType, $resourceId, $action);
    }

    /**
     * 建立 Function 執行權限
     */
    public function createFunctionExecutePermission(?int $functionId = null): Permission
    {
        return Permission::createFunctionExecutePermission($functionId);
    }

    /**
     * 建立完整的 CRUD 權限集合
     */
    public function createCrudPermissions(string $resourceType, ?int $resourceId = null): array
    {
        return Permission::createCrudPermissions($resourceType, $resourceId);
    }

    /**
     * 取得角色的所有權限
     */
    public function getByRole(int $roleId): Collection
    {
        $role = Role::findOrFail($roleId);
        return $role->permissions;
    }

    /**
     * 為角色指派權限
     */
    public function assignToRole(int $permissionId, int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $permission = $this->findOrFail($permissionId);
        
        $role->grantPermission($permission);
    }

    /**
     * 從角色移除權限
     */
    public function removeFromRole(int $permissionId, int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $permission = $this->findOrFail($permissionId);
        
        $role->revokePermission($permission);
    }

    /**
     * 同步角色的權限
     */
    public function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $role = Role::findOrFail($roleId);
        $role->syncPermissions($permissionIds);
    }

    /**
     * 檢查權限是否存在
     */
    public function permissionExists(string $resourceType, ?int $resourceId, string $action): bool
    {
        return $this->model
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('action', $action)
            ->exists();
    }

    /**
     * 取得所有 Function 權限
     */
    public function getAllFunctionPermissions(): Collection
    {
        return $this->findByResourceType(Permission::RESOURCE_FUNCTION);
    }

    /**
     * 取得特定 Function 的所有權限
     */
    public function getFunctionPermissions(int $functionId): Collection
    {
        return $this->model
            ->where('resource_type', Permission::RESOURCE_FUNCTION)
            ->where(function ($q) use ($functionId) {
                $q->where('resource_id', $functionId)
                  ->orWhereNull('resource_id');
            })
            ->get();
    }

    /**
     * 批次建立權限
     */
    public function createMany(array $permissions): Collection
    {
        $created = collect();

        foreach ($permissions as $permissionData) {
            $permission = $this->findOrCreate(
                $permissionData['resource_type'],
                $permissionData['resource_id'] ?? null,
                $permissionData['action']
            );
            
            $created->push($permission);
        }

        return $created;
    }

    /**
     * 刪除資源的所有權限
     */
    public function deleteByResource(string $resourceType, int $resourceId): int
    {
        return $this->model
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->delete();
    }

    /**
     * 取得權限矩陣（角色 x 權限）
     */
    public function getPermissionMatrix(): array
    {
        $roles = Role::with('permissions')->get();
        $allPermissions = $this->all();

        $matrix = [];

        foreach ($roles as $role) {
            $matrix[$role->id] = [
                'role' => $role,
                'permissions' => [],
            ];

            foreach ($allPermissions as $permission) {
                $matrix[$role->id]['permissions'][$permission->id] = 
                    $role->permissions->contains($permission->id);
            }
        }

        return $matrix;
    }

    /**
     * 取得 Function 權限矩陣（客戶端 x Function）
     */
    public function getFunctionPermissionMatrix(): array
    {
        // 這個方法會在後續與 FunctionPermission 整合時實作
        // 目前返回空陣列
        return [];
    }
}

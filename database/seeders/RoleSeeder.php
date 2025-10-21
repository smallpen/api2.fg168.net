<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Role Seeder
 * 
 * 建立系統預設角色和對應的權限
 */
class RoleSeeder extends Seeder
{
    /**
     * 執行 Seeder
     */
    public function run(): void
    {
        // 建立管理員角色
        $adminRole = Role::findOrCreateByName(
            Role::ROLE_ADMIN,
            '系統管理員，擁有所有權限'
        );

        // 建立一般使用者角色
        $userRole = Role::findOrCreateByName(
            Role::ROLE_USER,
            '一般使用者，擁有基本 API 存取權限'
        );

        // 建立訪客角色
        $guestRole = Role::findOrCreateByName(
            Role::ROLE_GUEST,
            '訪客，僅能存取公開 API'
        );

        // 為管理員角色建立完整權限
        $this->createAdminPermissions($adminRole);

        // 為一般使用者建立基本權限
        $this->createUserPermissions($userRole);

        // 為訪客建立最小權限
        $this->createGuestPermissions($guestRole);

        $this->command->info('角色和權限建立完成！');
    }

    /**
     * 建立管理員權限
     */
    private function createAdminPermissions(Role $role): void
    {
        $permissions = [];

        // Function 相關的所有權限
        $permissions[] = Permission::findOrCreate(Permission::RESOURCE_FUNCTION, null, Permission::ACTION_ALL);

        // Client 相關的所有權限
        $permissions[] = Permission::findOrCreate(Permission::RESOURCE_CLIENT, null, Permission::ACTION_ALL);

        // Role 相關的所有權限
        $permissions[] = Permission::findOrCreate(Permission::RESOURCE_ROLE, null, Permission::ACTION_ALL);

        // Log 相關的所有權限
        $permissions[] = Permission::findOrCreate(Permission::RESOURCE_LOG, null, Permission::ACTION_ALL);

        // 同步權限到角色
        $role->syncPermissions(collect($permissions)->pluck('id')->toArray());

        $this->command->info("管理員角色權限已建立：{$role->permissions->count()} 個權限");
    }

    /**
     * 建立一般使用者權限
     */
    private function createUserPermissions(Role $role): void
    {
        $permissions = [];

        // 可以執行所有 Function（但不能管理）
        $permissions[] = Permission::findOrCreate(Permission::RESOURCE_FUNCTION, null, Permission::ACTION_EXECUTE);

        // 可以檢視自己的客戶端資訊
        $permissions[] = Permission::findOrCreate(Permission::RESOURCE_CLIENT, null, Permission::ACTION_VIEW);

        // 可以檢視日誌
        $permissions[] = Permission::findOrCreate(Permission::RESOURCE_LOG, null, Permission::ACTION_VIEW);

        // 同步權限到角色
        $role->syncPermissions(collect($permissions)->pluck('id')->toArray());

        $this->command->info("一般使用者角色權限已建立：{$role->permissions->count()} 個權限");
    }

    /**
     * 建立訪客權限
     */
    private function createGuestPermissions(Role $role): void
    {
        $permissions = [];

        // 訪客只能執行 Function（需要額外的 Function 層級權限控制）
        $permissions[] = Permission::findOrCreate(Permission::RESOURCE_FUNCTION, null, Permission::ACTION_EXECUTE);

        // 同步權限到角色
        $role->syncPermissions(collect($permissions)->pluck('id')->toArray());

        $this->command->info("訪客角色權限已建立：{$role->permissions->count()} 個權限");
    }
}

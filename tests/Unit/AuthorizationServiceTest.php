<?php

namespace Tests\Unit;

use App\Models\ApiClient;
use App\Models\ApiFunction;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\AuthorizationManager;
use App\Services\Authorization\PermissionChecker;
use App\Services\Authorization\RoleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorization Service Test
 * 
 * 測試授權服務的核心功能
 */
class AuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuthorizationManager $authorizationManager;
    protected PermissionChecker $permissionChecker;
    protected RoleManager $roleManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissionChecker = new PermissionChecker();
        $this->roleManager = new RoleManager();
        $this->authorizationManager = new AuthorizationManager(
            $this->permissionChecker,
            $this->roleManager
        );
    }

    /**
     * 測試角色管理器可以建立角色
     */
    public function test_role_manager_can_create_role(): void
    {
        $role = $this->roleManager->createRole('test_role', '測試角色');

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('test_role', $role->name);
        $this->assertEquals('測試角色', $role->description);
        $this->assertDatabaseHas('roles', [
            'name' => 'test_role',
        ]);
    }

    /**
     * 測試可以為客戶端指派角色
     */
    public function test_can_assign_role_to_client(): void
    {
        $client = ApiClient::factory()->create();
        $role = $this->roleManager->createRole('user', '使用者');

        $result = $this->roleManager->assignRoleToClient($client, 'user');

        $this->assertTrue($result);
        $this->assertTrue($client->hasRole('user'));
    }

    /**
     * 測試可以從客戶端移除角色
     */
    public function test_can_remove_role_from_client(): void
    {
        $client = ApiClient::factory()->create();
        $role = $this->roleManager->createRole('user', '使用者');
        $this->roleManager->assignRoleToClient($client, 'user');

        $result = $this->roleManager->removeRoleFromClient($client, 'user');

        $this->assertTrue($result);
        $this->assertFalse($client->hasRole('user'));
    }

    /**
     * 測試權限檢查器可以檢查角色權限
     */
    public function test_permission_checker_validates_role_permissions(): void
    {
        // 建立客戶端和角色
        $client = ApiClient::factory()->create(['is_active' => true]);
        $role = $this->roleManager->createRole('user', '使用者');
        $this->roleManager->assignRoleToClient($client, 'user');

        // 建立 Function
        $function = ApiFunction::factory()->create(['is_active' => true]);

        // 建立權限並授予角色
        $permission = Permission::createFunctionExecutePermission($function->id);
        $this->roleManager->grantPermissionToRole($role, $permission);

        // 檢查權限
        $result = $this->permissionChecker->check($client, $function);

        $this->assertTrue($result);
    }

    /**
     * 測試授權管理器可以授權客戶端
     */
    public function test_authorization_manager_authorizes_client(): void
    {
        // 建立客戶端和角色
        $client = ApiClient::factory()->create(['is_active' => true]);
        $role = $this->roleManager->createRole('user', '使用者');
        $this->roleManager->assignRoleToClient($client, 'user');

        // 建立 Function
        $function = ApiFunction::factory()->create(['is_active' => true]);

        // 建立權限並授予角色
        $permission = Permission::createFunctionExecutePermission($function->id);
        $this->roleManager->grantPermissionToRole($role, $permission);

        // 執行授權
        $result = $this->authorizationManager->authorize($client, $function);

        $this->assertTrue($result);
    }

    /**
     * 測試未啟用的客戶端無法通過授權
     */
    public function test_inactive_client_cannot_be_authorized(): void
    {
        $client = ApiClient::factory()->create(['is_active' => false]);
        $function = ApiFunction::factory()->create(['is_active' => true]);

        $result = $this->authorizationManager->authorize($client, $function);

        $this->assertFalse($result);
    }

    /**
     * 測試未啟用的 Function 無法通過授權
     */
    public function test_inactive_function_cannot_be_authorized(): void
    {
        $client = ApiClient::factory()->create(['is_active' => true]);
        $function = ApiFunction::factory()->create(['is_active' => false]);

        $result = $this->authorizationManager->authorize($client, $function);

        $this->assertFalse($result);
    }
}

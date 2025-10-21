<?php

namespace Tests\Integration;

use App\Models\ApiClient;
use App\Models\ApiFunction;
use App\Models\Role;
use App\Models\Permission;
use App\Models\FunctionPermission;
use App\Models\User;

/**
 * 權限管理整合測試
 * 
 * 測試 Admin UI 的權限配置操作
 */
class PermissionManagementIntegrationTest extends IntegrationTestCase
{
    protected User $adminUser;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'is_admin' => true
        ]);
    }

    /**
     * 測試列出所有角色
     * 
     * @test
     */
    public function test_list_all_roles()
    {
        Role::factory()->count(5)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/roles');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'created_at'
                ]
            ]
        ]);
    }

    /**
     * 測試建立新角色
     * 
     * @test
     */
    public function test_create_new_role()
    {
        $roleData = [
            'name' => 'developer',
            'description' => '開發者角色'
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('roles', [
            'name' => 'developer'
        ]);
    }

    /**
     * 測試更新角色
     * 
     * @test
     */
    public function test_update_role()
    {
        $role = Role::factory()->create(['name' => 'old_name']);

        $updateData = [
            'name' => 'new_name',
            'description' => '新的描述'
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/roles/{$role->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'new_name'
        ]);
    }

    /**
     * 測試刪除角色
     * 
     * @test
     */
    public function test_delete_role()
    {
        $role = Role::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('roles', [
            'id' => $role->id
        ]);
    }

    /**
     * 測試為角色指派權限
     * 
     * @test
     */
    public function test_assign_permissions_to_role()
    {
        $role = Role::factory()->create();
        $function = ApiFunction::factory()->create();
        
        $permission = Permission::factory()->create([
            'resource_type' => 'function',
            'resource_id' => $function->id,
            'action' => 'execute'
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/roles/{$role->id}/permissions", [
                'permission_ids' => [$permission->id]
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id
        ]);
    }

    /**
     * 測試移除角色的權限
     * 
     * @test
     */
    public function test_remove_permissions_from_role()
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        
        $role->permissions()->attach($permission);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/roles/{$role->id}/permissions/{$permission->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id
        ]);
    }

    /**
     * 測試為客戶端指派角色
     * 
     * @test
     */
    public function test_assign_roles_to_client()
    {
        $client = ApiClient::factory()->create();
        $role = Role::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/clients/{$client->id}/roles", [
                'role_ids' => [$role->id]
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('client_roles', [
            'client_id' => $client->id,
            'role_id' => $role->id
        ]);
    }

    /**
     * 測試移除客戶端的角色
     * 
     * @test
     */
    public function test_remove_roles_from_client()
    {
        $client = ApiClient::factory()->create();
        $role = Role::factory()->create();
        
        $client->roles()->attach($role);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/clients/{$client->id}/roles/{$role->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('client_roles', [
            'client_id' => $client->id,
            'role_id' => $role->id
        ]);
    }

    /**
     * 測試設定 Function 的客戶端權限
     * 
     * @test
     */
    public function test_set_function_client_permission()
    {
        $function = ApiFunction::factory()->create();
        $client = ApiClient::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/functions/{$function->id}/permissions", [
                'client_id' => $client->id,
                'allowed' => true
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('function_permissions', [
            'function_id' => $function->id,
            'client_id' => $client->id,
            'allowed' => true
        ]);
    }

    /**
     * 測試更新 Function 權限
     * 
     * @test
     */
    public function test_update_function_permission()
    {
        $function = ApiFunction::factory()->create();
        $client = ApiClient::factory()->create();
        
        $permission = FunctionPermission::create([
            'function_id' => $function->id,
            'client_id' => $client->id,
            'allowed' => true
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/functions/{$function->id}/permissions/{$permission->id}", [
                'allowed' => false
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('function_permissions', [
            'id' => $permission->id,
            'allowed' => false
        ]);
    }

    /**
     * 測試刪除 Function 權限
     * 
     * @test
     */
    public function test_delete_function_permission()
    {
        $function = ApiFunction::factory()->create();
        $client = ApiClient::factory()->create();
        
        $permission = FunctionPermission::create([
            'function_id' => $function->id,
            'client_id' => $client->id,
            'allowed' => true
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/functions/{$function->id}/permissions/{$permission->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('function_permissions', [
            'id' => $permission->id
        ]);
    }

    /**
     * 測試取得 Function 的權限矩陣
     * 
     * @test
     */
    public function test_get_function_permission_matrix()
    {
        $function = ApiFunction::factory()->create();
        $client1 = ApiClient::factory()->create();
        $client2 = ApiClient::factory()->create();
        
        FunctionPermission::create([
            'function_id' => $function->id,
            'client_id' => $client1->id,
            'allowed' => true
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/functions/{$function->id}/permissions");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'client_id',
                    'client_name',
                    'allowed'
                ]
            ]
        ]);
    }

    /**
     * 測試批次設定權限
     * 
     * @test
     */
    public function test_batch_set_permissions()
    {
        $function = ApiFunction::factory()->create();
        $client1 = ApiClient::factory()->create();
        $client2 = ApiClient::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/functions/{$function->id}/permissions/batch", [
                'permissions' => [
                    ['client_id' => $client1->id, 'allowed' => true],
                    ['client_id' => $client2->id, 'allowed' => false]
                ]
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('function_permissions', [
            'function_id' => $function->id,
            'client_id' => $client1->id,
            'allowed' => true
        ]);
        
        $this->assertDatabaseHas('function_permissions', [
            'function_id' => $function->id,
            'client_id' => $client2->id,
            'allowed' => false
        ]);
    }

    /**
     * 測試取得客戶端的所有權限
     * 
     * @test
     */
    public function test_get_client_all_permissions()
    {
        $client = ApiClient::factory()->create();
        $role = Role::factory()->create();
        $function = ApiFunction::factory()->create();
        
        $permission = Permission::factory()->create([
            'resource_type' => 'function',
            'resource_id' => $function->id,
            'action' => 'execute'
        ]);
        
        $role->permissions()->attach($permission);
        $client->roles()->attach($role);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/clients/{$client->id}/permissions");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'roles',
                'permissions'
            ]
        ]);
    }

    /**
     * 測試權限變更記錄到審計日誌
     * 
     * @test
     */
    public function test_permission_changes_are_audited()
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/roles/{$role->id}/permissions", [
                'permission_ids' => [$permission->id]
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'assign_permission',
            'resource_type' => 'role',
            'resource_id' => $role->id
        ]);
    }

    /**
     * 測試權限快取在變更後被清除
     * 
     * @test
     */
    public function test_permission_cache_is_cleared_after_changes()
    {
        $client = ApiClient::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        // 指派權限
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/roles/{$role->id}/permissions", [
                'permission_ids' => [$permission->id]
            ]);

        // 驗證快取已清除（透過檢查 Redis）
        $cacheKey = "permissions:client:{$client->id}";
        $this->assertFalse(\Illuminate\Support\Facades\Redis::exists($cacheKey));
    }
}

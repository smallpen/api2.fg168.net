<?php

namespace Tests\Integration;

use App\Models\ApiClient;
use App\Models\ApiFunction;
use App\Models\Role;
use App\Models\Permission;
use App\Models\FunctionPermission;

/**
 * 授權機制整合測試
 * 
 * 測試權限控制和授權流程
 */
class AuthorizationIntegrationTest extends IntegrationTestCase
{
    /**
     * 測試具有權限的客戶端可以存取 Function
     * 
     * @test
     */
    public function test_client_with_permission_can_access_function()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 授予權限
        FunctionPermission::create([
            'function_id' => $function->id,
            'client_id' => $client->id,
            'allowed' => true
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試沒有權限的客戶端無法存取 Function
     * 
     * @test
     */
    public function test_client_without_permission_cannot_access_function()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 明確拒絕權限
        FunctionPermission::create([
            'function_id' => $function->id,
            'client_id' => $client->id,
            'allowed' => false
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertErrorResponse($response, 'PERMISSION_DENIED', 403);
    }

    /**
     * 測試基於角色的權限控制
     * 
     * @test
     */
    public function test_role_based_permission_control()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 建立角色和權限
        $role = Role::factory()->create(['name' => 'admin']);
        $permission = Permission::factory()->create([
            'resource_type' => 'function',
            'resource_id' => $function->id,
            'action' => 'execute'
        ]);

        // 指派權限給角色
        $role->permissions()->attach($permission);
        
        // 指派角色給客戶端
        $client->roles()->attach($role);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試沒有角色的客戶端無法存取受保護的 Function
     * 
     * @test
     */
    public function test_client_without_role_cannot_access_protected_function()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 建立角色和權限（但不指派給客戶端）
        $role = Role::factory()->create(['name' => 'admin']);
        $permission = Permission::factory()->create([
            'resource_type' => 'function',
            'resource_id' => $function->id,
            'action' => 'execute'
        ]);
        $role->permissions()->attach($permission);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertErrorResponse($response, 'PERMISSION_DENIED', 403);
    }

    /**
     * 測試多個角色的權限累加
     * 
     * @test
     */
    public function test_multiple_roles_permissions_are_cumulative()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function1 = $this->createTestFunction([
            'identifier' => 'test.function1',
            'is_active' => true
        ]);
        
        $function2 = $this->createTestFunction([
            'identifier' => 'test.function2',
            'is_active' => true
        ]);

        // 建立兩個角色，各自有不同的權限
        $role1 = Role::factory()->create(['name' => 'role1']);
        $permission1 = Permission::factory()->create([
            'resource_type' => 'function',
            'resource_id' => $function1->id,
            'action' => 'execute'
        ]);
        $role1->permissions()->attach($permission1);

        $role2 = Role::factory()->create(['name' => 'role2']);
        $permission2 = Permission::factory()->create([
            'resource_type' => 'function',
            'resource_id' => $function2->id,
            'action' => 'execute'
        ]);
        $role2->permissions()->attach($permission2);

        // 指派兩個角色給客戶端
        $client->roles()->attach([$role1->id, $role2->id]);

        // 測試可以存取 function1
        $response1 = $this->postJson('/api/v1/execute', [
            'function' => 'test.function1',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response1->assertStatus(200);

        // 測試可以存取 function2
        $response2 = $this->postJson('/api/v1/execute', [
            'function' => 'test.function2',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response2->assertStatus(200);
    }

    /**
     * 測試授權失敗記錄到安全日誌
     * 
     * @test
     */
    public function test_authorization_failure_is_logged()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 明確拒絕權限
        FunctionPermission::create([
            'function_id' => $function->id,
            'client_id' => $client->id,
            'allowed' => false
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertErrorResponse($response, 'PERMISSION_DENIED', 403);

        // 驗證安全日誌已記錄
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'authorization_failed',
            'client_id' => $client->id
        ]);
    }

    /**
     * 測試沒有設定權限的 Function 預設允許存取
     * 
     * @test
     */
    public function test_function_without_permissions_allows_access_by_default()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 不設定任何權限

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試權限快取機制
     * 
     * @test
     */
    public function test_permission_caching()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 授予權限
        FunctionPermission::create([
            'function_id' => $function->id,
            'client_id' => $client->id,
            'allowed' => true
        ]);

        // 第一次請求（應該查詢資料庫並快取）
        $response1 = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response1->assertStatus(200);

        // 第二次請求（應該使用快取）
        $response2 = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response2->assertStatus(200);
    }
}

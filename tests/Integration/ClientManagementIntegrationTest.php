<?php

namespace Tests\Integration;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\User;

/**
 * 客戶端管理整合測試
 * 
 * 測試 Admin UI 的客戶端管理操作
 */
class ClientManagementIntegrationTest extends IntegrationTestCase
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
     * 測試列出所有客戶端
     * 
     * @test
     */
    public function test_list_all_clients()
    {
        ApiClient::factory()->count(5)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/clients');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'client_type',
                    'is_active',
                    'rate_limit',
                    'created_at'
                ]
            ]
        ]);
    }

    /**
     * 測試建立新客戶端
     * 
     * @test
     */
    public function test_create_new_client()
    {
        $clientData = [
            'name' => '測試客戶端',
            'client_type' => 'api_key',
            'rate_limit' => 100,
            'is_active' => true
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/clients', $clientData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'api_key',
                'client_type'
            ]
        ]);

        // 驗證 API Key 已生成
        $this->assertNotEmpty($response->json('data.api_key'));
        
        $this->assertDatabaseHas('api_clients', [
            'name' => '測試客戶端'
        ]);
    }

    /**
     * 測試建立客戶端時自動生成 API Key
     * 
     * @test
     */
    public function test_api_key_is_generated_automatically()
    {
        $clientData = [
            'name' => '測試客戶端',
            'client_type' => 'api_key'
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/clients', $clientData);

        $response->assertStatus(201);
        
        $apiKey = $response->json('data.api_key');
        $this->assertNotEmpty($apiKey);
        $this->assertGreaterThan(32, strlen($apiKey));
    }

    /**
     * 測試取得客戶端詳情
     * 
     * @test
     */
    public function test_get_client_details()
    {
        $client = ApiClient::factory()->create();
        
        // 建立 Tokens
        ApiToken::factory()->count(2)->create([
            'client_id' => $client->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/clients/{$client->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'api_key',
                'tokens' => [
                    '*' => [
                        'id',
                        'token',
                        'expires_at'
                    ]
                ]
            ]
        ]);
    }

    /**
     * 測試更新客戶端
     * 
     * @test
     */
    public function test_update_client()
    {
        $client = ApiClient::factory()->create([
            'name' => '原始名稱',
            'rate_limit' => 60
        ]);

        $updateData = [
            'name' => '更新後的名稱',
            'rate_limit' => 120
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/clients/{$client->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('api_clients', [
            'id' => $client->id,
            'name' => '更新後的名稱',
            'rate_limit' => 120
        ]);
    }

    /**
     * 測試啟用/停用客戶端
     * 
     * @test
     */
    public function test_toggle_client_status()
    {
        $client = ApiClient::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/admin/clients/{$client->id}/toggle");

        $response->assertStatus(200);
        $this->assertDatabaseHas('api_clients', [
            'id' => $client->id,
            'is_active' => false
        ]);
    }

    /**
     * 測試重新生成 API Key
     * 
     * @test
     */
    public function test_regenerate_api_key()
    {
        $client = ApiClient::factory()->create();
        $oldApiKey = $client->api_key;

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/clients/{$client->id}/regenerate-key");

        $response->assertStatus(200);
        
        $newApiKey = $response->json('data.api_key');
        $this->assertNotEquals($oldApiKey, $newApiKey);
        
        $this->assertDatabaseHas('api_clients', [
            'id' => $client->id,
            'api_key' => $newApiKey
        ]);
    }

    /**
     * 測試生成新的 Token
     * 
     * @test
     */
    public function test_generate_new_token()
    {
        $client = ApiClient::factory()->create();

        $tokenData = [
            'type' => 'bearer',
            'expires_in' => 3600
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/clients/{$client->id}/tokens", $tokenData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'token',
                'expires_at'
            ]
        ]);

        $this->assertDatabaseHas('api_tokens', [
            'client_id' => $client->id,
            'type' => 'bearer'
        ]);
    }

    /**
     * 測試撤銷 Token
     * 
     * @test
     */
    public function test_revoke_token()
    {
        $client = ApiClient::factory()->create();
        $token = ApiToken::factory()->create([
            'client_id' => $client->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/clients/{$client->id}/tokens/{$token->id}");

        $response->assertStatus(200);

        $token->refresh();
        $this->assertNotNull($token->revoked_at);
    }

    /**
     * 測試列出客戶端的所有 Tokens
     * 
     * @test
     */
    public function test_list_client_tokens()
    {
        $client = ApiClient::factory()->create();
        ApiToken::factory()->count(3)->create([
            'client_id' => $client->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/clients/{$client->id}/tokens");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * 測試刪除客戶端
     * 
     * @test
     */
    public function test_delete_client()
    {
        $client = ApiClient::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/clients/{$client->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('api_clients', [
            'id' => $client->id
        ]);
    }

    /**
     * 測試刪除客戶端時同時刪除相關 Tokens
     * 
     * @test
     */
    public function test_delete_client_cascades_tokens()
    {
        $client = ApiClient::factory()->create();
        $token = ApiToken::factory()->create([
            'client_id' => $client->id
        ]);

        $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/clients/{$client->id}");

        $this->assertDatabaseMissing('api_tokens', [
            'id' => $token->id
        ]);
    }

    /**
     * 測試篩選客戶端類型
     * 
     * @test
     */
    public function test_filter_clients_by_type()
    {
        ApiClient::factory()->count(2)->create(['client_type' => 'api_key']);
        ApiClient::factory()->count(3)->create(['client_type' => 'oauth']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/clients?client_type=api_key');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /**
     * 測試搜尋客戶端
     * 
     * @test
     */
    public function test_search_clients()
    {
        ApiClient::factory()->create(['name' => '測試客戶端 A']);
        ApiClient::factory()->create(['name' => '測試客戶端 B']);
        ApiClient::factory()->create(['name' => '生產客戶端']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/clients?search=測試');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /**
     * 測試客戶端變更記錄到審計日誌
     * 
     * @test
     */
    public function test_client_changes_are_audited()
    {
        $client = ApiClient::factory()->create();

        $this->actingAs($this->adminUser)
            ->putJson("/api/admin/clients/{$client->id}", [
                'name' => '新名稱'
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'update',
            'resource_type' => 'api_client',
            'resource_id' => $client->id
        ]);
    }

    /**
     * 測試查看客戶端統計資訊
     * 
     * @test
     */
    public function test_get_client_statistics()
    {
        $client = ApiClient::factory()->create();
        
        // 建立一些請求日誌
        \App\Models\ApiRequestLog::factory()->count(10)->create([
            'client_id' => $client->id,
            'http_status' => 200
        ]);
        
        \App\Models\ApiRequestLog::factory()->count(2)->create([
            'client_id' => $client->id,
            'http_status' => 400
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/clients/{$client->id}/statistics");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_requests',
                'successful_requests',
                'failed_requests'
            ]
        ]);
    }
}

<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;

/**
 * 整合測試基礎類別
 * 
 * 提供整合測試所需的基礎設定和輔助方法
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 清除 Redis 快取
        $this->clearRedisCache();
        
        // 執行資料庫遷移
        $this->artisan('migrate:fresh');
        
        // 執行測試資料填充
        $this->seed();
    }

    /**
     * 清理測試環境
     */
    protected function tearDown(): void
    {
        // 清除 Redis 快取
        $this->clearRedisCache();
        
        parent::tearDown();
    }

    /**
     * 清除 Redis 快取
     */
    protected function clearRedisCache(): void
    {
        try {
            Redis::flushdb();
        } catch (\Exception $e) {
            // 如果 Redis 連線失敗，忽略錯誤
        }
    }

    /**
     * 建立測試用的 API 客戶端
     * 
     * @param array $attributes 客戶端屬性
     * @return \App\Models\ApiClient
     */
    protected function createTestClient(array $attributes = []): \App\Models\ApiClient
    {
        return \App\Models\ApiClient::factory()->create($attributes);
    }

    /**
     * 建立測試用的 API Function
     * 
     * @param array $attributes Function 屬性
     * @return \App\Models\ApiFunction
     */
    protected function createTestFunction(array $attributes = []): \App\Models\ApiFunction
    {
        return \App\Models\ApiFunction::factory()->create($attributes);
    }

    /**
     * 建立測試用的 API Token
     * 
     * @param \App\Models\ApiClient $client
     * @param array $attributes Token 屬性
     * @return \App\Models\ApiToken
     */
    protected function createTestToken(\App\Models\ApiClient $client, array $attributes = []): \App\Models\ApiToken
    {
        return \App\Models\ApiToken::factory()->for($client)->create($attributes);
    }

    /**
     * 產生有效的 Bearer Token
     * 
     * @param \App\Models\ApiClient $client
     * @return string
     */
    protected function generateBearerToken(\App\Models\ApiClient $client): string
    {
        $token = $this->createTestToken($client);
        return $token->token;
    }

    /**
     * 產生有效的 API Key
     * 
     * @param \App\Models\ApiClient $client
     * @return string
     */
    protected function generateApiKey(\App\Models\ApiClient $client): string
    {
        return $client->api_key;
    }

    /**
     * 建立具有權限的測試客戶端
     * 
     * @param array $permissions 權限陣列
     * @return \App\Models\ApiClient
     */
    protected function createClientWithPermissions(array $permissions = []): \App\Models\ApiClient
    {
        $client = $this->createTestClient();
        
        // 建立角色
        $role = \App\Models\Role::factory()->create();
        
        // 指派角色給客戶端
        $client->roles()->attach($role);
        
        // 建立權限
        foreach ($permissions as $permission) {
            $perm = \App\Models\Permission::factory()->create($permission);
            $role->permissions()->attach($perm);
        }
        
        return $client;
    }

    /**
     * 斷言 API 回應格式正確
     * 
     * @param \Illuminate\Testing\TestResponse $response
     * @param bool $expectSuccess 是否預期成功
     */
    protected function assertApiResponse($response, bool $expectSuccess = true): void
    {
        $response->assertJsonStructure([
            'success',
            'data',
            'meta'
        ]);
        
        if ($expectSuccess) {
            $response->assertJson(['success' => true]);
        } else {
            $response->assertJson(['success' => false]);
            $response->assertJsonStructure([
                'error' => [
                    'code',
                    'message'
                ]
            ]);
        }
    }

    /**
     * 斷言錯誤回應格式正確
     * 
     * @param \Illuminate\Testing\TestResponse $response
     * @param string $errorCode 預期的錯誤碼
     * @param int $httpStatus 預期的 HTTP 狀態碼
     */
    protected function assertErrorResponse($response, string $errorCode, int $httpStatus): void
    {
        $response->assertStatus($httpStatus);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => $errorCode
            ]
        ]);
    }
}

<?php

namespace Tests\Integration;

use App\Models\ApiClient;
use App\Models\ApiFunction;
use App\Models\FunctionParameter;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Redis;

/**
 * API Gateway 整合測試
 * 
 * 測試完整的 API 請求流程，包含驗證、授權、參數驗證、執行和回應
 */
class ApiGatewayIntegrationTest extends IntegrationTestCase
{
    /**
     * 測試完整的 API 請求流程
     * 
     * @test
     */
    public function test_complete_api_request_flow_with_valid_credentials()
    {
        // 建立測試客戶端
        $client = $this->createTestClient([
            'is_active' => true,
            'rate_limit' => 60
        ]);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true,
            'stored_procedure' => 'sp_test_function'
        ]);

        // 建立參數定義
        FunctionParameter::factory()->create([
            'function_id' => $function->id,
            'name' => 'user_id',
            'data_type' => 'integer',
            'is_required' => true,
            'sp_parameter_name' => 'p_user_id'
        ]);

        // 產生 Token
        $token = $this->generateBearerToken($client);

        // 發送 API 請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => [
                'user_id' => 123
            ]
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應
        $response->assertStatus(200);
        $this->assertApiResponse($response, true);
    }

    /**
     * 測試缺少驗證憑證的請求
     * 
     * @test
     */
    public function test_request_without_authentication_credentials()
    {
        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 發送沒有驗證憑證的請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'AUTHENTICATION_REQUIRED', 401);
    }

    /**
     * 測試無效的驗證憑證
     * 
     * @test
     */
    public function test_request_with_invalid_credentials()
    {
        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 發送無效 Token 的請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer invalid_token_12345'
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'INVALID_CREDENTIALS', 401);
    }

    /**
     * 測試過期的 Token
     * 
     * @test
     */
    public function test_request_with_expired_token()
    {
        // 建立測試客戶端
        $client = $this->createTestClient(['is_active' => true]);

        // 建立過期的 Token
        $token = $this->createTestToken($client, [
            'expires_at' => now()->subDay()
        ]);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 發送請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token->token
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'TOKEN_EXPIRED', 401);
    }

    /**
     * 測試 API Key 驗證
     * 
     * @test
     */
    public function test_authentication_with_api_key()
    {
        // 建立測試客戶端
        $client = $this->createTestClient([
            'is_active' => true,
            'rate_limit' => 60
        ]);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 使用 API Key 發送請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'X-API-Key' => $client->api_key
        ]);

        // 驗證回應
        $response->assertStatus(200);
        $this->assertApiResponse($response, true);
    }

    /**
     * 測試參數驗證失敗
     * 
     * @test
     */
    public function test_request_with_invalid_parameters()
    {
        // 建立測試客戶端和 Token
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 建立必填參數
        FunctionParameter::factory()->create([
            'function_id' => $function->id,
            'name' => 'email',
            'data_type' => 'string',
            'is_required' => true,
            'validation_rules' => ['email']
        ]);

        // 發送無效參數的請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => [
                'email' => 'invalid-email'
            ]
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'VALIDATION_ERROR', 400);
        $response->assertJsonStructure([
            'error' => [
                'details'
            ]
        ]);
    }

    /**
     * 測試缺少必填參數
     * 
     * @test
     */
    public function test_request_with_missing_required_parameters()
    {
        // 建立測試客戶端和 Token
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 建立必填參數
        FunctionParameter::factory()->create([
            'function_id' => $function->id,
            'name' => 'user_id',
            'data_type' => 'integer',
            'is_required' => true
        ]);

        // 發送缺少參數的請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'VALIDATION_ERROR', 400);
    }

    /**
     * 測試 Function 不存在
     * 
     * @test
     */
    public function test_request_for_non_existent_function()
    {
        // 建立測試客戶端和 Token
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);

        // 發送請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'non.existent.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'FUNCTION_NOT_FOUND', 404);
    }

    /**
     * 測試已停用的 Function
     * 
     * @test
     */
    public function test_request_for_disabled_function()
    {
        // 建立測試客戶端和 Token
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);

        // 建立已停用的 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => false
        ]);

        // 發送請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'FUNCTION_DISABLED', 403);
    }

    /**
     * 測試權限不足
     * 
     * @test
     */
    public function test_request_without_permission()
    {
        // 建立測試客戶端和 Token（沒有權限）
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 建立權限限制（只允許特定客戶端）
        $function->permissions()->create([
            'client_id' => 999, // 不同的客戶端 ID
            'allowed' => true
        ]);

        // 發送請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'PERMISSION_DENIED', 403);
    }

    /**
     * 測試 Rate Limiting
     * 
     * @test
     */
    public function test_rate_limiting()
    {
        // 建立測試客戶端（限制為 2 次請求）
        $client = $this->createTestClient([
            'is_active' => true,
            'rate_limit' => 2
        ]);
        $token = $this->generateBearerToken($client);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 發送第一次請求（應該成功）
        $response1 = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response1->assertStatus(200);

        // 發送第二次請求（應該成功）
        $response2 = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response2->assertStatus(200);

        // 發送第三次請求（應該被限制）
        $response3 = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應
        $this->assertErrorResponse($response3, 'RATE_LIMIT_EXCEEDED', 429);
        
        // 驗證回應標頭包含速率限制資訊
        $response3->assertHeader('X-RateLimit-Limit');
        $response3->assertHeader('X-RateLimit-Remaining');
        $response3->assertHeader('Retry-After');
    }

    /**
     * 測試回應包含執行時間
     * 
     * @test
     */
    public function test_response_includes_execution_time()
    {
        // 建立測試客戶端和 Token
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 發送請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應包含 meta 資訊
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'meta' => [
                'execution_time'
            ]
        ]);
    }

    /**
     * 測試停用的客戶端無法存取
     * 
     * @test
     */
    public function test_disabled_client_cannot_access_api()
    {
        // 建立已停用的客戶端
        $client = $this->createTestClient(['is_active' => false]);
        $token = $this->generateBearerToken($client);

        // 建立測試 Function
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 發送請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證回應
        $this->assertErrorResponse($response, 'INVALID_CREDENTIALS', 401);
    }
}

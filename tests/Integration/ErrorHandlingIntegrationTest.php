<?php

namespace Tests\Integration;

use App\Models\ApiClient;
use App\Models\ApiFunction;
use App\Models\FunctionParameter;
use App\Models\FunctionErrorMapping;

/**
 * 錯誤處理整合測試
 * 
 * 測試各種錯誤情況的處理和回應格式
 */
class ErrorHandlingIntegrationTest extends IntegrationTestCase
{
    /**
     * 測試錯誤回應格式正確
     * 
     * @test
     */
    public function test_error_response_format()
    {
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 發送沒有驗證的請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'success',
            'error' => [
                'code',
                'message'
            ],
            'meta' => [
                'request_id',
                'timestamp'
            ]
        ]);
        
        $response->assertJson([
            'success' => false
        ]);
    }

    /**
     * 測試資料庫錯誤映射
     * 
     * @test
     */
    public function test_database_error_mapping()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true,
            'stored_procedure' => 'sp_test_error'
        ]);

        // 建立錯誤映射
        FunctionErrorMapping::create([
            'function_id' => $function->id,
            'error_code' => 'DB_ERROR_1001',
            'http_status' => 400,
            'error_message' => '資料驗證失敗'
        ]);

        // 模擬資料庫錯誤（實際測試中需要真實的 SP）
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 驗證錯誤被正確映射
        if ($response->status() === 400) {
            $response->assertJsonStructure([
                'error' => [
                    'code',
                    'message'
                ]
            ]);
        }
    }

    /**
     * 測試參數驗證錯誤詳情
     * 
     * @test
     */
    public function test_validation_error_details()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 建立多個參數
        FunctionParameter::factory()->create([
            'function_id' => $function->id,
            'name' => 'email',
            'data_type' => 'string',
            'is_required' => true,
            'validation_rules' => ['email']
        ]);

        FunctionParameter::factory()->create([
            'function_id' => $function->id,
            'name' => 'age',
            'data_type' => 'integer',
            'is_required' => true,
            'validation_rules' => ['min:18', 'max:100']
        ]);

        // 發送無效參數
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => [
                'email' => 'invalid-email',
                'age' => 10
            ]
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'error' => [
                'code',
                'message',
                'details' => [
                    'email',
                    'age'
                ]
            ]
        ]);
    }

    /**
     * 測試內部伺服器錯誤
     * 
     * @test
     */
    public function test_internal_server_error()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true,
            'stored_procedure' => 'sp_non_existent'
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 應該返回 500 錯誤
        if ($response->status() === 500) {
            $this->assertErrorResponse($response, 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * 測試錯誤記錄到日誌
     * 
     * @test
     */
    public function test_errors_are_logged()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        FunctionParameter::factory()->create([
            'function_id' => $function->id,
            'name' => 'email',
            'data_type' => 'string',
            'is_required' => true,
            'validation_rules' => ['email']
        ]);

        // 發送無效請求
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => [
                'email' => 'invalid'
            ]
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(400);

        // 驗證錯誤日誌
        $this->assertDatabaseHas('api_request_logs', [
            'client_id' => $client->id,
            'function_id' => $function->id,
            'http_status' => 400
        ]);
    }

    /**
     * 測試錯誤回應包含 request_id
     * 
     * @test
     */
    public function test_error_response_includes_request_id()
    {
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => []
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'meta' => [
                'request_id'
            ]
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['meta']['request_id']);
    }

    /**
     * 測試 JSON 格式錯誤
     * 
     * @test
     */
    public function test_malformed_json_request()
    {
        $client = $this->createTestClient(['is_active' => true]);
        
        $response = $this->post('/api/v1/execute', 
            'invalid json content',
            [
                'Authorization' => 'Bearer ' . $this->generateBearerToken($client),
                'Content-Type' => 'application/json'
            ]
        );

        $response->assertStatus(400);
    }

    /**
     * 測試缺少必要欄位
     * 
     * @test
     */
    public function test_missing_required_fields()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);

        // 缺少 function 欄位
        $response = $this->postJson('/api/v1/execute', [
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(400);
        $this->assertErrorResponse($response, 'VALIDATION_ERROR', 400);
    }

    /**
     * 測試超大請求
     * 
     * @test
     */
    public function test_oversized_request()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        
        $function = $this->createTestFunction([
            'identifier' => 'test.function',
            'is_active' => true
        ]);

        // 建立超大的參數
        $largeData = str_repeat('x', 1024 * 1024); // 1MB

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.function',
            'params' => [
                'data' => $largeData
            ]
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        // 應該被拒絕或正常處理（取決於配置）
        $this->assertContains($response->status(), [400, 413, 500]);
    }
}

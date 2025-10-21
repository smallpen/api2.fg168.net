<?php

namespace Tests\Integration;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\ApiFunction;

/**
 * 驗證機制整合測試
 * 
 * 測試各種驗證方式和驗證流程
 */
class AuthenticationIntegrationTest extends IntegrationTestCase
{
    /**
     * 測試 Bearer Token 驗證成功
     * 
     * @test
     */
    public function test_bearer_token_authentication_success()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試 API Key 驗證成功
     * 
     * @test
     */
    public function test_api_key_authentication_success()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'X-API-Key' => $client->api_key
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試同時提供多種驗證方式時優先使用 Bearer Token
     * 
     * @test
     */
    public function test_bearer_token_takes_precedence_over_api_key()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->generateBearerToken($client);
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        // 同時提供 Bearer Token 和 API Key
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token,
            'X-API-Key' => $client->api_key
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試 Token 格式錯誤
     * 
     * @test
     */
    public function test_malformed_bearer_token()
    {
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        // 缺少 "Bearer " 前綴
        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'Authorization' => 'invalid_token_format'
        ]);

        $this->assertErrorResponse($response, 'INVALID_CREDENTIALS', 401);
    }

    /**
     * 測試空的驗證標頭
     * 
     * @test
     */
    public function test_empty_authorization_header()
    {
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'Authorization' => ''
        ]);

        $this->assertErrorResponse($response, 'AUTHENTICATION_REQUIRED', 401);
    }

    /**
     * 測試 Token 使用後更新 last_used_at
     * 
     * @test
     */
    public function test_token_last_used_at_is_updated()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->createTestToken($client);
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        $originalLastUsed = $token->last_used_at;

        // 等待一秒確保時間戳不同
        sleep(1);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token->token
        ]);

        $response->assertStatus(200);

        // 重新載入 Token 並檢查 last_used_at
        $token->refresh();
        $this->assertNotEquals($originalLastUsed, $token->last_used_at);
    }

    /**
     * 測試撤銷的 Token 無法使用
     * 
     * @test
     */
    public function test_revoked_token_cannot_be_used()
    {
        $client = $this->createTestClient(['is_active' => true]);
        $token = $this->createTestToken($client, [
            'revoked_at' => now()
        ]);
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'Authorization' => 'Bearer ' . $token->token
        ]);

        $this->assertErrorResponse($response, 'INVALID_CREDENTIALS', 401);
    }

    /**
     * 測試不存在的 API Key
     * 
     * @test
     */
    public function test_non_existent_api_key()
    {
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'X-API-Key' => 'non_existent_api_key_12345'
        ]);

        $this->assertErrorResponse($response, 'INVALID_CREDENTIALS', 401);
    }

    /**
     * 測試驗證失敗記錄到安全日誌
     * 
     * @test
     */
    public function test_authentication_failure_is_logged()
    {
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/execute', [
            'function' => 'test.auth',
            'params' => []
        ], [
            'Authorization' => 'Bearer invalid_token'
        ]);

        $this->assertErrorResponse($response, 'INVALID_CREDENTIALS', 401);

        // 驗證安全日誌已記錄
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'authentication_failed'
        ]);
    }

    /**
     * 測試多次驗證失敗
     * 
     * @test
     */
    public function test_multiple_authentication_failures()
    {
        $function = $this->createTestFunction([
            'identifier' => 'test.auth',
            'is_active' => true
        ]);

        // 發送多次失敗的請求
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v1/execute', [
                'function' => 'test.auth',
                'params' => []
            ], [
                'Authorization' => 'Bearer invalid_token_' . $i
            ]);

            $this->assertErrorResponse($response, 'INVALID_CREDENTIALS', 401);
        }

        // 驗證所有失敗都被記錄
        $this->assertEquals(3, \App\Models\SecurityLog::where('event_type', 'authentication_failed')->count());
    }
}

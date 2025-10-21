<?php

namespace Tests\Integration;

use App\Models\ApiFunction;
use App\Models\FunctionParameter;
use App\Models\FunctionResponse;
use App\Models\FunctionErrorMapping;
use App\Models\User;

/**
 * Function 管理整合測試
 * 
 * 測試 Admin UI 的 Function CRUD 操作
 */
class FunctionManagementIntegrationTest extends IntegrationTestCase
{
    protected User $adminUser;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立管理員使用者
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'is_admin' => true
        ]);
    }

    /**
     * 測試列出所有 Functions
     * 
     * @test
     */
    public function test_list_all_functions()
    {
        // 建立測試 Functions
        ApiFunction::factory()->count(5)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/functions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'identifier',
                    'description',
                    'is_active',
                    'created_at'
                ]
            ]
        ]);
        
        $this->assertCount(5, $response->json('data'));
    }

    /**
     * 測試搜尋 Functions
     * 
     * @test
     */
    public function test_search_functions()
    {
        ApiFunction::factory()->create([
            'name' => '使用者管理',
            'identifier' => 'user.management'
        ]);
        
        ApiFunction::factory()->create([
            'name' => '訂單處理',
            'identifier' => 'order.process'
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/functions?search=使用者');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonFragment(['name' => '使用者管理']);
    }

    /**
     * 測試篩選啟用/停用的 Functions
     * 
     * @test
     */
    public function test_filter_functions_by_status()
    {
        ApiFunction::factory()->count(3)->create(['is_active' => true]);
        ApiFunction::factory()->count(2)->create(['is_active' => false]);

        // 篩選啟用的
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/functions?is_active=1');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));

        // 篩選停用的
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/functions?is_active=0');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /**
     * 測試取得單一 Function 詳情
     * 
     * @test
     */
    public function test_get_function_details()
    {
        $function = ApiFunction::factory()->create();
        
        // 建立參數
        FunctionParameter::factory()->count(2)->create([
            'function_id' => $function->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/functions/{$function->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'identifier',
                'description',
                'stored_procedure',
                'is_active',
                'parameters' => [
                    '*' => [
                        'id',
                        'name',
                        'data_type',
                        'is_required',
                        'validation_rules'
                    ]
                ]
            ]
        ]);
    }

    /**
     * 測試建立新的 Function
     * 
     * @test
     */
    public function test_create_new_function()
    {
        $functionData = [
            'name' => '測試 Function',
            'identifier' => 'test.new.function',
            'description' => '這是一個測試 Function',
            'stored_procedure' => 'sp_test_function',
            'is_active' => true
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/functions', $functionData);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => '測試 Function',
            'identifier' => 'test.new.function'
        ]);

        $this->assertDatabaseHas('api_functions', [
            'identifier' => 'test.new.function'
        ]);
    }

    /**
     * 測試建立 Function 時驗證唯一識別碼
     * 
     * @test
     */
    public function test_create_function_validates_unique_identifier()
    {
        $existing = ApiFunction::factory()->create([
            'identifier' => 'existing.function'
        ]);

        $functionData = [
            'name' => '重複的 Function',
            'identifier' => 'existing.function',
            'stored_procedure' => 'sp_test'
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/functions', $functionData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['identifier']);
    }

    /**
     * 測試更新 Function
     * 
     * @test
     */
    public function test_update_function()
    {
        $function = ApiFunction::factory()->create([
            'name' => '原始名稱',
            'is_active' => true
        ]);

        $updateData = [
            'name' => '更新後的名稱',
            'description' => '更新後的描述',
            'is_active' => false
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/functions/{$function->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => '更新後的名稱',
            'is_active' => false
        ]);

        $this->assertDatabaseHas('api_functions', [
            'id' => $function->id,
            'name' => '更新後的名稱'
        ]);
    }

    /**
     * 測試刪除 Function
     * 
     * @test
     */
    public function test_delete_function()
    {
        $function = ApiFunction::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/functions/{$function->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('api_functions', [
            'id' => $function->id
        ]);
    }

    /**
     * 測試刪除 Function 時同時刪除相關資料
     * 
     * @test
     */
    public function test_delete_function_cascades_related_data()
    {
        $function = ApiFunction::factory()->create();
        
        // 建立相關資料
        $parameter = FunctionParameter::factory()->create([
            'function_id' => $function->id
        ]);
        
        $response = FunctionResponse::factory()->create([
            'function_id' => $function->id
        ]);

        $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/functions/{$function->id}");

        // 驗證相關資料也被刪除
        $this->assertDatabaseMissing('function_parameters', [
            'id' => $parameter->id
        ]);
        
        $this->assertDatabaseMissing('function_responses', [
            'id' => $response->id
        ]);
    }

    /**
     * 測試啟用/停用 Function
     * 
     * @test
     */
    public function test_toggle_function_status()
    {
        $function = ApiFunction::factory()->create(['is_active' => true]);

        // 停用
        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/admin/functions/{$function->id}/toggle");

        $response->assertStatus(200);
        $this->assertDatabaseHas('api_functions', [
            'id' => $function->id,
            'is_active' => false
        ]);

        // 再次啟用
        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/admin/functions/{$function->id}/toggle");

        $response->assertStatus(200);
        $this->assertDatabaseHas('api_functions', [
            'id' => $function->id,
            'is_active' => true
        ]);
    }

    /**
     * 測試新增參數到 Function
     * 
     * @test
     */
    public function test_add_parameter_to_function()
    {
        $function = ApiFunction::factory()->create();

        $parameterData = [
            'name' => 'user_id',
            'data_type' => 'integer',
            'is_required' => true,
            'validation_rules' => ['min:1'],
            'sp_parameter_name' => 'p_user_id'
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/functions/{$function->id}/parameters", $parameterData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('function_parameters', [
            'function_id' => $function->id,
            'name' => 'user_id'
        ]);
    }

    /**
     * 測試更新參數
     * 
     * @test
     */
    public function test_update_parameter()
    {
        $function = ApiFunction::factory()->create();
        $parameter = FunctionParameter::factory()->create([
            'function_id' => $function->id,
            'is_required' => true
        ]);

        $updateData = [
            'is_required' => false,
            'default_value' => '0'
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/functions/{$function->id}/parameters/{$parameter->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('function_parameters', [
            'id' => $parameter->id,
            'is_required' => false,
            'default_value' => '0'
        ]);
    }

    /**
     * 測試刪除參數
     * 
     * @test
     */
    public function test_delete_parameter()
    {
        $function = ApiFunction::factory()->create();
        $parameter = FunctionParameter::factory()->create([
            'function_id' => $function->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/functions/{$function->id}/parameters/{$parameter->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('function_parameters', [
            'id' => $parameter->id
        ]);
    }

    /**
     * 測試記錄 Function 變更到審計日誌
     * 
     * @test
     */
    public function test_function_changes_are_audited()
    {
        $function = ApiFunction::factory()->create(['name' => '原始名稱']);

        $this->actingAs($this->adminUser)
            ->putJson("/api/admin/functions/{$function->id}", [
                'name' => '新名稱'
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'update',
            'resource_type' => 'api_function',
            'resource_id' => $function->id
        ]);
    }
}

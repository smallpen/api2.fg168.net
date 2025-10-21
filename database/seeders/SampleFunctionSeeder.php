<?php

namespace Database\Seeders;

use App\Models\ApiFunction;
use App\Models\FunctionParameter;
use App\Models\FunctionResponse;
use App\Models\FunctionErrorMapping;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample Function Seeder
 * 
 * 建立範例 API Function 用於測試和示範
 */
class SampleFunctionSeeder extends Seeder
{
    /**
     * 執行 Seeder
     */
    public function run(): void
    {
        // 取得管理員使用者作為建立者
        $admin = User::where('email', 'admin@example.com')->first();
        $createdBy = $admin ? $admin->id : null;

        // 建立範例 Function 1: 使用者查詢
        $this->createUserQueryFunction($createdBy);

        // 建立範例 Function 2: 使用者建立
        $this->createUserCreateFunction($createdBy);

        // 建立範例 Function 3: 訂單查詢
        $this->createOrderQueryFunction($createdBy);

        $this->command->info('範例 API Functions 建立完成！');
    }

    /**
     * 建立使用者查詢 Function
     */
    private function createUserQueryFunction(?int $createdBy): void
    {
        $function = ApiFunction::firstOrCreate(
            ['identifier' => 'user.query'],
            [
                'name' => '使用者查詢',
                'description' => '根據使用者 ID 或 Email 查詢使用者資訊',
                'stored_procedure' => 'sp_get_user_info',
                'is_active' => true,
                'created_by' => $createdBy,
            ]
        );

        // 建立參數定義
        $this->createUserQueryParameters($function);

        // 建立回應映射
        $this->createUserQueryResponses($function);

        // 建立錯誤映射
        $this->createUserQueryErrorMappings($function);

        $this->command->info("已建立範例 Function: {$function->name} ({$function->identifier})");
    }

    /**
     * 建立使用者查詢的參數
     */
    private function createUserQueryParameters(ApiFunction $function): void
    {
        $parameters = [
            [
                'name' => 'user_id',
                'data_type' => 'integer',
                'is_required' => false,
                'default_value' => null,
                'validation_rules' => ['min:1'],
                'sp_parameter_name' => 'p_user_id',
                'position' => 1,
            ],
            [
                'name' => 'email',
                'data_type' => 'string',
                'is_required' => false,
                'default_value' => null,
                'validation_rules' => ['email', 'max:255'],
                'sp_parameter_name' => 'p_email',
                'position' => 2,
            ],
        ];

        foreach ($parameters as $paramData) {
            FunctionParameter::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'name' => $paramData['name'],
                ],
                $paramData
            );
        }
    }

    /**
     * 建立使用者查詢的回應映射
     */
    private function createUserQueryResponses(ApiFunction $function): void
    {
        $responses = [
            [
                'field_name' => 'user_id',
                'sp_column_name' => 'id',
                'data_type' => 'integer',
                'transform_rule' => null,
            ],
            [
                'field_name' => 'name',
                'sp_column_name' => 'user_name',
                'data_type' => 'string',
                'transform_rule' => null,
            ],
            [
                'field_name' => 'email',
                'sp_column_name' => 'user_email',
                'data_type' => 'string',
                'transform_rule' => ['type' => 'lowercase'],
            ],
            [
                'field_name' => 'created_at',
                'sp_column_name' => 'created_date',
                'data_type' => 'datetime',
                'transform_rule' => null,
            ],
        ];

        foreach ($responses as $responseData) {
            FunctionResponse::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'field_name' => $responseData['field_name'],
                ],
                $responseData
            );
        }
    }

    /**
     * 建立使用者查詢的錯誤映射
     */
    private function createUserQueryErrorMappings(ApiFunction $function): void
    {
        $errorMappings = [
            [
                'error_code' => 'USER_NOT_FOUND',
                'http_status' => 404,
                'error_message' => '找不到指定的使用者',
            ],
            [
                'error_code' => 'INVALID_PARAMETER',
                'http_status' => 400,
                'error_message' => '參數格式不正確',
            ],
        ];

        foreach ($errorMappings as $mappingData) {
            FunctionErrorMapping::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'error_code' => $mappingData['error_code'],
                ],
                $mappingData
            );
        }
    }

    /**
     * 建立使用者建立 Function
     */
    private function createUserCreateFunction(?int $createdBy): void
    {
        $function = ApiFunction::firstOrCreate(
            ['identifier' => 'user.create'],
            [
                'name' => '使用者建立',
                'description' => '建立新的使用者帳號',
                'stored_procedure' => 'sp_create_user',
                'is_active' => true,
                'created_by' => $createdBy,
            ]
        );

        // 建立參數定義
        $this->createUserCreateParameters($function);

        // 建立回應映射
        $this->createUserCreateResponses($function);

        // 建立錯誤映射
        $this->createUserCreateErrorMappings($function);

        $this->command->info("已建立範例 Function: {$function->name} ({$function->identifier})");
    }

    /**
     * 建立使用者建立的參數
     */
    private function createUserCreateParameters(ApiFunction $function): void
    {
        $parameters = [
            [
                'name' => 'name',
                'data_type' => 'string',
                'is_required' => true,
                'default_value' => null,
                'validation_rules' => ['min:2', 'max:100'],
                'sp_parameter_name' => 'p_name',
                'position' => 1,
            ],
            [
                'name' => 'email',
                'data_type' => 'string',
                'is_required' => true,
                'default_value' => null,
                'validation_rules' => ['email', 'max:255'],
                'sp_parameter_name' => 'p_email',
                'position' => 2,
            ],
            [
                'name' => 'password',
                'data_type' => 'string',
                'is_required' => true,
                'default_value' => null,
                'validation_rules' => ['min:8', 'max:255'],
                'sp_parameter_name' => 'p_password',
                'position' => 3,
            ],
            [
                'name' => 'phone',
                'data_type' => 'string',
                'is_required' => false,
                'default_value' => null,
                'validation_rules' => ['max:20'],
                'sp_parameter_name' => 'p_phone',
                'position' => 4,
            ],
        ];

        foreach ($parameters as $paramData) {
            FunctionParameter::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'name' => $paramData['name'],
                ],
                $paramData
            );
        }
    }

    /**
     * 建立使用者建立的回應映射
     */
    private function createUserCreateResponses(ApiFunction $function): void
    {
        $responses = [
            [
                'field_name' => 'user_id',
                'sp_column_name' => 'new_user_id',
                'data_type' => 'integer',
                'transform_rule' => null,
            ],
            [
                'field_name' => 'created_at',
                'sp_column_name' => 'created_date',
                'data_type' => 'datetime',
                'transform_rule' => null,
            ],
            [
                'field_name' => 'message',
                'sp_column_name' => 'result_message',
                'data_type' => 'string',
                'transform_rule' => null,
            ],
        ];

        foreach ($responses as $responseData) {
            FunctionResponse::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'field_name' => $responseData['field_name'],
                ],
                $responseData
            );
        }
    }

    /**
     * 建立使用者建立的錯誤映射
     */
    private function createUserCreateErrorMappings(ApiFunction $function): void
    {
        $errorMappings = [
            [
                'error_code' => 'EMAIL_EXISTS',
                'http_status' => 409,
                'error_message' => 'Email 已被使用',
            ],
            [
                'error_code' => 'INVALID_EMAIL',
                'http_status' => 400,
                'error_message' => 'Email 格式不正確',
            ],
            [
                'error_code' => 'WEAK_PASSWORD',
                'http_status' => 400,
                'error_message' => '密碼強度不足',
            ],
        ];

        foreach ($errorMappings as $mappingData) {
            FunctionErrorMapping::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'error_code' => $mappingData['error_code'],
                ],
                $mappingData
            );
        }
    }

    /**
     * 建立訂單查詢 Function
     */
    private function createOrderQueryFunction(?int $createdBy): void
    {
        $function = ApiFunction::firstOrCreate(
            ['identifier' => 'order.query'],
            [
                'name' => '訂單查詢',
                'description' => '查詢訂單資訊和明細',
                'stored_procedure' => 'sp_get_order_details',
                'is_active' => true,
                'created_by' => $createdBy,
            ]
        );

        // 建立參數定義
        $this->createOrderQueryParameters($function);

        // 建立回應映射
        $this->createOrderQueryResponses($function);

        // 建立錯誤映射
        $this->createOrderQueryErrorMappings($function);

        $this->command->info("已建立範例 Function: {$function->name} ({$function->identifier})");
    }

    /**
     * 建立訂單查詢的參數
     */
    private function createOrderQueryParameters(ApiFunction $function): void
    {
        $parameters = [
            [
                'name' => 'order_id',
                'data_type' => 'integer',
                'is_required' => true,
                'default_value' => null,
                'validation_rules' => ['min:1'],
                'sp_parameter_name' => 'p_order_id',
                'position' => 1,
            ],
            [
                'name' => 'include_items',
                'data_type' => 'boolean',
                'is_required' => false,
                'default_value' => 'true',
                'validation_rules' => [],
                'sp_parameter_name' => 'p_include_items',
                'position' => 2,
            ],
        ];

        foreach ($parameters as $paramData) {
            FunctionParameter::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'name' => $paramData['name'],
                ],
                $paramData
            );
        }
    }

    /**
     * 建立訂單查詢的回應映射
     */
    private function createOrderQueryResponses(ApiFunction $function): void
    {
        $responses = [
            [
                'field_name' => 'order_id',
                'sp_column_name' => 'id',
                'data_type' => 'integer',
                'transform_rule' => null,
            ],
            [
                'field_name' => 'order_number',
                'sp_column_name' => 'order_no',
                'data_type' => 'string',
                'transform_rule' => ['type' => 'uppercase'],
            ],
            [
                'field_name' => 'total_amount',
                'sp_column_name' => 'total',
                'data_type' => 'float',
                'transform_rule' => null,
            ],
            [
                'field_name' => 'status',
                'sp_column_name' => 'order_status',
                'data_type' => 'string',
                'transform_rule' => null,
            ],
            [
                'field_name' => 'created_at',
                'sp_column_name' => 'created_date',
                'data_type' => 'datetime',
                'transform_rule' => null,
            ],
            [
                'field_name' => 'items',
                'sp_column_name' => 'order_items',
                'data_type' => 'json',
                'transform_rule' => null,
            ],
        ];

        foreach ($responses as $responseData) {
            FunctionResponse::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'field_name' => $responseData['field_name'],
                ],
                $responseData
            );
        }
    }

    /**
     * 建立訂單查詢的錯誤映射
     */
    private function createOrderQueryErrorMappings(ApiFunction $function): void
    {
        $errorMappings = [
            [
                'error_code' => 'ORDER_NOT_FOUND',
                'http_status' => 404,
                'error_message' => '找不到指定的訂單',
            ],
            [
                'error_code' => 'ORDER_CANCELLED',
                'http_status' => 410,
                'error_message' => '訂單已取消',
            ],
        ];

        foreach ($errorMappings as $mappingData) {
            FunctionErrorMapping::firstOrCreate(
                [
                    'function_id' => $function->id,
                    'error_code' => $mappingData['error_code'],
                ],
                $mappingData
            );
        }
    }
}

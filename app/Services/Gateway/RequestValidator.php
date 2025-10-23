<?php

namespace App\Services\Gateway;

use App\Models\ApiFunction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * 請求驗證器
 * 
 * 負責根據 API Function 配置動態驗證請求參數
 */
class RequestValidator
{
    /**
     * 驗證請求參數
     * 
     * 根據 Function 的參數配置動態驗證請求參數
     * 
     * @param array $params 請求參數
     * @param ApiFunction $function API Function 物件
     * @return array 驗證後的參數
     * @throws ValidationException 驗證失敗時拋出
     */
    public function validate(array $params, ApiFunction $function): array
    {
        // 建立驗證規則
        $rules = $this->buildValidationRules($function);
        
        // 建立自訂錯誤訊息
        $messages = $this->buildValidationMessages($function);
        
        // 建立自訂屬性名稱
        $attributes = $this->buildAttributeNames($function);

        Log::debug('開始驗證請求參數', [
            'function' => $function->identifier,
            'params_count' => count($params),
            'rules_count' => count($rules),
        ]);

        // 執行驗證
        $validator = Validator::make($params, $rules, $messages, $attributes);

        // 加入自訂驗證規則
        $this->addCustomValidationRules($validator, $function);

        // 如果驗證失敗，拋出例外
        if ($validator->fails()) {
            Log::warning('參數驗證失敗', [
                'function' => $function->identifier,
                'errors' => $validator->errors()->toArray(),
            ]);

            throw new ValidationException($validator);
        }

        // 返回驗證後的資料
        $validated = $validator->validated();

        Log::debug('參數驗證成功', [
            'function' => $function->identifier,
            'validated_count' => count($validated),
        ]);

        return $validated;
    }

    /**
     * 建立驗證規則
     * 
     * 根據 Function 的參數配置建立 Laravel 驗證規則
     * 
     * @param ApiFunction $function API Function 物件
     * @return array 驗證規則陣列
     */
    protected function buildValidationRules(ApiFunction $function): array
    {
        $rules = [];

        foreach ($function->parameters as $parameter) {
            $paramRules = [];

            // 必填規則
            if ($parameter->is_required) {
                $paramRules[] = 'required';
            } else {
                $paramRules[] = 'nullable';
            }

            // 資料類型規則
            $paramRules = array_merge($paramRules, $this->getDataTypeRules($parameter->data_type));

            // 自訂驗證規則
            if (!empty($parameter->validation_rules)) {
                $customRules = $this->parseCustomRules($parameter->validation_rules);
                $paramRules = array_merge($paramRules, $customRules);
            }

            $rules[$parameter->name] = $paramRules;
        }

        return $rules;
    }

    /**
     * 取得資料類型對應的驗證規則
     * 
     * @param string $dataType 資料類型
     * @return array 驗證規則陣列
     */
    protected function getDataTypeRules(string $dataType): array
    {
        return match($dataType) {
            'string' => ['string'],
            'integer', 'int' => ['integer'],
            'float', 'double', 'decimal' => ['numeric'],
            'boolean', 'bool' => ['boolean'],
            'date' => ['date_format:Y-m-d'],
            'datetime' => ['date_format:Y-m-d H:i:s'],
            'json' => ['json'],
            'array' => ['array'],
            'email' => ['email'],
            'url' => ['url'],
            default => [],
        };
    }

    /**
     * 解析自訂驗證規則
     * 
     * 將儲存在資料庫中的驗證規則轉換為 Laravel 驗證規則格式
     * 
     * @param mixed $customRules 自訂規則（可能是陣列或字串）
     * @return array 解析後的規則陣列
     */
    protected function parseCustomRules($customRules): array
    {
        if (is_array($customRules)) {
            return $customRules;
        }

        if (is_string($customRules)) {
            // 如果是字串，用 | 分隔
            return explode('|', $customRules);
        }

        return [];
    }

    /**
     * 建立驗證錯誤訊息
     * 
     * @param ApiFunction $function API Function 物件
     * @return array 錯誤訊息陣列
     */
    protected function buildValidationMessages(ApiFunction $function): array
    {
        $messages = [];

        foreach ($function->parameters as $parameter) {
            $fieldName = $parameter->name;

            // 必填訊息
            if ($parameter->is_required) {
                $messages["{$fieldName}.required"] = "{$fieldName} 為必填欄位";
            }

            // 資料類型訊息
            $messages = array_merge($messages, $this->getDataTypeMessages($fieldName, $parameter->data_type));
        }

        return $messages;
    }

    /**
     * 取得資料類型對應的錯誤訊息
     * 
     * @param string $fieldName 欄位名稱
     * @param string $dataType 資料類型
     * @return array 錯誤訊息陣列
     */
    protected function getDataTypeMessages(string $fieldName, string $dataType): array
    {
        $messages = [];

        switch ($dataType) {
            case 'string':
                $messages["{$fieldName}.string"] = "{$fieldName} 必須是字串";
                break;
            case 'integer':
            case 'int':
                $messages["{$fieldName}.integer"] = "{$fieldName} 必須是整數";
                break;
            case 'float':
            case 'double':
            case 'decimal':
                $messages["{$fieldName}.numeric"] = "{$fieldName} 必須是數字";
                break;
            case 'boolean':
            case 'bool':
                $messages["{$fieldName}.boolean"] = "{$fieldName} 必須是布林值";
                break;
            case 'date':
                $messages["{$fieldName}.date_format"] = "{$fieldName} 必須是有效的日期格式 (Y-m-d)";
                break;
            case 'datetime':
                $messages["{$fieldName}.date_format"] = "{$fieldName} 必須是有效的日期時間格式 (Y-m-d H:i:s)";
                break;
            case 'json':
                $messages["{$fieldName}.json"] = "{$fieldName} 必須是有效的 JSON 格式";
                break;
            case 'array':
                $messages["{$fieldName}.array"] = "{$fieldName} 必須是陣列";
                break;
            case 'email':
                $messages["{$fieldName}.email"] = "{$fieldName} 必須是有效的電子郵件地址";
                break;
            case 'url':
                $messages["{$fieldName}.url"] = "{$fieldName} 必須是有效的 URL";
                break;
        }

        return $messages;
    }

    /**
     * 建立屬性名稱對應
     * 
     * @param ApiFunction $function API Function 物件
     * @return array 屬性名稱陣列
     */
    protected function buildAttributeNames(ApiFunction $function): array
    {
        $attributes = [];

        foreach ($function->parameters as $parameter) {
            $attributes[$parameter->name] = $parameter->name;
        }

        return $attributes;
    }

    /**
     * 加入自訂驗證規則
     * 
     * 為特定的 Function 加入額外的自訂驗證邏輯
     * 
     * @param \Illuminate\Validation\Validator $validator 驗證器實例
     * @param ApiFunction $function API Function 物件
     */
    protected function addCustomValidationRules($validator, ApiFunction $function): void
    {
        // 可以在這裡加入自訂的驗證規則
        // 例如：跨欄位驗證、複雜的業務邏輯驗證等

        // 範例：使用 after 方法加入自訂驗證邏輯
        $validator->after(function ($validator) use ($function) {
            // 自訂驗證邏輯可以在這裡實作
            // 例如：
            // if (某個條件) {
            //     $validator->errors()->add('field_name', '錯誤訊息');
            // }
        });
    }

    /**
     * 驗證並填充預設值
     * 
     * 驗證參數並為缺少的可選參數填充預設值
     * 
     * @param array $params 請求參數
     * @param ApiFunction $function API Function 物件
     * @return array 填充預設值後的參數
     * @throws ValidationException 驗證失敗時拋出
     */
    public function validateAndFillDefaults(array $params, ApiFunction $function): array
    {
        // 先執行驗證
        $validated = $this->validate($params, $function);

        // 填充預設值
        foreach ($function->parameters as $parameter) {
            // 如果參數不存在且有預設值，則填充
            if (!isset($validated[$parameter->name]) && $parameter->default_value !== null) {
                $validated[$parameter->name] = $this->castDefaultValue(
                    $parameter->default_value,
                    $parameter->data_type
                );

                Log::debug('填充預設值', [
                    'function' => $function->identifier,
                    'parameter' => $parameter->name,
                    'default_value' => $parameter->default_value,
                ]);
            }
        }

        return $validated;
    }

    /**
     * 轉換預設值為正確的資料類型
     * 
     * @param mixed $value 預設值
     * @param string $dataType 資料類型
     * @return mixed 轉換後的值
     */
    protected function castDefaultValue($value, string $dataType)
    {
        return match($dataType) {
            'integer', 'int' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => is_array($value) ? $value : json_decode($value, true),
            'json' => is_string($value) ? $value : json_encode($value),
            default => $value,
        };
    }
}

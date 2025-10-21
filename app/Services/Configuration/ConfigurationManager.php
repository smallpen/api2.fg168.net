<?php

namespace App\Services\Configuration;

use App\Models\ApiFunction;
use App\Repositories\FunctionRepository;
use App\Exceptions\FunctionNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Configuration Manager
 * 
 * 負責管理 API Function 的配置載入和驗證
 */
class ConfigurationManager
{
    /**
     * @var FunctionRepository
     */
    protected $functionRepository;

    /**
     * @var ConfigurationCache
     */
    protected $cache;

    /**
     * ConfigurationManager constructor
     */
    public function __construct(
        FunctionRepository $functionRepository,
        ConfigurationCache $cache
    ) {
        $this->functionRepository = $functionRepository;
        $this->cache = $cache;
    }

    /**
     * 根據識別碼載入 API Function 配置
     * 
     * @param string $identifier Function 識別碼
     * @param bool $activeOnly 是否只載入啟用的 Function
     * @return ApiFunction
     * @throws FunctionNotFoundException
     */
    public function loadConfiguration(string $identifier, bool $activeOnly = true): ApiFunction
    {
        // 先嘗試從快取載入
        $function = $this->cache->get($identifier);

        if ($function) {
            // 如果需要檢查啟用狀態
            if ($activeOnly && !$function->is_active) {
                throw new FunctionNotFoundException("API Function '{$identifier}' 已停用");
            }
            return $function;
        }

        // 從資料庫載入
        if ($activeOnly) {
            $function = $this->functionRepository->findActiveByIdentifier($identifier);
        } else {
            $function = $this->functionRepository->findByIdentifier($identifier);
        }

        if (!$function) {
            throw new FunctionNotFoundException("找不到 API Function: {$identifier}");
        }

        // 驗證配置
        $this->validateConfiguration($function);

        // 儲存到快取
        $this->cache->put($identifier, $function);

        return $function;
    }

    /**
     * 驗證 API Function 配置的完整性和正確性
     * 
     * @param ApiFunction $function
     * @throws ValidationException
     */
    public function validateConfiguration(ApiFunction $function): void
    {
        $errors = [];

        // 驗證基本資訊
        if (empty($function->name)) {
            $errors['name'] = ['Function 名稱不能為空'];
        }

        if (empty($function->identifier)) {
            $errors['identifier'] = ['Function 識別碼不能為空'];
        }

        if (empty($function->stored_procedure)) {
            $errors['stored_procedure'] = ['Stored Procedure 名稱不能為空'];
        }

        // 驗證參數配置
        $this->validateParameters($function, $errors);

        // 驗證回應配置
        $this->validateResponses($function, $errors);

        // 驗證錯誤映射配置
        $this->validateErrorMappings($function, $errors);

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * 驗證參數配置
     */
    protected function validateParameters(ApiFunction $function, array &$errors): void
    {
        $parameters = $function->parameters;

        if ($parameters->isEmpty()) {
            // 參數可以為空，某些 API 可能不需要參數
            return;
        }

        $parameterNames = [];
        $spParameterNames = [];

        foreach ($parameters as $index => $parameter) {
            $prefix = "parameters.{$index}";

            // 檢查參數名稱
            if (empty($parameter->name)) {
                $errors["{$prefix}.name"] = ['參數名稱不能為空'];
            } elseif (in_array($parameter->name, $parameterNames)) {
                $errors["{$prefix}.name"] = ["參數名稱 '{$parameter->name}' 重複"];
            } else {
                $parameterNames[] = $parameter->name;
            }

            // 檢查資料類型
            $validTypes = ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json', 'array'];
            if (!in_array($parameter->data_type, $validTypes)) {
                $errors["{$prefix}.data_type"] = ["不支援的資料類型: {$parameter->data_type}"];
            }

            // 檢查 SP 參數名稱
            if (empty($parameter->sp_parameter_name)) {
                $errors["{$prefix}.sp_parameter_name"] = ['Stored Procedure 參數名稱不能為空'];
            } elseif (in_array($parameter->sp_parameter_name, $spParameterNames)) {
                $errors["{$prefix}.sp_parameter_name"] = ["SP 參數名稱 '{$parameter->sp_parameter_name}' 重複"];
            } else {
                $spParameterNames[] = $parameter->sp_parameter_name;
            }

            // 驗證驗證規則格式
            if (!empty($parameter->validation_rules)) {
                $this->validateValidationRules($parameter->validation_rules, $prefix, $errors);
            }
        }
    }

    /**
     * 驗證驗證規則格式
     */
    protected function validateValidationRules($rules, string $prefix, array &$errors): void
    {
        if (!is_array($rules)) {
            $errors["{$prefix}.validation_rules"] = ['驗證規則必須是陣列格式'];
            return;
        }

        // 這裡可以進一步驗證每個規則的格式
        // 例如檢查是否為有效的 Laravel 驗證規則
    }

    /**
     * 驗證回應配置
     */
    protected function validateResponses(ApiFunction $function, array &$errors): void
    {
        $responses = $function->responses;

        if ($responses->isEmpty()) {
            // 回應配置可以為空，使用預設格式
            return;
        }

        $fieldNames = [];

        foreach ($responses as $index => $response) {
            $prefix = "responses.{$index}";

            // 檢查欄位名稱
            if (empty($response->field_name)) {
                $errors["{$prefix}.field_name"] = ['回應欄位名稱不能為空'];
            } elseif (in_array($response->field_name, $fieldNames)) {
                $errors["{$prefix}.field_name"] = ["回應欄位名稱 '{$response->field_name}' 重複"];
            } else {
                $fieldNames[] = $response->field_name;
            }

            // 檢查 SP 欄位名稱
            if (empty($response->sp_column_name)) {
                $errors["{$prefix}.sp_column_name"] = ['Stored Procedure 欄位名稱不能為空'];
            }

            // 檢查資料類型
            $validTypes = ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json', 'array'];
            if (!empty($response->data_type) && !in_array($response->data_type, $validTypes)) {
                $errors["{$prefix}.data_type"] = ["不支援的資料類型: {$response->data_type}"];
            }
        }
    }

    /**
     * 驗證錯誤映射配置
     */
    protected function validateErrorMappings(ApiFunction $function, array &$errors): void
    {
        $errorMappings = $function->errorMappings;

        if ($errorMappings->isEmpty()) {
            // 錯誤映射可以為空，使用預設錯誤處理
            return;
        }

        $errorCodes = [];

        foreach ($errorMappings as $index => $mapping) {
            $prefix = "error_mappings.{$index}";

            // 檢查錯誤碼
            if (empty($mapping->error_code)) {
                $errors["{$prefix}.error_code"] = ['錯誤碼不能為空'];
            } elseif (in_array($mapping->error_code, $errorCodes)) {
                $errors["{$prefix}.error_code"] = ["錯誤碼 '{$mapping->error_code}' 重複"];
            } else {
                $errorCodes[] = $mapping->error_code;
            }

            // 檢查 HTTP 狀態碼
            if (empty($mapping->http_status)) {
                $errors["{$prefix}.http_status"] = ['HTTP 狀態碼不能為空'];
            } elseif ($mapping->http_status < 100 || $mapping->http_status > 599) {
                $errors["{$prefix}.http_status"] = ['HTTP 狀態碼必須在 100-599 之間'];
            }

            // 檢查錯誤訊息
            if (empty($mapping->error_message)) {
                $errors["{$prefix}.error_message"] = ['錯誤訊息不能為空'];
            }
        }
    }

    /**
     * 重新載入配置（清除快取並重新載入）
     */
    public function reloadConfiguration(string $identifier): ApiFunction
    {
        $this->cache->forget($identifier);
        return $this->loadConfiguration($identifier, false);
    }

    /**
     * 批次載入多個配置
     */
    public function loadMultipleConfigurations(array $identifiers, bool $activeOnly = true): array
    {
        $configurations = [];

        foreach ($identifiers as $identifier) {
            try {
                $configurations[$identifier] = $this->loadConfiguration($identifier, $activeOnly);
            } catch (FunctionNotFoundException $e) {
                // 記錄錯誤但繼續處理其他配置
                \Log::warning("無法載入配置: {$identifier}", ['error' => $e->getMessage()]);
            }
        }

        return $configurations;
    }

    /**
     * 取得所有啟用的配置
     */
    public function getAllActiveConfigurations(): array
    {
        $functions = $this->functionRepository->getAllActive();
        $configurations = [];

        foreach ($functions as $function) {
            try {
                $this->validateConfiguration($function);
                $configurations[$function->identifier] = $function;
                
                // 確保快取中有此配置
                $this->cache->put($function->identifier, $function);
            } catch (ValidationException $e) {
                // 記錄驗證失敗的配置
                \Log::error("配置驗證失敗: {$function->identifier}", [
                    'errors' => $e->errors()
                ]);
            }
        }

        return $configurations;
    }

    /**
     * 檢查配置是否存在
     */
    public function configurationExists(string $identifier): bool
    {
        try {
            $this->loadConfiguration($identifier, false);
            return true;
        } catch (FunctionNotFoundException $e) {
            return false;
        }
    }

    /**
     * 清除所有配置快取
     */
    public function clearAllCache(): void
    {
        $this->cache->flush();
    }
}

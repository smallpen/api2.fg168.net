<?php

namespace App\Services\Configuration;

use App\Models\ApiFunction;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

/**
 * Configuration Cache
 * 
 * 使用 Redis 快取 API Function 配置
 */
class ConfigurationCache
{
    /**
     * 快取鍵前綴
     */
    protected const CACHE_PREFIX = 'api_config:';

    /**
     * 快取標籤
     */
    protected const CACHE_TAG = 'api_configurations';

    /**
     * 預設快取時間（秒）- 1 小時
     */
    protected const DEFAULT_TTL = 3600;

    /**
     * 快取驅動
     */
    protected $cache;

    /**
     * ConfigurationCache constructor
     */
    public function __construct()
    {
        $this->cache = Cache::store(config('cache.default'));
    }

    /**
     * 從快取取得配置
     * 
     * @param string $identifier Function 識別碼
     * @return ApiFunction|null
     */
    public function get(string $identifier): ?ApiFunction
    {
        $key = $this->getCacheKey($identifier);
        
        try {
            $cached = $this->cache->get($key);
            
            if ($cached) {
                // 反序列化並重新建立 Model 實例
                return $this->unserializeFunction($cached);
            }
        } catch (\Exception $e) {
            \Log::warning("快取讀取失敗: {$identifier}", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * 將配置儲存到快取
     * 
     * @param string $identifier Function 識別碼
     * @param ApiFunction $function
     * @param int|null $ttl 快取時間（秒），null 使用預設值
     * @return bool
     */
    public function put(string $identifier, ApiFunction $function, ?int $ttl = null): bool
    {
        $key = $this->getCacheKey($identifier);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        try {
            // 序列化 Function 及其關聯資料
            $serialized = $this->serializeFunction($function);
            
            return $this->cache->put($key, $serialized, $ttl);
        } catch (\Exception $e) {
            \Log::error("快取寫入失敗: {$identifier}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 從快取移除配置
     * 
     * @param string $identifier Function 識別碼
     * @return bool
     */
    public function forget(string $identifier): bool
    {
        $key = $this->getCacheKey($identifier);
        
        try {
            return $this->cache->forget($key);
        } catch (\Exception $e) {
            \Log::warning("快取刪除失敗: {$identifier}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 檢查快取是否存在
     * 
     * @param string $identifier Function 識別碼
     * @return bool
     */
    public function has(string $identifier): bool
    {
        $key = $this->getCacheKey($identifier);
        
        try {
            return $this->cache->has($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 清除所有配置快取
     * 
     * @return bool
     */
    public function flush(): bool
    {
        try {
            // 使用標籤清除（如果支援）
            if (method_exists($this->cache, 'tags')) {
                $this->cache->tags([self::CACHE_TAG])->flush();
            } else {
                // 如果不支援標籤，清除所有快取
                $this->cache->flush();
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error("快取清除失敗", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 批次取得多個配置
     * 
     * @param array $identifiers Function 識別碼陣列
     * @return array
     */
    public function getMany(array $identifiers): array
    {
        $results = [];

        foreach ($identifiers as $identifier) {
            $function = $this->get($identifier);
            if ($function) {
                $results[$identifier] = $function;
            }
        }

        return $results;
    }

    /**
     * 批次儲存多個配置
     * 
     * @param array $functions [identifier => ApiFunction]
     * @param int|null $ttl 快取時間（秒）
     * @return bool
     */
    public function putMany(array $functions, ?int $ttl = null): bool
    {
        $success = true;

        foreach ($functions as $identifier => $function) {
            if (!$this->put($identifier, $function, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 批次移除多個配置
     * 
     * @param array $identifiers Function 識別碼陣列
     * @return bool
     */
    public function forgetMany(array $identifiers): bool
    {
        $success = true;

        foreach ($identifiers as $identifier) {
            if (!$this->forget($identifier)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 更新快取（先刪除再新增）
     * 
     * @param string $identifier Function 識別碼
     * @param ApiFunction $function
     * @param int|null $ttl 快取時間（秒）
     * @return bool
     */
    public function update(string $identifier, ApiFunction $function, ?int $ttl = null): bool
    {
        $this->forget($identifier);
        return $this->put($identifier, $function, $ttl);
    }

    /**
     * 取得快取的剩餘時間（秒）
     * 
     * @param string $identifier Function 識別碼
     * @return int|null 剩餘秒數，null 表示不存在或無法取得
     */
    public function getTtl(string $identifier): ?int
    {
        $key = $this->getCacheKey($identifier);

        try {
            // 使用 Redis 直接取得 TTL
            if (config('cache.default') === 'redis') {
                $ttl = Redis::ttl($key);
                return $ttl > 0 ? $ttl : null;
            }
        } catch (\Exception $e) {
            \Log::warning("無法取得快取 TTL: {$identifier}", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * 延長快取時間
     * 
     * @param string $identifier Function 識別碼
     * @param int $additionalSeconds 要延長的秒數
     * @return bool
     */
    public function extend(string $identifier, int $additionalSeconds): bool
    {
        $function = $this->get($identifier);
        
        if (!$function) {
            return false;
        }

        $currentTtl = $this->getTtl($identifier) ?? 0;
        $newTtl = $currentTtl + $additionalSeconds;

        return $this->put($identifier, $function, $newTtl);
    }

    /**
     * 取得快取鍵
     */
    protected function getCacheKey(string $identifier): string
    {
        return self::CACHE_PREFIX . $identifier;
    }

    /**
     * 序列化 Function 物件
     */
    protected function serializeFunction(ApiFunction $function): array
    {
        return [
            'id' => $function->id,
            'name' => $function->name,
            'identifier' => $function->identifier,
            'description' => $function->description,
            'stored_procedure' => $function->stored_procedure,
            'is_active' => $function->is_active,
            'created_by' => $function->created_by,
            'created_at' => $function->created_at?->toDateTimeString(),
            'updated_at' => $function->updated_at?->toDateTimeString(),
            'parameters' => $function->parameters->map(function ($param) {
                return [
                    'id' => $param->id,
                    'function_id' => $param->function_id,
                    'name' => $param->name,
                    'data_type' => $param->data_type,
                    'is_required' => $param->is_required,
                    'default_value' => $param->default_value,
                    'validation_rules' => $param->validation_rules,
                    'sp_parameter_name' => $param->sp_parameter_name,
                    'position' => $param->position,
                ];
            })->toArray(),
            'responses' => $function->responses->map(function ($response) {
                return [
                    'id' => $response->id,
                    'function_id' => $response->function_id,
                    'field_name' => $response->field_name,
                    'sp_column_name' => $response->sp_column_name,
                    'data_type' => $response->data_type,
                    'transform_rule' => $response->transform_rule,
                ];
            })->toArray(),
            'errorMappings' => $function->errorMappings->map(function ($mapping) {
                return [
                    'id' => $mapping->id,
                    'function_id' => $mapping->function_id,
                    'error_code' => $mapping->error_code,
                    'http_status' => $mapping->http_status,
                    'error_message' => $mapping->error_message,
                ];
            })->toArray(),
        ];
    }

    /**
     * 反序列化 Function 物件
     */
    protected function unserializeFunction(array $data): ApiFunction
    {
        $function = new ApiFunction($data);
        $function->exists = true;

        // 重建關聯資料
        if (isset($data['parameters'])) {
            $function->setRelation('parameters', collect($data['parameters'])->map(function ($param) {
                $parameter = new \App\Models\FunctionParameter($param);
                $parameter->exists = true;
                return $parameter;
            }));
        }

        if (isset($data['responses'])) {
            $function->setRelation('responses', collect($data['responses'])->map(function ($response) {
                $responseModel = new \App\Models\FunctionResponse($response);
                $responseModel->exists = true;
                return $responseModel;
            }));
        }

        if (isset($data['errorMappings'])) {
            $function->setRelation('errorMappings', collect($data['errorMappings'])->map(function ($mapping) {
                $mappingModel = new \App\Models\FunctionErrorMapping($mapping);
                $mappingModel->exists = true;
                return $mappingModel;
            }));
        }

        return $function;
    }

    /**
     * 取得快取統計資訊
     */
    public function getStats(): array
    {
        try {
            if (config('cache.default') === 'redis') {
                $keys = Redis::keys(self::CACHE_PREFIX . '*');
                
                return [
                    'total_cached' => count($keys),
                    'cache_prefix' => self::CACHE_PREFIX,
                    'cache_tag' => self::CACHE_TAG,
                    'default_ttl' => self::DEFAULT_TTL,
                ];
            }
        } catch (\Exception $e) {
            \Log::warning("無法取得快取統計", [
                'error' => $e->getMessage()
            ]);
        }

        return [
            'total_cached' => 0,
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_tag' => self::CACHE_TAG,
            'default_ttl' => self::DEFAULT_TTL,
        ];
    }
}

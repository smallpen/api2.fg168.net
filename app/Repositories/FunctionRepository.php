<?php

namespace App\Repositories;

use App\Models\ApiFunction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Function Repository
 * 
 * 提供 API Function 的資料存取操作
 */
class FunctionRepository extends BaseRepository
{
    /**
     * 快取鍵前綴
     */
    protected const CACHE_PREFIX = 'api_function:';

    /**
     * 快取時間（秒）
     */
    protected const CACHE_TTL = 3600;

    /**
     * FunctionRepository constructor
     */
    public function __construct(ApiFunction $model)
    {
        parent::__construct($model);
    }

    /**
     * 根據識別碼查找 Function
     */
    public function findByIdentifier(string $identifier): ?ApiFunction
    {
        $cacheKey = self::CACHE_PREFIX . 'identifier:' . $identifier;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($identifier) {
            return $this->model
                ->with(['parameters', 'responses', 'errorMappings'])
                ->where('identifier', $identifier)
                ->first();
        });
    }

    /**
     * 根據識別碼查找啟用的 Function
     */
    public function findActiveByIdentifier(string $identifier): ?ApiFunction
    {
        $cacheKey = self::CACHE_PREFIX . 'active:' . $identifier;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($identifier) {
            return $this->model
                ->with(['parameters', 'responses', 'errorMappings'])
                ->where('identifier', $identifier)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * 取得所有啟用的 Functions
     */
    public function getAllActive(): Collection
    {
        return $this->model
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * 取得分頁的 Functions 列表
     */
    public function paginate(int $perPage = 15, array $filters = [])
    {
        $query = $this->model->newQuery();

        // 套用篩選條件
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('identifier', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['stored_procedure'])) {
            $query->where('stored_procedure', $filters['stored_procedure']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 建立 Function 及其相關資料
     */
    public function createWithRelations(array $data): ApiFunction
    {
        $function = $this->create([
            'name' => $data['name'],
            'identifier' => $data['identifier'],
            'description' => $data['description'] ?? null,
            'stored_procedure' => $data['stored_procedure'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $data['created_by'] ?? null,
        ]);

        // 建立參數
        if (isset($data['parameters']) && is_array($data['parameters'])) {
            foreach ($data['parameters'] as $parameter) {
                $function->parameters()->create($parameter);
            }
        }

        // 建立回應映射
        if (isset($data['responses']) && is_array($data['responses'])) {
            foreach ($data['responses'] as $response) {
                $function->responses()->create($response);
            }
        }

        // 建立錯誤映射
        if (isset($data['error_mappings']) && is_array($data['error_mappings'])) {
            foreach ($data['error_mappings'] as $errorMapping) {
                $function->errorMappings()->create($errorMapping);
            }
        }

        $this->clearCache($function->identifier);

        return $function->load(['parameters', 'responses', 'errorMappings']);
    }

    /**
     * 更新 Function 及其相關資料
     */
    public function updateWithRelations(int $id, array $data): ApiFunction
    {
        $function = $this->findOrFail($id);

        // 更新基本資訊
        $function->update([
            'name' => $data['name'] ?? $function->name,
            'identifier' => $data['identifier'] ?? $function->identifier,
            'description' => $data['description'] ?? $function->description,
            'stored_procedure' => $data['stored_procedure'] ?? $function->stored_procedure,
            'is_active' => $data['is_active'] ?? $function->is_active,
        ]);

        // 更新參數（如果提供）
        if (isset($data['parameters'])) {
            $function->parameters()->delete();
            foreach ($data['parameters'] as $parameter) {
                $function->parameters()->create($parameter);
            }
        }

        // 更新回應映射（如果提供）
        if (isset($data['responses'])) {
            $function->responses()->delete();
            foreach ($data['responses'] as $response) {
                $function->responses()->create($response);
            }
        }

        // 更新錯誤映射（如果提供）
        if (isset($data['error_mappings'])) {
            $function->errorMappings()->delete();
            foreach ($data['error_mappings'] as $errorMapping) {
                $function->errorMappings()->create($errorMapping);
            }
        }

        $this->clearCache($function->identifier);

        return $function->fresh(['parameters', 'responses', 'errorMappings']);
    }

    /**
     * 啟用 Function
     */
    public function activate(int $id): bool
    {
        $function = $this->findOrFail($id);
        $result = $function->activate();
        
        if ($result) {
            $this->clearCache($function->identifier);
        }

        return $result;
    }

    /**
     * 停用 Function
     */
    public function deactivate(int $id): bool
    {
        $function = $this->findOrFail($id);
        $result = $function->deactivate();
        
        if ($result) {
            $this->clearCache($function->identifier);
        }

        return $result;
    }

    /**
     * 檢查識別碼是否已存在
     */
    public function identifierExists(string $identifier, ?int $excludeId = null): bool
    {
        $query = $this->model->where('identifier', $identifier);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 清除快取
     */
    protected function clearCache(string $identifier): void
    {
        Cache::forget(self::CACHE_PREFIX . 'identifier:' . $identifier);
        Cache::forget(self::CACHE_PREFIX . 'active:' . $identifier);
    }

    /**
     * 清除所有 Function 快取
     */
    public function clearAllCache(): void
    {
        Cache::flush();
    }
}

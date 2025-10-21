<?php

namespace App\Repositories;

use App\Models\FunctionParameter;
use Illuminate\Database\Eloquent\Collection;

/**
 * Parameter Repository
 * 
 * 提供 Function Parameter 的資料存取操作
 */
class ParameterRepository extends BaseRepository
{
    /**
     * ParameterRepository constructor
     */
    public function __construct(FunctionParameter $model)
    {
        parent::__construct($model);
    }

    /**
     * 根據 Function ID 取得所有參數
     */
    public function getByFunctionId(int $functionId): Collection
    {
        return $this->model
            ->where('function_id', $functionId)
            ->orderBy('position')
            ->get();
    }

    /**
     * 根據 Function ID 取得必填參數
     */
    public function getRequiredByFunctionId(int $functionId): Collection
    {
        return $this->model
            ->where('function_id', $functionId)
            ->where('is_required', true)
            ->orderBy('position')
            ->get();
    }

    /**
     * 根據 Function ID 取得選填參數
     */
    public function getOptionalByFunctionId(int $functionId): Collection
    {
        return $this->model
            ->where('function_id', $functionId)
            ->where('is_required', false)
            ->orderBy('position')
            ->get();
    }

    /**
     * 根據 Function ID 和參數名稱查找參數
     */
    public function findByFunctionAndName(int $functionId, string $name): ?FunctionParameter
    {
        return $this->model
            ->where('function_id', $functionId)
            ->where('name', $name)
            ->first();
    }

    /**
     * 批次建立參數
     */
    public function createMany(int $functionId, array $parameters): Collection
    {
        $created = collect();

        foreach ($parameters as $index => $parameterData) {
            $parameterData['function_id'] = $functionId;
            $parameterData['position'] = $parameterData['position'] ?? $index;
            
            $created->push($this->create($parameterData));
        }

        return $created;
    }

    /**
     * 更新參數順序
     */
    public function updatePositions(array $positionMap): bool
    {
        foreach ($positionMap as $parameterId => $position) {
            $this->update($parameterId, ['position' => $position]);
        }

        return true;
    }

    /**
     * 刪除 Function 的所有參數
     */
    public function deleteByFunctionId(int $functionId): int
    {
        return $this->model
            ->where('function_id', $functionId)
            ->delete();
    }

    /**
     * 取得參數的驗證規則映射
     */
    public function getValidationRules(int $functionId): array
    {
        $parameters = $this->getByFunctionId($functionId);
        $rules = [];

        foreach ($parameters as $parameter) {
            $rules[$parameter->name] = $parameter->getLaravelValidationRule();
        }

        return $rules;
    }

    /**
     * 檢查參數名稱是否在 Function 中已存在
     */
    public function nameExistsInFunction(int $functionId, string $name, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('function_id', $functionId)
            ->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 取得 Function 的參數映射（API 參數名 => SP 參數名）
     */
    public function getParameterMapping(int $functionId): array
    {
        $parameters = $this->getByFunctionId($functionId);
        $mapping = [];

        foreach ($parameters as $parameter) {
            $mapping[$parameter->name] = $parameter->sp_parameter_name;
        }

        return $mapping;
    }
}

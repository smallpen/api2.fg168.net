<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Function Parameter Model
 * 
 * 代表 API Function 的輸入參數定義，包含驗證規則和資料類型
 */
class FunctionParameter extends Model
{
    use HasFactory;

    protected $table = 'function_parameters';

    protected $fillable = [
        'function_id',
        'name',
        'data_type',
        'is_required',
        'default_value',
        'validation_rules',
        'sp_parameter_name',
        'position',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_rules' => 'array',
        'position' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 支援的資料類型
     */
    public const DATA_TYPES = [
        'string',
        'integer',
        'float',
        'boolean',
        'date',
        'datetime',
        'json',
        'array',
    ];

    /**
     * 取得此參數所屬的 API Function
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ApiFunction::class, 'function_id');
    }

    /**
     * 檢查參數是否為必填
     */
    public function isRequired(): bool
    {
        return $this->is_required;
    }

    /**
     * 檢查參數是否有預設值
     */
    public function hasDefaultValue(): bool
    {
        return !is_null($this->default_value);
    }

    /**
     * 取得驗證規則陣列
     */
    public function getValidationRules(): array
    {
        return $this->validation_rules ?? [];
    }

    /**
     * 取得完整的 Laravel 驗證規則字串
     */
    public function getLaravelValidationRule(): string
    {
        $rules = [];

        // 必填規則
        if ($this->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // 資料類型規則
        $rules[] = $this->getDataTypeValidationRule();

        // 自訂驗證規則
        if (!empty($this->validation_rules)) {
            $rules = array_merge($rules, $this->validation_rules);
        }

        return implode('|', $rules);
    }

    /**
     * 根據資料類型取得對應的驗證規則
     */
    protected function getDataTypeValidationRule(): string
    {
        return match ($this->data_type) {
            'string' => 'string',
            'integer' => 'integer',
            'float' => 'numeric',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'date',
            'json' => 'json',
            'array' => 'array',
            default => 'string',
        };
    }

    /**
     * 轉換值為指定的資料類型
     */
    public function castValue($value)
    {
        if (is_null($value)) {
            return $this->default_value;
        }

        return match ($this->data_type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => (bool) $value,
            'array' => is_array($value) ? $value : json_decode($value, true),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => (string) $value,
        };
    }

    /**
     * 驗證資料類型是否有效
     */
    public static function isValidDataType(string $type): bool
    {
        return in_array($type, self::DATA_TYPES);
    }
}

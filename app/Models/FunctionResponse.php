<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Function Response Model
 * 
 * 代表 API Function 的回應欄位映射，定義如何將 Stored Procedure 結果轉換為 JSON 回應
 */
class FunctionResponse extends Model
{
    use HasFactory;

    protected $table = 'function_responses';

    protected $fillable = [
        'function_id',
        'field_name',
        'sp_column_name',
        'data_type',
        'transform_rule',
    ];

    protected $casts = [
        'transform_rule' => 'array',
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
     * 取得此回應欄位所屬的 API Function
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ApiFunction::class, 'function_id');
    }

    /**
     * 轉換資料庫欄位值為指定格式
     */
    public function transformValue($value)
    {
        // 如果值為 null，直接返回
        if (is_null($value)) {
            return null;
        }

        // 先進行資料類型轉換
        $value = $this->castToDataType($value);

        // 如果有自訂轉換規則，套用轉換
        if (!empty($this->transform_rule)) {
            $value = $this->applyTransformRule($value);
        }

        return $value;
    }

    /**
     * 將值轉換為指定的資料類型
     */
    protected function castToDataType($value)
    {
        return match ($this->data_type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => (bool) $value,
            'date' => $this->formatDate($value, 'Y-m-d'),
            'datetime' => $this->formatDate($value, 'Y-m-d H:i:s'),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'array' => is_array($value) ? $value : json_decode($value, true),
            default => (string) $value,
        };
    }

    /**
     * 格式化日期
     */
    protected function formatDate($value, string $format): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if ($value instanceof \DateTime) {
                return $value->format($format);
            }
            
            $date = new \DateTime($value);
            return $date->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 套用自訂轉換規則
     */
    protected function applyTransformRule($value)
    {
        if (empty($this->transform_rule)) {
            return $value;
        }

        // 支援的轉換規則範例：
        // ['type' => 'uppercase'] - 轉換為大寫
        // ['type' => 'lowercase'] - 轉換為小寫
        // ['type' => 'prefix', 'value' => 'PREFIX_'] - 加上前綴
        // ['type' => 'suffix', 'value' => '_SUFFIX'] - 加上後綴
        // ['type' => 'multiply', 'value' => 100] - 乘以指定值
        // ['type' => 'divide', 'value' => 100] - 除以指定值

        $type = $this->transform_rule['type'] ?? null;

        return match ($type) {
            'uppercase' => is_string($value) ? strtoupper($value) : $value,
            'lowercase' => is_string($value) ? strtolower($value) : $value,
            'prefix' => ($this->transform_rule['value'] ?? '') . $value,
            'suffix' => $value . ($this->transform_rule['value'] ?? ''),
            'multiply' => is_numeric($value) ? $value * ($this->transform_rule['value'] ?? 1) : $value,
            'divide' => is_numeric($value) && ($this->transform_rule['value'] ?? 0) != 0 
                ? $value / $this->transform_rule['value'] 
                : $value,
            default => $value,
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

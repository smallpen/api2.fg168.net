<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API Function Model
 * 
 * 代表動態配置的 API 端點，包含參數定義、Stored Procedure 映射和回應格式
 */
class ApiFunction extends Model
{
    use HasFactory;

    protected $table = 'api_functions';

    protected $fillable = [
        'name',
        'identifier',
        'description',
        'stored_procedure',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 取得此 Function 的所有參數定義
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(FunctionParameter::class, 'function_id')
            ->orderBy('position');
    }

    /**
     * 取得此 Function 的回應欄位映射
     */
    public function responses(): HasMany
    {
        return $this->hasMany(FunctionResponse::class, 'function_id');
    }

    /**
     * 取得此 Function 的錯誤映射規則
     */
    public function errorMappings(): HasMany
    {
        return $this->hasMany(FunctionErrorMapping::class, 'function_id');
    }

    /**
     * 取得此 Function 的權限設定
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(FunctionPermission::class, 'function_id');
    }

    /**
     * 取得建立此 Function 的使用者
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 檢查 Function 是否啟用
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * 啟用 Function
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * 停用 Function
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * 取得必填參數列表
     */
    public function getRequiredParameters()
    {
        return $this->parameters()->where('is_required', true)->get();
    }

    /**
     * 取得選填參數列表
     */
    public function getOptionalParameters()
    {
        return $this->parameters()->where('is_required', false)->get();
    }
}

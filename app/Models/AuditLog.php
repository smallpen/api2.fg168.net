<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 審計日誌模型
 * 
 * 記錄系統配置變更和重要操作
 */
class AuditLog extends Model
{
    /**
     * 資料表名稱
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * 可批量賦值的屬性
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'old_value',
        'new_value',
    ];

    /**
     * 屬性類型轉換
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * 不使用 updated_at 欄位
     *
     * @var bool
     */
    public const UPDATED_AT = null;

    /**
     * 取得關聯的使用者
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

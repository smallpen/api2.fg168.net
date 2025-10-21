<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 安全日誌模型
 * 
 * 記錄系統安全相關事件，如驗證失敗、權限拒絕等
 */
class SecurityLog extends Model
{
    /**
     * 資料表名稱
     *
     * @var string
     */
    protected $table = 'security_logs';

    /**
     * 可批量賦值的屬性
     *
     * @var array<string>
     */
    protected $fillable = [
        'event_type',
        'client_id',
        'ip_address',
        'details',
    ];

    /**
     * 屬性類型轉換
     *
     * @var array<string, string>
     */
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * 不使用 updated_at 欄位
     *
     * @var bool
     */
    public const UPDATED_AT = null;

    /**
     * 取得關聯的 API 客戶端
     *
     * @return BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'client_id');
    }
}

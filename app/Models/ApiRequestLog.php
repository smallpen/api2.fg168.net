<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API 請求日誌模型
 * 
 * 記錄所有通過 API Gateway 的請求資訊
 */
class ApiRequestLog extends Model
{
    /**
     * 資料表名稱
     *
     * @var string
     */
    protected $table = 'api_request_logs';

    /**
     * 可批量賦值的屬性
     *
     * @var array<string>
     */
    protected $fillable = [
        'client_id',
        'function_id',
        'request_data',
        'response_data',
        'http_status',
        'execution_time',
        'ip_address',
        'user_agent',
    ];

    /**
     * 屬性類型轉換
     *
     * @var array<string, string>
     */
    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'http_status' => 'integer',
        'execution_time' => 'float',
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

    /**
     * 取得關聯的 API Function
     *
     * @return BelongsTo
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ApiFunction::class, 'function_id');
    }
}

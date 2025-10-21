<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 錯誤日誌模型
 * 
 * 記錄系統運行過程中發生的錯誤和例外
 */
class ErrorLog extends Model
{
    /**
     * 資料表名稱
     *
     * @var string
     */
    protected $table = 'error_logs';

    /**
     * 可批量賦值的屬性
     *
     * @var array<string>
     */
    protected $fillable = [
        'type',
        'message',
        'stack_trace',
        'context',
    ];

    /**
     * 屬性類型轉換
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * 不使用 updated_at 欄位
     *
     * @var bool
     */
    public const UPDATED_AT = null;
}

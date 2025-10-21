<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Function Permission Model
 * 
 * 代表特定客戶端對特定 API Function 的存取權限
 */
class FunctionPermission extends Model
{
    use HasFactory;

    protected $table = 'function_permissions';

    protected $fillable = [
        'function_id',
        'client_id',
        'allowed',
    ];

    protected $casts = [
        'allowed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 取得此權限所屬的 API Function
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ApiFunction::class, 'function_id');
    }

    /**
     * 取得此權限所屬的客戶端
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'client_id');
    }

    /**
     * 檢查是否允許存取
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * 允許存取
     */
    public function allow(): bool
    {
        return $this->update(['allowed' => true]);
    }

    /**
     * 拒絕存取
     */
    public function deny(): bool
    {
        return $this->update(['allowed' => false]);
    }

    /**
     * 查找或建立權限
     */
    public static function findOrCreate(int $functionId, int $clientId, bool $allowed = true): self
    {
        $permission = self::where('function_id', $functionId)
            ->where('client_id', $clientId)
            ->first();

        if (!$permission) {
            $permission = self::create([
                'function_id' => $functionId,
                'client_id' => $clientId,
                'allowed' => $allowed,
            ]);
        }

        return $permission;
    }

    /**
     * 檢查客戶端是否有權限存取 Function
     */
    public static function checkPermission(int $functionId, int $clientId): bool
    {
        $permission = self::where('function_id', $functionId)
            ->where('client_id', $clientId)
            ->first();

        return $permission ? $permission->allowed : false;
    }
}

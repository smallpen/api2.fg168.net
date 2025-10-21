<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * API Token Model
 * 
 * 代表 API 客戶端的驗證 Token
 */
class ApiToken extends Model
{
    use HasFactory;

    protected $table = 'api_tokens';

    protected $fillable = [
        'client_id',
        'token',
        'type',
        'expires_at',
        'last_used_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Token 類型
     */
    public const TYPE_ACCESS = 'access';
    public const TYPE_REFRESH = 'refresh';

    /**
     * 預設 Token 有效期（小時）
     */
    public const DEFAULT_ACCESS_TOKEN_LIFETIME = 24;
    public const DEFAULT_REFRESH_TOKEN_LIFETIME = 720; // 30 天

    /**
     * 取得此 Token 所屬的客戶端
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'client_id');
    }

    /**
     * 檢查 Token 是否過期
     */
    public function isExpired(): bool
    {
        if (is_null($this->expires_at)) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * 檢查 Token 是否有效
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && $this->client && $this->client->isActive();
    }

    /**
     * 更新最後使用時間
     */
    public function updateLastUsed(): bool
    {
        return $this->update(['last_used_at' => now()]);
    }

    /**
     * 撤銷 Token（設定為已過期）
     */
    public function revoke(): bool
    {
        return $this->update(['expires_at' => now()]);
    }

    /**
     * 生成新的 Token 字串
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * 建立新的 Access Token
     */
    public static function createAccessToken(int $clientId, ?int $lifetimeHours = null): self
    {
        $lifetime = $lifetimeHours ?? self::DEFAULT_ACCESS_TOKEN_LIFETIME;

        return self::create([
            'client_id' => $clientId,
            'token' => self::generateToken(),
            'type' => self::TYPE_ACCESS,
            'expires_at' => now()->addHours($lifetime),
        ]);
    }

    /**
     * 建立新的 Refresh Token
     */
    public static function createRefreshToken(int $clientId, ?int $lifetimeHours = null): self
    {
        $lifetime = $lifetimeHours ?? self::DEFAULT_REFRESH_TOKEN_LIFETIME;

        return self::create([
            'client_id' => $clientId,
            'token' => self::generateToken(),
            'type' => self::TYPE_REFRESH,
            'expires_at' => now()->addHours($lifetime),
        ]);
    }

    /**
     * 根據 Token 字串查找有效的 Token
     */
    public static function findValidToken(string $token): ?self
    {
        $apiToken = self::where('token', $token)->first();

        if (!$apiToken || !$apiToken->isValid()) {
            return null;
        }

        return $apiToken;
    }

    /**
     * 清理過期的 Token
     */
    public static function cleanupExpiredTokens(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }
}

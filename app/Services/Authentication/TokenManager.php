<?php

namespace App\Services\Authentication;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Services\Authentication\Validators\TokenValidator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Token 管理器
 * 
 * 負責 Token 的生成、驗證、撤銷和清理
 */
class TokenManager
{
    /**
     * Token 驗證器
     */
    protected TokenValidator $tokenValidator;

    /**
     * 建構函數
     */
    public function __construct(TokenValidator $tokenValidator)
    {
        $this->tokenValidator = $tokenValidator;
    }

    /**
     * 為客戶端生成 Access Token
     * 
     * @param ApiClient $client 客戶端
     * @param int|null $lifetimeHours Token 有效期（小時）
     * @param string $type Token 類型（'jwt' 或 'database'）
     * @return array Token 資訊
     */
    public function generateAccessToken(
        ApiClient $client,
        ?int $lifetimeHours = null,
        string $type = 'database'
    ): array {
        $lifetime = $lifetimeHours ?? ApiToken::DEFAULT_ACCESS_TOKEN_LIFETIME;

        if ($type === 'jwt') {
            // 生成 JWT Token
            $token = $this->tokenValidator->generateJWT($client, $lifetime);
            $expiresAt = now()->addHours($lifetime);

            return [
                'token' => $token,
                'type' => 'Bearer',
                'expires_in' => $lifetime * 3600,
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        }

        // 生成資料庫 Token
        $apiToken = ApiToken::createAccessToken($client->id, $lifetime);

        return [
            'token' => $apiToken->token,
            'type' => 'Bearer',
            'expires_in' => $lifetime * 3600,
            'expires_at' => $apiToken->expires_at->toIso8601String(),
        ];
    }

    /**
     * 為客戶端生成 Refresh Token
     * 
     * @param ApiClient $client 客戶端
     * @param int|null $lifetimeHours Token 有效期（小時）
     * @return array Token 資訊
     */
    public function generateRefreshToken(
        ApiClient $client,
        ?int $lifetimeHours = null
    ): array {
        $lifetime = $lifetimeHours ?? ApiToken::DEFAULT_REFRESH_TOKEN_LIFETIME;

        $apiToken = ApiToken::createRefreshToken($client->id, $lifetime);

        return [
            'token' => $apiToken->token,
            'type' => 'Refresh',
            'expires_in' => $lifetime * 3600,
            'expires_at' => $apiToken->expires_at->toIso8601String(),
        ];
    }

    /**
     * 生成 Token 對（Access Token 和 Refresh Token）
     * 
     * @param ApiClient $client 客戶端
     * @param int|null $accessTokenLifetime Access Token 有效期（小時）
     * @param int|null $refreshTokenLifetime Refresh Token 有效期（小時）
     * @param string $type Token 類型（'jwt' 或 'database'）
     * @return array Token 對資訊
     */
    public function generateTokenPair(
        ApiClient $client,
        ?int $accessTokenLifetime = null,
        ?int $refreshTokenLifetime = null,
        string $type = 'database'
    ): array {
        $accessToken = $this->generateAccessToken($client, $accessTokenLifetime, $type);
        $refreshToken = $this->generateRefreshToken($client, $refreshTokenLifetime);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * 使用 Refresh Token 刷新 Access Token
     * 
     * @param string $refreshToken Refresh Token 字串
     * @param string $type 新 Token 類型（'jwt' 或 'database'）
     * @return array 新的 Token 資訊
     * @throws AuthenticationException Token 無效時拋出
     */
    public function refreshAccessToken(string $refreshToken, string $type = 'database'): array
    {
        // 驗證 Refresh Token
        $apiToken = ApiToken::findValidToken($refreshToken);

        if (!$apiToken || $apiToken->type !== ApiToken::TYPE_REFRESH) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                'Refresh Token 無效或已過期'
            );
        }

        // 取得客戶端
        $client = $apiToken->client;

        if (!$client || !$client->isActive()) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                '客戶端不存在或已停用'
            );
        }

        // 生成新的 Access Token
        $accessToken = $this->generateAccessToken($client, null, $type);

        // 更新 Refresh Token 的最後使用時間
        $apiToken->updateLastUsed();

        return $accessToken;
    }

    /**
     * 驗證 Token 是否有效
     * 
     * @param string $token Token 字串
     * @return bool Token 是否有效
     */
    public function validateToken(string $token): bool
    {
        try {
            $this->tokenValidator->validateToken($token);
            return true;
        } catch (AuthenticationException $e) {
            return false;
        }
    }

    /**
     * 檢查 Token 是否過期
     * 
     * @param string $token Token 字串
     * @return bool Token 是否過期
     */
    public function isTokenExpired(string $token): bool
    {
        // 嘗試作為資料庫 Token 檢查
        $apiToken = ApiToken::where('token', $token)->first();

        if ($apiToken) {
            return $apiToken->isExpired();
        }

        // 嘗試作為 JWT 檢查
        $decoded = $this->tokenValidator->decodeJWT($token);

        if ($decoded && isset($decoded->exp)) {
            return $decoded->exp < time();
        }

        return true;
    }

    /**
     * 撤銷單個 Token
     * 
     * @param string $token Token 字串
     * @return bool 是否成功撤銷
     */
    public function revokeToken(string $token): bool
    {
        $apiToken = ApiToken::where('token', $token)->first();

        if (!$apiToken) {
            return false;
        }

        return $apiToken->revoke();
    }

    /**
     * 撤銷客戶端的所有 Token
     * 
     * @param int $clientId 客戶端 ID
     * @param string|null $type Token 類型（可選）
     * @return int 撤銷的 Token 數量
     */
    public function revokeClientTokens(int $clientId, ?string $type = null): int
    {
        $query = ApiToken::where('client_id', $clientId)
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now());

        if ($type) {
            $query->where('type', $type);
        }

        return $query->update(['expires_at' => now()]);
    }

    /**
     * 撤銷客戶端的所有 Access Token
     * 
     * @param int $clientId 客戶端 ID
     * @return int 撤銷的 Token 數量
     */
    public function revokeClientAccessTokens(int $clientId): int
    {
        return $this->revokeClientTokens($clientId, ApiToken::TYPE_ACCESS);
    }

    /**
     * 撤銷客戶端的所有 Refresh Token
     * 
     * @param int $clientId 客戶端 ID
     * @return int 撤銷的 Token 數量
     */
    public function revokeClientRefreshTokens(int $clientId): int
    {
        return $this->revokeClientTokens($clientId, ApiToken::TYPE_REFRESH);
    }

    /**
     * 清理過期的 Token
     * 
     * @param int|null $daysOld 清理多少天前過期的 Token（預設為 7 天）
     * @return int 清理的 Token 數量
     */
    public function cleanupExpiredTokens(?int $daysOld = 7): int
    {
        $cutoffDate = now()->subDays($daysOld);

        return ApiToken::where('expires_at', '<', $cutoffDate)->delete();
    }

    /**
     * 取得客戶端的所有有效 Token
     * 
     * @param int $clientId 客戶端 ID
     * @param string|null $type Token 類型（可選）
     * @return \Illuminate\Database\Eloquent\Collection Token 集合
     */
    public function getClientTokens(int $clientId, ?string $type = null)
    {
        $query = ApiToken::where('client_id', $clientId)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * 取得 Token 的詳細資訊
     * 
     * @param string $token Token 字串
     * @return array|null Token 資訊
     */
    public function getTokenInfo(string $token): ?array
    {
        // 嘗試作為資料庫 Token
        $apiToken = ApiToken::where('token', $token)->first();

        if ($apiToken) {
            return [
                'type' => 'database',
                'token_type' => $apiToken->type,
                'client_id' => $apiToken->client_id,
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
                'is_expired' => $apiToken->isExpired(),
                'last_used_at' => $apiToken->last_used_at?->toIso8601String(),
                'created_at' => $apiToken->created_at->toIso8601String(),
            ];
        }

        // 嘗試作為 JWT
        $decoded = $this->tokenValidator->decodeJWT($token);

        if ($decoded) {
            return [
                'type' => 'jwt',
                'client_id' => $decoded->client_id ?? null,
                'expires_at' => isset($decoded->exp) ? Carbon::createFromTimestamp($decoded->exp)->toIso8601String() : null,
                'is_expired' => isset($decoded->exp) ? $decoded->exp < time() : false,
                'issued_at' => isset($decoded->iat) ? Carbon::createFromTimestamp($decoded->iat)->toIso8601String() : null,
            ];
        }

        return null;
    }

    /**
     * 取得 Token 統計資訊
     * 
     * @param int|null $clientId 客戶端 ID（可選）
     * @return array 統計資訊
     */
    public function getTokenStatistics(?int $clientId = null): array
    {
        $query = ApiToken::query();

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $total = $query->count();
        $active = (clone $query)->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })->count();
        $expired = (clone $query)->where('expires_at', '<=', now())->count();

        $byType = (clone $query)->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'by_type' => $byType,
        ];
    }
}

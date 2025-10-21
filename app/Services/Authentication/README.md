# 驗證服務 (Authentication Service)

本目錄包含 Dynamic API Manager 的驗證服務實作，支援多種驗證方式。

## 功能概述

### 支援的驗證方式

1. **Bearer Token (JWT)**
   - 支援 JWT 標準
   - 可自訂過期時間
   - 無狀態驗證

2. **API Key**
   - 簡單的 API Key 驗證
   - 支援從標頭或查詢參數取得
   - 可選的 Secret 雙重驗證

3. **OAuth 2.0**
   - 支援多個 OAuth 提供者（Google、GitHub、Microsoft）
   - 自動建立客戶端
   - 標準 OAuth 2.0 流程

## 核心類別

### AuthenticationManager

驗證管理器，負責協調不同的驗證方式。

```php
use App\Services\Authentication\AuthenticationManager;

// 自動偵測並驗證請求
$client = $authManager->authenticate($request);

// 檢查是否已驗證
$isAuthenticated = $authManager->check($request);

// 取得已驗證的客戶端
$client = $authManager->getClient($request);
```

### TokenManager

Token 管理器，負責 Token 的生成、驗證和撤銷。

```php
use App\Services\Authentication\TokenManager;

// 生成 Access Token
$tokenInfo = $tokenManager->generateAccessToken($client);

// 生成 Token 對（Access + Refresh）
$tokens = $tokenManager->generateTokenPair($client);

// 刷新 Access Token
$newToken = $tokenManager->refreshAccessToken($refreshToken);

// 撤銷 Token
$tokenManager->revokeToken($token);

// 清理過期 Token
$count = $tokenManager->cleanupExpiredTokens();
```

### TokenValidator

Token 驗證器，支援 JWT 和資料庫 Token。

```php
use App\Services\Authentication\Validators\TokenValidator;

// 驗證 Token
$client = $tokenValidator->validateToken($token);

// 生成 JWT
$jwt = $tokenValidator->generateJWT($client, 24);

// 解碼 JWT
$decoded = $tokenValidator->decodeJWT($token);
```

### ApiKeyValidator

API Key 驗證器。

```php
use App\Services\Authentication\Validators\ApiKeyValidator;

// 驗證 API Key
$client = $apiKeyValidator->validateApiKey($apiKey);

// 驗證 API Key 和 Secret
$client = $apiKeyValidator->validateApiKeyWithSecret($apiKey, $secret);

// 生成新的 API Key
$apiKey = $apiKeyValidator->generateApiKey();
```

### OAuthProvider

OAuth 2.0 提供者。

```php
use App\Services\Authentication\Validators\OAuthProvider;

// 驗證 OAuth Token
$client = $oauthProvider->validateOAuthToken($token);

// 取得授權 URL
$url = $oauthProvider->getAuthorizationUrl('google', $redirectUri, ['email', 'profile']);

// 交換授權碼為 Token
$tokenInfo = $oauthProvider->exchangeCodeForToken('google', $code, $redirectUri);
```

## Middleware 使用

### AuthenticateApi

API 驗證 Middleware，自動驗證所有 API 請求。

```php
// 在路由中使用
Route::middleware('auth.api')->group(function () {
    Route::post('/api/v1/execute', [ApiGatewayController::class, 'execute']);
});

// 在控制器中取得已驗證的客戶端
public function execute(Request $request)
{
    $client = $request->attributes->get('api_client');
    // 或使用輔助函數
    $client = auth_client();
}
```

## 輔助函數

系統提供了便利的全域輔助函數：

```php
// 取得已驗證的客戶端
$client = auth_client();

// 取得客戶端 ID
$clientId = auth_client_id();

// 檢查是否已驗證
if (is_authenticated()) {
    // 已驗證
}
```

## 配置

### OAuth 配置

在 `config/oauth.php` 中配置 OAuth 提供者：

```php
'providers' => [
    'google' => [
        'client_id' => env('OAUTH_GOOGLE_CLIENT_ID'),
        'client_secret' => env('OAUTH_GOOGLE_CLIENT_SECRET'),
        'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
        'default_scopes' => ['openid', 'profile', 'email'],
        'timeout' => 10,
    ],
],
```

### 環境變數

在 `.env` 檔案中設定：

```env
# Google OAuth
OAUTH_GOOGLE_CLIENT_ID=your_client_id
OAUTH_GOOGLE_CLIENT_SECRET=your_client_secret

# GitHub OAuth
OAUTH_GITHUB_CLIENT_ID=your_client_id
OAUTH_GITHUB_CLIENT_SECRET=your_client_secret

# Microsoft OAuth
OAUTH_MICROSOFT_CLIENT_ID=your_client_id
OAUTH_MICROSOFT_CLIENT_SECRET=your_client_secret
```

## 錯誤處理

所有驗證失敗都會拋出 `AuthenticationException`：

```php
try {
    $client = $authManager->authenticate($request);
} catch (AuthenticationException $e) {
    // 錯誤代碼
    $errorCode = $e->getErrorCode();
    
    // 錯誤訊息
    $message = $e->getMessage();
    
    // HTTP 狀態碼
    $statusCode = $e->getCode();
    
    // 轉換為陣列
    $error = $e->toArray();
}
```

### 常見錯誤代碼

- `AUTHENTICATION_REQUIRED`: 缺少驗證憑證
- `INVALID_CREDENTIALS`: 驗證憑證無效
- `TOKEN_EXPIRED`: Token 已過期

## 安全建議

1. **使用 HTTPS**: 所有 API 請求都應該通過 HTTPS
2. **定期輪換 Secret**: 定期更新 API Key 和 Secret
3. **設定合理的過期時間**: Access Token 建議 1-24 小時，Refresh Token 建議 7-30 天
4. **記錄安全事件**: 所有驗證失敗都會自動記錄到日誌
5. **限制 Token 使用**: 實施 Rate Limiting 防止濫用

## 測試

```bash
# 執行驗證服務測試
php artisan test --filter=Authentication
```

## 依賴套件

- `firebase/php-jwt`: JWT 處理
- `guzzlehttp/guzzle`: HTTP 客戶端（OAuth）
- `laravel/framework`: Laravel 框架

## 相關文件

- [Requirements Document](../../../.kiro/specs/dynamic-api-manager/requirements.md)
- [Design Document](../../../.kiro/specs/dynamic-api-manager/design.md)
- [API Models](../../Models/)

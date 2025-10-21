# API 驗證方式說明

## 概述

Dynamic API Manager 支援多種驗證方式，以滿足不同的安全需求和使用場景。所有 API 請求都必須通過驗證才能執行。

## 支援的驗證方式

### 1. Bearer Token (JWT)

JWT (JSON Web Token) 是一種基於標準的 Token 驗證方式，適合需要短期存取權限的場景。

#### 特點

- Token 包含過期時間
- 支援自動續期
- 可攜帶使用者資訊
- 適合前端應用程式

#### 使用方式

在請求標頭中加入 `Authorization` 欄位：

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

#### 完整請求範例

```bash
curl -X POST https://api.example.com/api/v1/execute \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  -H "Content-Type: application/json" \
  -d '{
    "function": "user.profile",
    "params": {}
  }'
```

#### Token 結構

JWT Token 包含三個部分，以點號分隔：

```
header.payload.signature
```

**Header (標頭)**:
```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

**Payload (負載)**:
```json
{
  "client_id": 123,
  "client_name": "MyApp",
  "exp": 1729512600,
  "iat": 1729509000
}
```

**Signature (簽章)**: 使用密鑰對 header 和 payload 進行簽章

#### 取得 Token

通過 Admin UI 為客戶端生成 Token，或使用 Token 生成 API：

```bash
curl -X POST https://api.example.com/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "your_client_id",
    "client_secret": "your_client_secret"
  }'
```

回應：

```json
{
  "success": true,
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "expires_at": "2025-10-21T11:30:00Z"
  }
}
```

#### Token 續期

當 Token 即將過期時，可以使用 Refresh Token 取得新的 Access Token：

```bash
curl -X POST https://api.example.com/api/v1/auth/refresh \
  -H "Authorization: Bearer your_current_token" \
  -H "Content-Type: application/json"
```

### 2. API Key

API Key 是一種簡單的靜態驗證方式，適合伺服器對伺服器的通訊。

#### 特點

- 長期有效
- 簡單易用
- 適合後端服務
- 需要妥善保管

#### 使用方式

在請求標頭中加入 `X-API-Key` 欄位：

```http
X-API-Key: ak_live_1234567890abcdef
```

#### 完整請求範例

```bash
curl -X POST https://api.example.com/api/v1/execute \
  -H "X-API-Key: ak_live_1234567890abcdef" \
  -H "Content-Type: application/json" \
  -d '{
    "function": "order.create",
    "params": {
      "customer_id": 456,
      "items": [
        {"product_id": 789, "quantity": 2}
      ]
    }
  }'
```

#### 取得 API Key

通過 Admin UI 創建新的 API 客戶端時，系統會自動生成 API Key。

API Key 格式：
- 開發環境：`ak_test_` 開頭
- 生產環境：`ak_live_` 開頭

#### 安全建議

1. **不要在客戶端程式碼中使用**: API Key 應該只在伺服器端使用
2. **定期輪換**: 建議定期更換 API Key
3. **使用環境變數**: 將 API Key 儲存在環境變數中，不要寫死在程式碼裡
4. **限制權限**: 為每個 API Key 設定最小必要權限

### 3. OAuth 2.0

OAuth 2.0 是一種授權框架，適合需要第三方授權的場景。

#### 特點

- 標準化協定
- 支援第三方授權
- 細粒度權限控制
- 適合多租戶應用

#### 支援的授權流程

##### Authorization Code Flow (授權碼流程)

適合有後端伺服器的 Web 應用程式。

**步驟 1: 取得授權碼**

將使用者導向授權端點：

```
https://api.example.com/oauth/authorize?
  response_type=code&
  client_id=your_client_id&
  redirect_uri=https://yourapp.com/callback&
  scope=read write&
  state=random_state_string
```

**步驟 2: 使用授權碼換取 Token**

```bash
curl -X POST https://api.example.com/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code" \
  -d "code=received_authorization_code" \
  -d "client_id=your_client_id" \
  -d "client_secret=your_client_secret" \
  -d "redirect_uri=https://yourapp.com/callback"
```

回應：

```json
{
  "access_token": "oauth_token_here",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "refresh_token_here",
  "scope": "read write"
}
```

**步驟 3: 使用 Access Token 呼叫 API**

```bash
curl -X POST https://api.example.com/api/v1/execute \
  -H "Authorization: Bearer oauth_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "function": "data.read",
    "params": {}
  }'
```

##### Client Credentials Flow (客戶端憑證流程)

適合機器對機器的通訊，不涉及使用者授權。

```bash
curl -X POST https://api.example.com/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials" \
  -d "client_id=your_client_id" \
  -d "client_secret=your_client_secret" \
  -d "scope=read"
```

#### 使用 OAuth Token

取得 Access Token 後，在請求標頭中使用：

```http
Authorization: Bearer oauth_access_token
```

或使用 OAuth 專用格式：

```http
Authorization: OAuth oauth_access_token
```

#### Scope (權限範圍)

OAuth 2.0 支援細粒度的權限控制：

- `read`: 讀取資料
- `write`: 寫入資料
- `delete`: 刪除資料
- `admin`: 管理權限

可以組合多個 scope：

```
scope=read write
```

## 驗證錯誤處理

### 常見驗證錯誤

#### 401 Unauthorized - 缺少驗證憑證

```json
{
  "success": false,
  "error": {
    "code": "AUTHENTICATION_REQUIRED",
    "message": "此請求需要驗證，請提供有效的驗證憑證"
  }
}
```

**解決方式**: 在請求標頭中加入正確的驗證憑證

#### 401 Unauthorized - 驗證憑證無效

```json
{
  "success": false,
  "error": {
    "code": "INVALID_CREDENTIALS",
    "message": "提供的驗證憑證無效"
  }
}
```

**解決方式**: 檢查憑證是否正確，或重新生成新的憑證

#### 401 Unauthorized - Token 已過期

```json
{
  "success": false,
  "error": {
    "code": "TOKEN_EXPIRED",
    "message": "Token 已過期，請重新取得新的 Token",
    "details": {
      "expired_at": "2025-10-21T10:30:00Z"
    }
  }
}
```

**解決方式**: 使用 Refresh Token 取得新的 Access Token，或重新登入

## 安全最佳實踐

### 1. 使用 HTTPS

所有 API 請求都必須通過 HTTPS 加密傳輸，以防止憑證被竊取。

### 2. 妥善保管憑證

- **不要在客戶端程式碼中暴露**: API Key 和 Secret 不應該出現在前端程式碼中
- **使用環境變數**: 將憑證儲存在環境變數或安全的配置管理系統中
- **定期輪換**: 定期更換 API Key 和 Secret
- **限制權限**: 為每個憑證設定最小必要權限

### 3. 實作 Token 過期處理

```javascript
// JavaScript 範例
async function callApi(functionName, params) {
  try {
    const response = await fetch('/api/v1/execute', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ function: functionName, params })
    });
    
    if (response.status === 401) {
      // Token 過期，嘗試續期
      await refreshToken();
      // 重試請求
      return callApi(functionName, params);
    }
    
    return await response.json();
  } catch (error) {
    console.error('API 呼叫失敗:', error);
    throw error;
  }
}
```

### 4. 監控異常活動

- 記錄所有驗證失敗的嘗試
- 設定告警機制偵測異常登入模式
- 實施帳號鎖定機制防止暴力破解

### 5. IP 白名單

對於敏感的 API 操作，可以設定 IP 白名單限制存取來源。

## 測試驗證

### 使用 Postman 測試

1. 建立新的請求
2. 設定 URL: `https://api.example.com/api/v1/execute`
3. 選擇 POST 方法
4. 在 Headers 標籤中加入驗證標頭
5. 在 Body 標籤中選擇 raw 和 JSON 格式
6. 輸入請求內容並發送

### 使用 curl 測試

```bash
# 測試 Bearer Token
curl -v -X POST https://api.example.com/api/v1/execute \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{"function": "test.ping", "params": {}}'

# 測試 API Key
curl -v -X POST https://api.example.com/api/v1/execute \
  -H "X-API-Key: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"function": "test.ping", "params": {}}'
```

使用 `-v` 參數可以查看詳細的請求和回應資訊，包括標頭。

## 常見問題

### Q: 可以同時使用多種驗證方式嗎？

A: 不可以。每個請求只能使用一種驗證方式。如果同時提供多種憑證，系統會按照以下優先順序處理：Bearer Token > API Key > OAuth。

### Q: Token 的有效期限是多久？

A: 預設的 Access Token 有效期限為 1 小時，Refresh Token 有效期限為 30 天。管理員可以在 Admin UI 中為不同的客戶端設定不同的有效期限。

### Q: 如何撤銷已發出的 Token？

A: 管理員可以在 Admin UI 的客戶端管理頁面中撤銷特定客戶端的所有 Token，或撤銷單一 Token。撤銷後，該 Token 立即失效。

### Q: API Key 遺失或洩漏怎麼辦？

A: 立即在 Admin UI 中撤銷該 API Key，並生成新的 API Key。同時檢查日誌確認是否有異常使用記錄。

### Q: 是否支援雙因素驗證 (2FA)？

A: 目前系統支援在 Admin UI 登入時使用雙因素驗證，但 API 呼叫本身不需要 2FA。建議使用短期 Token 並實施 IP 白名單來提升安全性。

# API Gateway 使用指南

## 概述

API Gateway 是 Dynamic API Manager 的核心元件，提供統一的 API 入口點。所有動態配置的 API Function 都通過此端點執行，無需為每個功能建立獨立的路由。

## 快速開始

### 基本使用流程

1. 取得 API 憑證（API Key 或 Token）
2. 確認要呼叫的 API Function 識別碼
3. 準備請求參數
4. 發送 POST 請求到 API Gateway
5. 處理回應結果

### 第一個 API 請求

```bash
curl -X POST https://api.example.com/api/v1/execute \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "function": "test.ping",
    "params": {}
  }'
```

預期回應：

```json
{
  "success": true,
  "data": {
    "message": "pong",
    "timestamp": "2025-10-21T10:30:00Z"
  },
  "meta": {
    "request_id": "req_1729509000_abc123",
    "execution_time": 0.012
  }
}
```

## 端點資訊

- **URL**: `/api/v1/execute`
- **方法**: `POST`
- **Content-Type**: `application/json`
- **驗證**: 必須（Bearer Token、API Key 或 OAuth 2.0）

## 驗證方式

API Gateway 支援三種驗證方式。詳細的驗證說明請參閱 [API 驗證方式說明](authentication.md)。

### 1. Bearer Token (JWT)

適合需要短期存取權限的場景，如前端應用程式。

```http
POST /api/v1/execute
Authorization: Bearer {your_jwt_token}
Content-Type: application/json
```

### 2. API Key

適合伺服器對伺服器的通訊，長期有效。

```http
POST /api/v1/execute
X-API-Key: {your_api_key}
Content-Type: application/json
```

### 3. OAuth 2.0

適合需要第三方授權的場景。

```http
POST /api/v1/execute
Authorization: Bearer {your_oauth_token}
Content-Type: application/json
```

> **詳細資訊**: 關於各種驗證方式的完整說明、取得憑證的方法和安全最佳實踐，請參閱 [authentication.md](authentication.md)

## 請求格式

### 基本請求結構

```json
{
  "function": "function.identifier",
  "params": {
    "param1": "value1",
    "param2": "value2"
  }
}
```

### 欄位說明

- `function` (必填): API Function 的唯一識別碼
- `params` (選填): 傳遞給 Function 的參數物件

## 回應格式

### 成功回應

```json
{
  "success": true,
  "data": {
    // Function 執行結果
  },
  "meta": {
    "request_id": "req_abc123...",
    "execution_time": 0.045,
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

### 錯誤回應

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "錯誤訊息",
    "details": {
      // 額外的錯誤詳情（如驗證錯誤）
    }
  },
  "meta": {
    "request_id": "req_abc123...",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

## 錯誤處理

當 API 請求失敗時，會返回包含錯誤資訊的 JSON 回應。

### 常見錯誤代碼

| 錯誤代碼 | HTTP 狀態碼 | 說明 |
|---------|-----------|------|
| `AUTHENTICATION_REQUIRED` | 401 | 缺少驗證憑證 |
| `INVALID_CREDENTIALS` | 401 | 驗證憑證無效 |
| `TOKEN_EXPIRED` | 401 | Token 已過期 |
| `PERMISSION_DENIED` | 403 | 權限不足 |
| `FUNCTION_NOT_FOUND` | 404 | API Function 不存在 |
| `FUNCTION_DISABLED` | 403 | API Function 已停用 |
| `VALIDATION_ERROR` | 400 | 參數驗證失敗 |
| `RATE_LIMIT_EXCEEDED` | 429 | 超過請求頻率限制 |
| `STORED_PROCEDURE_ERROR` | 500 | Stored Procedure 執行錯誤 |
| `INTERNAL_ERROR` | 500 | 內部伺服器錯誤 |

> **完整錯誤碼參考**: 關於所有錯誤碼的詳細說明、範例和處理建議，請參閱 [error-codes.md](error-codes.md)

## 速率限制

API Gateway 實施速率限制以保護系統資源。回應標頭會包含速率限制資訊：

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1729512600
```

當超過速率限制時，會返回 429 狀態碼：

```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "超過請求頻率限制，請稍後再試",
    "details": {
      "max_attempts": 60,
      "retry_after": 30
    }
  }
}
```

## 請求範例

### 範例 1: 創建使用者

```bash
curl -X POST https://api.example.com/api/v1/execute \
  -H "Authorization: Bearer your_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "function": "user.create",
    "params": {
      "name": "張三",
      "email": "zhangsan@example.com",
      "phone": "0912345678"
    }
  }'
```

### 範例 2: 查詢訂單

```bash
curl -X POST https://api.example.com/api/v1/execute \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "function": "order.query",
    "params": {
      "order_id": "ORD-12345",
      "include_items": true
    }
  }'
```

### 範例 3: 更新產品

```bash
curl -X POST https://api.example.com/api/v1/execute \
  -H "Authorization: Bearer your_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "function": "product.update",
    "params": {
      "product_id": 123,
      "name": "新產品名稱",
      "price": 999.99,
      "stock": 100
    }
  }'
```

## 參數驗證

API Gateway 會根據 Function 配置自動驗證請求參數。驗證失敗時會返回詳細的錯誤訊息：

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "參數驗證失敗",
    "details": {
      "email": [
        "email 格式不正確"
      ],
      "phone": [
        "phone 為必填欄位"
      ]
    }
  }
}
```

## 最佳實踐

1. **使用 HTTPS**: 所有 API 請求都應該通過 HTTPS 加密傳輸
2. **妥善保管憑證**: API Key 和 Token 應該安全儲存，不要暴露在客戶端程式碼中
3. **處理錯誤**: 實作適當的錯誤處理邏輯，包括重試機制
4. **遵守速率限制**: 監控速率限制標頭，避免超過限制
5. **記錄請求 ID**: 保存 `request_id` 以便追蹤和除錯

## 故障排除

### 401 Unauthorized

- 檢查驗證憑證是否正確
- 確認 Token 是否過期
- 驗證 API Key 是否有效

### 403 Forbidden

- 確認客戶端是否有權限存取該 Function
- 檢查 Function 是否已啟用
- 聯繫管理員檢查權限配置

### 429 Too Many Requests

- 等待 `Retry-After` 標頭指定的時間後重試
- 考慮實作請求佇列或批次處理
- 聯繫管理員提升速率限制

### 500 Internal Server Error

- 檢查請求參數是否正確
- 查看 `request_id` 並聯繫技術支援
- 確認 Stored Procedure 是否正常運作

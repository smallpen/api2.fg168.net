# API 錯誤碼參考

## 概述

本文件列出 Dynamic API Manager 所有可能返回的錯誤碼、對應的 HTTP 狀態碼、錯誤訊息和解決方案。

## 錯誤回應格式

所有錯誤回應都遵循統一的格式：

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "人類可讀的錯誤訊息",
    "details": {
      // 額外的錯誤詳情（選填）
    }
  },
  "meta": {
    "request_id": "req_abc123...",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

## 錯誤碼分類

### 驗證相關錯誤 (4xx)

#### AUTHENTICATION_REQUIRED

- **HTTP 狀態碼**: 401
- **說明**: 請求缺少驗證憑證
- **常見原因**: 
  - 未提供 Authorization 標頭或 X-API-Key 標頭
  - 標頭格式不正確
- **解決方案**: 在請求標頭中加入有效的驗證憑證

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "AUTHENTICATION_REQUIRED",
    "message": "此請求需要驗證，請提供有效的驗證憑證"
  },
  "meta": {
    "request_id": "req_1729509000_abc123",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

**範例請求**:
```bash
# 錯誤 - 缺少驗證標頭
curl -X POST https://api.example.com/api/v1/execute \
  -H "Content-Type: application/json" \
  -d '{"function": "user.profile", "params": {}}'

# 正確 - 包含驗證標頭
curl -X POST https://api.example.com/api/v1/execute \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{"function": "user.profile", "params": {}}'
```

---

#### INVALID_CREDENTIALS

- **HTTP 狀態碼**: 401
- **說明**: 提供的驗證憑證無效
- **常見原因**:
  - API Key 不存在或格式錯誤
  - Token 簽章驗證失敗
  - 憑證已被撤銷
- **解決方案**: 檢查憑證是否正確，或重新生成新的憑證

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "INVALID_CREDENTIALS",
    "message": "提供的驗證憑證無效",
    "details": {
      "reason": "API Key 不存在或已被撤銷"
    }
  },
  "meta": {
    "request_id": "req_1729509000_def456",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

#### TOKEN_EXPIRED

- **HTTP 狀態碼**: 401
- **說明**: Token 已過期
- **常見原因**:
  - Access Token 超過有效期限
  - 系統時間不同步
- **解決方案**: 使用 Refresh Token 取得新的 Access Token，或重新登入

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "TOKEN_EXPIRED",
    "message": "Token 已過期，請重新取得新的 Token",
    "details": {
      "expired_at": "2025-10-21T09:30:00Z",
      "current_time": "2025-10-21T10:30:00Z"
    }
  },
  "meta": {
    "request_id": "req_1729509000_ghi789",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

**處理範例** (JavaScript):
```javascript
async function callApiWithRetry(functionName, params) {
  try {
    return await callApi(functionName, params);
  } catch (error) {
    if (error.code === 'TOKEN_EXPIRED') {
      // 嘗試續期 Token
      await refreshAccessToken();
      // 重試請求
      return await callApi(functionName, params);
    }
    throw error;
  }
}
```

---

#### PERMISSION_DENIED

- **HTTP 狀態碼**: 403
- **說明**: 客戶端沒有權限執行此操作
- **常見原因**:
  - 客戶端未被授權存取該 API Function
  - 角色權限不足
  - 資源層級的權限限制
- **解決方案**: 聯繫管理員檢查權限配置，或使用有足夠權限的憑證

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "PERMISSION_DENIED",
    "message": "您沒有權限執行此操作",
    "details": {
      "required_permission": "function.user.delete",
      "client_permissions": ["function.user.read", "function.user.update"]
    }
  },
  "meta": {
    "request_id": "req_1729509000_jkl012",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

### 請求相關錯誤 (4xx)

#### FUNCTION_NOT_FOUND

- **HTTP 狀態碼**: 404
- **說明**: 指定的 API Function 不存在
- **常見原因**:
  - Function 識別碼拼寫錯誤
  - Function 已被刪除
  - Function 尚未建立
- **解決方案**: 檢查 Function 識別碼是否正確，或在 Admin UI 中確認 Function 是否存在

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "FUNCTION_NOT_FOUND",
    "message": "找不到指定的 API Function",
    "details": {
      "function": "user.deletee",
      "suggestion": "您是否要找 'user.delete'？"
    }
  },
  "meta": {
    "request_id": "req_1729509000_mno345",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

#### FUNCTION_DISABLED

- **HTTP 狀態碼**: 403
- **說明**: API Function 已被停用
- **常見原因**:
  - 管理員暫時停用該 Function
  - Function 正在維護中
- **解決方案**: 聯繫管理員確認 Function 狀態，或等待 Function 重新啟用

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "FUNCTION_DISABLED",
    "message": "此 API Function 目前已停用",
    "details": {
      "function": "payment.process",
      "disabled_at": "2025-10-21T08:00:00Z",
      "reason": "系統維護中"
    }
  },
  "meta": {
    "request_id": "req_1729509000_pqr678",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

#### VALIDATION_ERROR

- **HTTP 狀態碼**: 400
- **說明**: 請求參數驗證失敗
- **常見原因**:
  - 缺少必填參數
  - 參數類型不正確
  - 參數值不符合驗證規則
- **解決方案**: 根據 `details` 中的錯誤訊息修正參數

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "參數驗證失敗",
    "details": {
      "email": [
        "email 為必填欄位",
        "email 格式不正確"
      ],
      "age": [
        "age 必須是整數",
        "age 必須大於等於 18"
      ],
      "phone": [
        "phone 格式不正確，應為 09xxxxxxxx"
      ]
    }
  },
  "meta": {
    "request_id": "req_1729509000_stu901",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

**錯誤請求範例**:
```json
{
  "function": "user.create",
  "params": {
    "name": "張三",
    "email": "invalid-email",
    "age": "seventeen",
    "phone": "12345"
  }
}
```

**正確請求範例**:
```json
{
  "function": "user.create",
  "params": {
    "name": "張三",
    "email": "zhangsan@example.com",
    "age": 25,
    "phone": "0912345678"
  }
}
```

---

#### INVALID_REQUEST_FORMAT

- **HTTP 狀態碼**: 400
- **說明**: 請求格式不正確
- **常見原因**:
  - JSON 格式錯誤
  - 缺少必要的欄位（如 `function`）
  - Content-Type 不正確
- **解決方案**: 檢查請求格式是否符合 API 規範

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "INVALID_REQUEST_FORMAT",
    "message": "請求格式不正確",
    "details": {
      "reason": "JSON 解析失敗",
      "position": "line 3, column 15"
    }
  },
  "meta": {
    "request_id": "req_1729509000_vwx234",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

#### RATE_LIMIT_EXCEEDED

- **HTTP 狀態碼**: 429
- **說明**: 超過請求頻率限制
- **常見原因**:
  - 短時間內發送過多請求
  - 客戶端的速率限制配額已用完
- **解決方案**: 等待 `retry_after` 秒後重試，或實作請求佇列機制

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "超過請求頻率限制，請稍後再試",
    "details": {
      "limit": 60,
      "window": "1 minute",
      "retry_after": 45,
      "reset_at": "2025-10-21T10:31:00Z"
    }
  },
  "meta": {
    "request_id": "req_1729509000_yza567",
    "timestamp": "2025-10-21T10:30:15Z"
  }
}
```

**回應標頭**:
```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1729509060
Retry-After: 45
```

**處理範例** (JavaScript):
```javascript
async function callApiWithRateLimit(functionName, params) {
  try {
    return await callApi(functionName, params);
  } catch (error) {
    if (error.code === 'RATE_LIMIT_EXCEEDED') {
      const retryAfter = error.details.retry_after * 1000; // 轉換為毫秒
      console.log(`速率限制，等待 ${retryAfter}ms 後重試`);
      await sleep(retryAfter);
      return await callApi(functionName, params);
    }
    throw error;
  }
}
```

---

### 伺服器相關錯誤 (5xx)

#### STORED_PROCEDURE_ERROR

- **HTTP 狀態碼**: 500
- **說明**: Stored Procedure 執行時發生錯誤
- **常見原因**:
  - Stored Procedure 內部邏輯錯誤
  - 資料庫約束違反
  - 參數映射錯誤
- **解決方案**: 檢查請求參數是否正確，聯繫技術支援並提供 `request_id`

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "STORED_PROCEDURE_ERROR",
    "message": "執行資料庫程序時發生錯誤",
    "details": {
      "procedure": "sp_create_user",
      "error_message": "Duplicate entry 'user@example.com' for key 'email'",
      "sql_state": "23000"
    }
  },
  "meta": {
    "request_id": "req_1729509000_bcd890",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

#### DATABASE_ERROR

- **HTTP 狀態碼**: 500
- **說明**: 資料庫連線或查詢錯誤
- **常見原因**:
  - 資料庫連線失敗
  - 資料庫伺服器無回應
  - 查詢逾時
- **解決方案**: 稍後重試，如果問題持續請聯繫技術支援

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "DATABASE_ERROR",
    "message": "資料庫操作失敗",
    "details": {
      "reason": "Connection timeout",
      "retry_possible": true
    }
  },
  "meta": {
    "request_id": "req_1729509000_efg123",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

#### QUERY_TIMEOUT

- **HTTP 狀態碼**: 504
- **說明**: 資料庫查詢執行逾時
- **常見原因**:
  - 查詢過於複雜
  - 資料量過大
  - 資料庫效能問題
- **解決方案**: 優化查詢條件，減少資料範圍，或聯繫管理員優化 Stored Procedure

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "QUERY_TIMEOUT",
    "message": "查詢執行逾時",
    "details": {
      "timeout": 30,
      "unit": "seconds",
      "suggestion": "請縮小查詢範圍或加入更多篩選條件"
    }
  },
  "meta": {
    "request_id": "req_1729509000_hij456",
    "timestamp": "2025-10-21T10:30:30Z"
  }
}
```

---

#### INTERNAL_ERROR

- **HTTP 狀態碼**: 500
- **說明**: 內部伺服器錯誤
- **常見原因**:
  - 未預期的系統錯誤
  - 配置錯誤
  - 程式碼錯誤
- **解決方案**: 聯繫技術支援並提供 `request_id` 以便追蹤問題

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "伺服器發生內部錯誤，請稍後再試",
    "details": {
      "error_id": "err_1729509000_xyz789"
    }
  },
  "meta": {
    "request_id": "req_1729509000_klm789",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

#### SERVICE_UNAVAILABLE

- **HTTP 狀態碼**: 503
- **說明**: 服務暫時無法使用
- **常見原因**:
  - 系統維護中
  - 伺服器過載
  - 依賴服務不可用
- **解決方案**: 等待服務恢復，或查看系統狀態頁面

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "SERVICE_UNAVAILABLE",
    "message": "服務暫時無法使用",
    "details": {
      "reason": "系統維護中",
      "estimated_recovery": "2025-10-21T12:00:00Z"
    }
  },
  "meta": {
    "request_id": "req_1729509000_nop012",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

### 配置相關錯誤 (5xx)

#### CONFIGURATION_ERROR

- **HTTP 狀態碼**: 500
- **說明**: API Function 配置錯誤
- **常見原因**:
  - Stored Procedure 不存在
  - 參數映射配置錯誤
  - 回應映射配置錯誤
- **解決方案**: 聯繫管理員檢查 Function 配置

**範例回應**:
```json
{
  "success": false,
  "error": {
    "code": "CONFIGURATION_ERROR",
    "message": "API Function 配置錯誤",
    "details": {
      "function": "order.process",
      "issue": "Stored Procedure 'sp_process_order' 不存在"
    }
  },
  "meta": {
    "request_id": "req_1729509000_qrs345",
    "timestamp": "2025-10-21T10:30:00Z"
  }
}
```

---

## 錯誤碼快速參考表

| 錯誤碼 | HTTP 狀態碼 | 分類 | 說明 |
|--------|-----------|------|------|
| `AUTHENTICATION_REQUIRED` | 401 | 驗證 | 缺少驗證憑證 |
| `INVALID_CREDENTIALS` | 401 | 驗證 | 驗證憑證無效 |
| `TOKEN_EXPIRED` | 401 | 驗證 | Token 已過期 |
| `PERMISSION_DENIED` | 403 | 授權 | 權限不足 |
| `FUNCTION_NOT_FOUND` | 404 | 請求 | API Function 不存在 |
| `FUNCTION_DISABLED` | 403 | 請求 | API Function 已停用 |
| `VALIDATION_ERROR` | 400 | 請求 | 參數驗證失敗 |
| `INVALID_REQUEST_FORMAT` | 400 | 請求 | 請求格式不正確 |
| `RATE_LIMIT_EXCEEDED` | 429 | 限流 | 超過請求頻率限制 |
| `STORED_PROCEDURE_ERROR` | 500 | 伺服器 | Stored Procedure 執行錯誤 |
| `DATABASE_ERROR` | 500 | 伺服器 | 資料庫錯誤 |
| `QUERY_TIMEOUT` | 504 | 伺服器 | 查詢執行逾時 |
| `INTERNAL_ERROR` | 500 | 伺服器 | 內部伺服器錯誤 |
| `SERVICE_UNAVAILABLE` | 503 | 伺服器 | 服務暫時無法使用 |
| `CONFIGURATION_ERROR` | 500 | 配置 | API Function 配置錯誤 |

## 錯誤處理最佳實踐

### 1. 實作統一的錯誤處理

```javascript
// JavaScript 範例
class ApiClient {
  async execute(functionName, params) {
    try {
      const response = await fetch('/api/v1/execute', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ function: functionName, params })
      });
      
      const data = await response.json();
      
      if (!data.success) {
        throw new ApiError(data.error, data.meta);
      }
      
      return data.data;
    } catch (error) {
      return this.handleError(error);
    }
  }
  
  handleError(error) {
    switch (error.code) {
      case 'TOKEN_EXPIRED':
        return this.refreshTokenAndRetry();
      case 'RATE_LIMIT_EXCEEDED':
        return this.retryAfterDelay(error.details.retry_after);
      case 'VALIDATION_ERROR':
        throw new ValidationError(error.details);
      default:
        throw error;
    }
  }
}
```

### 2. 記錄錯誤資訊

```php
// PHP 範例
try {
    $result = $apiClient->execute('user.create', $params);
} catch (ApiException $e) {
    // 記錄完整的錯誤資訊
    Log::error('API 呼叫失敗', [
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage(),
        'request_id' => $e->getRequestId(),
        'function' => 'user.create',
        'params' => $params,
        'timestamp' => now()
    ]);
    
    // 向使用者顯示友善的錯誤訊息
    return response()->json([
        'message' => '操作失敗，請稍後再試',
        'error_id' => $e->getRequestId()
    ], 500);
}
```

### 3. 實作重試機制

```python
# Python 範例
import time
from typing import Dict, Any

def call_api_with_retry(function_name: str, params: Dict[str, Any], max_retries: int = 3):
    """
    呼叫 API 並在特定錯誤時自動重試
    """
    retries = 0
    
    while retries < max_retries:
        try:
            return api_client.execute(function_name, params)
        except ApiError as e:
            if e.code == 'RATE_LIMIT_EXCEEDED':
                # 速率限制，等待後重試
                retry_after = e.details.get('retry_after', 60)
                time.sleep(retry_after)
                retries += 1
            elif e.code in ['DATABASE_ERROR', 'SERVICE_UNAVAILABLE']:
                # 暫時性錯誤，指數退避重試
                wait_time = 2 ** retries
                time.sleep(wait_time)
                retries += 1
            else:
                # 其他錯誤不重試
                raise
    
    raise Exception(f'API 呼叫失敗，已重試 {max_retries} 次')
```

### 4. 向使用者顯示友善的錯誤訊息

```javascript
// 將技術性錯誤轉換為使用者友善的訊息
function getUserFriendlyMessage(errorCode) {
  const messages = {
    'AUTHENTICATION_REQUIRED': '請先登入',
    'PERMISSION_DENIED': '您沒有權限執行此操作',
    'VALIDATION_ERROR': '請檢查輸入的資料是否正確',
    'RATE_LIMIT_EXCEEDED': '操作過於頻繁，請稍後再試',
    'INTERNAL_ERROR': '系統發生錯誤，我們正在處理中'
  };
  
  return messages[errorCode] || '操作失敗，請稍後再試';
}
```

## 除錯技巧

### 1. 使用 Request ID 追蹤問題

每個錯誤回應都包含唯一的 `request_id`，可用於在日誌中追蹤完整的請求流程：

```bash
# 在日誌中搜尋特定請求
grep "req_1729509000_abc123" /var/log/api-server.log
```

### 2. 檢查回應標頭

使用 `curl -v` 查看完整的回應標頭，包括速率限制資訊：

```bash
curl -v -X POST https://api.example.com/api/v1/execute \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{"function": "test.ping", "params": {}}'
```

### 3. 啟用詳細錯誤訊息（開發環境）

在開發環境中，可以在請求標頭中加入 `X-Debug: true` 以取得更詳細的錯誤資訊：

```bash
curl -X POST https://api.example.com/api/v1/execute \
  -H "Authorization: Bearer your_token" \
  -H "X-Debug: true" \
  -H "Content-Type: application/json" \
  -d '{"function": "user.create", "params": {}}'
```

**注意**: 此功能僅在開發環境中可用，生產環境會忽略此標頭。

## 聯繫技術支援

如果遇到無法解決的錯誤，請聯繫技術支援並提供以下資訊：

1. **Request ID**: 從錯誤回應的 `meta.request_id` 取得
2. **錯誤碼**: 從錯誤回應的 `error.code` 取得
3. **時間戳**: 錯誤發生的時間
4. **請求內容**: Function 名稱和參數（移除敏感資訊）
5. **預期行為**: 描述您預期的結果
6. **實際行為**: 描述實際發生的情況

技術支援信箱: support@example.com

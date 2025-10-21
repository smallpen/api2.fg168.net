# Admin UI - Function 管理指南

## 概述

Function 管理是 Dynamic API Manager 的核心功能，允許管理員通過 Web 介面動態創建、配置和管理 API Function，而無需修改程式碼或重啟服務。

## 存取 Function 管理介面

1. 登入 Admin UI
2. 在左側導航選單中點擊「API Functions」
3. 進入 Function 列表頁面

## Function 列表頁面

### 頁面功能

Function 列表頁面顯示所有已配置的 API Function，提供以下功能：

- **搜尋**: 根據 Function 名稱或識別碼搜尋
- **篩選**: 按狀態（啟用/停用）、類別或標籤篩選
- **排序**: 按名稱、創建時間或最後修改時間排序
- **批次操作**: 批次啟用、停用或刪除 Function

### Function 列表欄位

| 欄位 | 說明 |
|------|------|
| 名稱 | Function 的顯示名稱 |
| 識別碼 | Function 的唯一識別碼（用於 API 呼叫） |
| Stored Procedure | 對應的資料庫 Stored Procedure |
| 狀態 | 啟用或停用 |
| 最後修改 | 最後修改時間和修改者 |
| 操作 | 編輯、複製、刪除等操作 |

### 快速操作

#### 啟用/停用 Function

點擊 Function 列表中的開關按鈕即可快速啟用或停用 Function。

- **啟用**: Function 可以被 API Gateway 呼叫
- **停用**: Function 無法被呼叫，API Gateway 會返回 403 錯誤

#### 複製 Function

點擊「複製」按鈕可以快速創建一個相同配置的新 Function，適合創建類似的 API。

#### 刪除 Function

點擊「刪除」按鈕會彈出確認對話框。刪除後無法恢復，請謹慎操作。

**注意**: 刪除 Function 不會刪除對應的 Stored Procedure。

## 創建新的 API Function

### 步驟 1: 基本資訊

點擊「創建 Function」按鈕，填寫基本資訊：

#### 必填欄位

- **Function 名稱**: 顯示名稱，例如「創建使用者」
- **Function 識別碼**: 唯一識別碼，用於 API 呼叫，例如「user.create」
  - 建議使用小寫字母、數字和點號
  - 格式：`{模組}.{操作}`，例如 `user.create`、`order.query`
- **描述**: Function 的用途說明

#### 選填欄位

- **類別**: Function 的分類，例如「使用者管理」、「訂單處理」
- **標籤**: 用於搜尋和篩選的標籤
- **版本**: API 版本號，例如「v1」、「v2」

#### 範例

```
名稱: 創建使用者
識別碼: user.create
描述: 創建新的使用者帳號，包含基本資料驗證和重複檢查
類別: 使用者管理
標籤: user, create, registration
版本: v1
```

### 步驟 2: 選擇 Stored Procedure

#### 使用 Stored Procedure 選擇器

1. 點擊「選擇 Stored Procedure」按鈕
2. 從下拉選單中選擇資料庫
3. 搜尋或瀏覽可用的 Stored Procedure
4. 選擇要使用的 Stored Procedure

#### Stored Procedure 資訊

選擇後，系統會自動載入以下資訊：

- **Procedure 名稱**: 例如 `sp_create_user`
- **參數列表**: Procedure 的所有輸入和輸出參數
- **參數類型**: 每個參數的資料類型
- **參數說明**: 參數的用途說明（如果有）

#### 範例

```
Stored Procedure: sp_create_user
資料庫: main_db

參數列表:
- p_name (VARCHAR): 使用者姓名
- p_email (VARCHAR): 電子郵件
- p_phone (VARCHAR): 電話號碼
- p_password (VARCHAR): 密碼（已加密）
- out_user_id (INT): 輸出 - 新建使用者的 ID
```

### 步驟 3: 配置參數

#### 參數建構器

參數建構器允許您定義 API 接受的參數，並映射到 Stored Procedure 的參數。

#### 新增參數

點擊「新增參數」按鈕，填寫以下資訊：

##### 基本設定

- **參數名稱**: API 請求中的參數名稱，例如「name」
- **顯示名稱**: 在 UI 中顯示的名稱，例如「使用者姓名」
- **資料類型**: 選擇參數的資料類型
  - `string`: 字串
  - `integer`: 整數
  - `float`: 浮點數
  - `boolean`: 布林值
  - `date`: 日期 (Y-m-d)
  - `datetime`: 日期時間 (Y-m-d H:i:s)
  - `json`: JSON 物件
  - `array`: 陣列
- **是否必填**: 勾選表示此參數為必填
- **預設值**: 當請求未提供此參數時使用的預設值

##### 驗證規則

為參數設定驗證規則，確保資料正確性：

**常用驗證規則**:

- `required`: 必填
- `email`: 電子郵件格式
- `min:n`: 最小長度或最小值
- `max:n`: 最大長度或最大值
- `regex:pattern`: 正則表達式驗證
- `in:value1,value2`: 值必須在指定列表中
- `numeric`: 數字
- `alpha`: 只能包含字母
- `alpha_num`: 只能包含字母和數字
- `url`: URL 格式
- `unique:table,column`: 資料庫唯一性檢查

**範例**:

```
參數: email
驗證規則: required|email|unique:users,email
說明: 必填、必須是有效的電子郵件格式、在 users 表中必須唯一

參數: phone
驗證規則: required|regex:/^09\d{8}$/
說明: 必填、必須符合台灣手機號碼格式

參數: age
驗證規則: required|integer|min:18|max:120
說明: 必填、必須是整數、年齡在 18-120 之間
```

##### 參數映射

將 API 參數映射到 Stored Procedure 參數：

- **SP 參數名稱**: 選擇對應的 Stored Procedure 參數
- **轉換規則**: 如果需要，可以設定資料轉換規則
  - 例如：將密碼進行 bcrypt 加密
  - 例如：將日期格式轉換

##### 完整參數配置範例

```
參數 1:
  名稱: name
  顯示名稱: 使用者姓名
  資料類型: string
  必填: 是
  驗證規則: required|min:2|max:50
  SP 參數: p_name
  
參數 2:
  名稱: email
  顯示名稱: 電子郵件
  資料類型: string
  必填: 是
  驗證規則: required|email|unique:users,email
  SP 參數: p_email
  
參數 3:
  名稱: phone
  顯示名稱: 電話號碼
  資料類型: string
  必填: 否
  驗證規則: nullable|regex:/^09\d{8}$/
  SP 參數: p_phone
  
參數 4:
  名稱: password
  顯示名稱: 密碼
  資料類型: string
  必填: 是
  驗證規則: required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/
  SP 參數: p_password
  轉換規則: bcrypt
```

#### 參數順序

使用拖曳功能調整參數順序，這會影響文件顯示順序，但不影響功能。

### 步驟 4: 配置回應映射

#### 回應映射器

回應映射器定義如何將 Stored Procedure 的執行結果轉換為 API 回應。

#### 結果集映射

如果 Stored Procedure 返回結果集（SELECT 查詢），需要配置欄位映射：

##### 新增欄位映射

- **API 欄位名稱**: 在 API 回應中的欄位名稱
- **SP 欄位名稱**: Stored Procedure 結果集中的欄位名稱
- **資料類型**: 欄位的資料類型
- **轉換規則**: 資料轉換規則（選填）

##### 範例

```
欄位映射:
  user_id: id (integer)
  user_name: name (string)
  user_email: email (string)
  created_at: created_at (datetime) - 轉換為 ISO 8601 格式
  is_active: status (boolean) - 1/0 轉換為 true/false
```

#### 輸出參數映射

如果 Stored Procedure 使用輸出參數（OUT 參數），需要配置輸出參數映射：

##### 範例

```
輸出參數:
  out_user_id -> user_id (integer)
  out_message -> message (string)
```

#### 回應格式預覽

系統會顯示預期的 API 回應格式：

```json
{
  "success": true,
  "data": {
    "user_id": 123,
    "name": "張三",
    "email": "zhangsan@example.com",
    "created_at": "2025-10-21T10:30:00Z",
    "status": true
  },
  "meta": {
    "request_id": "req_abc123",
    "execution_time": 0.045
  }
}
```

### 步驟 5: 配置錯誤映射

#### 錯誤映射設定

定義如何將 Stored Procedure 的錯誤映射到 HTTP 狀態碼和錯誤訊息。

#### 新增錯誤映射

- **SP 錯誤碼**: Stored Procedure 拋出的錯誤碼
- **HTTP 狀態碼**: 對應的 HTTP 狀態碼
- **錯誤訊息**: 返回給客戶端的錯誤訊息
- **錯誤代碼**: API 錯誤代碼（選填）

#### 範例

```
錯誤映射:
  1062 (Duplicate entry) -> 409 Conflict
    訊息: "電子郵件已被使用"
    代碼: DUPLICATE_EMAIL
    
  1452 (Foreign key constraint) -> 400 Bad Request
    訊息: "參考的資料不存在"
    代碼: INVALID_REFERENCE
    
  45000 (Custom error) -> 400 Bad Request
    訊息: "使用者年齡必須大於 18 歲"
    代碼: AGE_REQUIREMENT_NOT_MET
```

### 步驟 6: 進階設定

#### 快取設定

- **啟用快取**: 是否快取此 Function 的回應
- **快取時間**: 快取有效時間（秒）
- **快取鍵**: 快取鍵的生成規則

#### 逾時設定

- **執行逾時**: Stored Procedure 執行的最大時間（秒）
- **預設值**: 30 秒

#### 日誌設定

- **記錄請求**: 是否記錄請求參數
- **記錄回應**: 是否記錄回應資料
- **敏感欄位**: 標記敏感欄位（如密碼），這些欄位在日誌中會被遮罩

### 步驟 7: 測試 Function

在儲存前，可以使用內建的測試工具測試 Function：

1. 點擊「測試」按鈕
2. 輸入測試參數
3. 點擊「執行測試」
4. 查看測試結果

#### 測試範例

```json
測試請求:
{
  "function": "user.create",
  "params": {
    "name": "測試使用者",
    "email": "test@example.com",
    "phone": "0912345678",
    "password": "Test1234"
  }
}

測試回應:
{
  "success": true,
  "data": {
    "user_id": 999,
    "name": "測試使用者",
    "email": "test@example.com",
    "created_at": "2025-10-21T10:30:00Z"
  },
  "meta": {
    "execution_time": 0.052
  }
}
```

### 步驟 8: 儲存 Function

確認所有配置正確後，點擊「儲存」按鈕。

- Function 會立即生效，無需重啟服務
- 系統會記錄配置變更到審計日誌
- 可以在 Function 列表中看到新建立的 Function

## 編輯現有 Function

### 編輯流程

1. 在 Function 列表中找到要編輯的 Function
2. 點擊「編輯」按鈕
3. 修改需要變更的配置
4. 點擊「儲存」按鈕

### 版本控制

系統會自動保存 Function 的歷史版本：

- 每次儲存都會創建新版本
- 可以查看歷史版本
- 可以回滾到之前的版本

### 查看歷史版本

1. 在 Function 編輯頁面點擊「版本歷史」
2. 查看所有歷史版本
3. 點擊「查看」查看特定版本的配置
4. 點擊「回滾」恢復到該版本

## 複製 Function

複製功能可以快速創建相似的 Function：

1. 在 Function 列表中點擊「複製」按鈕
2. 系統會創建一個新的 Function，包含原 Function 的所有配置
3. 修改 Function 識別碼（必須唯一）
4. 調整其他需要變更的配置
5. 儲存新 Function

## 刪除 Function

### 刪除單一 Function

1. 在 Function 列表中點擊「刪除」按鈕
2. 確認刪除操作
3. Function 會被永久刪除

### 批次刪除

1. 勾選要刪除的 Function
2. 點擊「批次刪除」按鈕
3. 確認刪除操作

### 注意事項

- 刪除操作無法撤銷
- 刪除 Function 不會刪除對應的 Stored Procedure
- 刪除前請確認沒有客戶端正在使用該 Function
- 系統會記錄刪除操作到審計日誌

## 匯入和匯出 Function

### 匯出 Function

將 Function 配置匯出為 JSON 檔案，用於備份或遷移：

1. 選擇要匯出的 Function
2. 點擊「匯出」按鈕
3. 選擇匯出格式（JSON）
4. 下載匯出檔案

### 匯入 Function

從 JSON 檔案匯入 Function 配置：

1. 點擊「匯入」按鈕
2. 選擇 JSON 檔案
3. 預覽匯入內容
4. 確認匯入

### 匯出格式範例

```json
{
  "name": "創建使用者",
  "identifier": "user.create",
  "description": "創建新的使用者帳號",
  "stored_procedure": "sp_create_user",
  "parameters": [
    {
      "name": "name",
      "type": "string",
      "required": true,
      "validation": "required|min:2|max:50",
      "sp_parameter": "p_name"
    }
  ],
  "responses": [
    {
      "api_field": "user_id",
      "sp_field": "id",
      "type": "integer"
    }
  ],
  "error_mappings": [
    {
      "sp_error": "1062",
      "http_status": 409,
      "message": "電子郵件已被使用"
    }
  ]
}
```

## Function 權限管理

### 設定 Function 權限

控制哪些客戶端可以存取特定的 Function：

1. 在 Function 編輯頁面點擊「權限」標籤
2. 查看當前的權限設定
3. 新增或移除客戶端權限
4. 儲存變更

### 權限類型

- **公開**: 所有已驗證的客戶端都可以存取
- **限制**: 只有特定的客戶端或角色可以存取
- **私有**: 只有管理員可以存取

詳細的權限配置請參閱 [權限配置指南](admin-ui-permissions.md)。

## 監控 Function 使用情況

### 使用統計

在 Function 詳情頁面可以查看：

- **呼叫次數**: 總呼叫次數和時間分布
- **成功率**: 成功和失敗的比例
- **平均執行時間**: Function 的效能指標
- **錯誤統計**: 常見錯誤類型和頻率

### 最近請求

查看最近的 API 請求記錄：

- 請求時間
- 客戶端資訊
- 請求參數
- 回應狀態
- 執行時間

## 最佳實踐

### Function 命名

- 使用清晰、描述性的名稱
- 遵循命名規範：`{模組}.{操作}`
- 例如：`user.create`、`order.query`、`product.update`

### 參數設計

- 只定義必要的參數
- 使用適當的驗證規則
- 提供清晰的參數說明
- 考慮向後相容性

### 錯誤處理

- 為所有可能的錯誤情況配置錯誤映射
- 提供友善的錯誤訊息
- 使用適當的 HTTP 狀態碼

### 效能優化

- 為不常變動的資料啟用快取
- 設定合理的執行逾時時間
- 優化 Stored Procedure 效能

### 安全性

- 標記敏感欄位（如密碼、信用卡號）
- 使用嚴格的參數驗證
- 設定適當的權限控制
- 定期審查 Function 配置

### 文件化

- 提供詳細的 Function 描述
- 說明每個參數的用途
- 提供使用範例
- 記錄已知限制

## 故障排除

### Function 無法儲存

**可能原因**:
- Function 識別碼重複
- 參數配置不完整
- Stored Procedure 不存在

**解決方案**:
- 檢查錯誤訊息
- 確認所有必填欄位已填寫
- 驗證 Stored Procedure 是否存在

### Function 執行失敗

**可能原因**:
- 參數驗證失敗
- Stored Procedure 錯誤
- 資料庫連線問題

**解決方案**:
- 查看錯誤日誌
- 使用測試工具除錯
- 檢查 Stored Procedure 邏輯

### 回應格式不正確

**可能原因**:
- 回應映射配置錯誤
- Stored Procedure 返回格式變更

**解決方案**:
- 檢查回應映射配置
- 使用測試工具驗證回應格式
- 更新映射配置

## 常見問題

### Q: 修改 Function 配置後需要重啟服務嗎？

A: 不需要。所有配置變更會立即生效。

### Q: 可以同時編輯同一個 Function 嗎？

A: 系統會鎖定正在編輯的 Function，防止衝突。如果其他管理員正在編輯，您會看到提示訊息。

### Q: 如何測試 Function 而不影響生產資料？

A: 使用測試工具時，可以選擇連接到測試資料庫。或者在測試環境中配置和測試 Function，確認無誤後再匯出到生產環境。

### Q: Function 識別碼可以修改嗎？

A: 可以，但不建議。修改識別碼會影響所有使用該 Function 的客戶端。如果必須修改，請通知所有相關的客戶端開發者。

### Q: 如何備份 Function 配置？

A: 使用匯出功能定期備份 Function 配置。建議將匯出的 JSON 檔案納入版本控制系統。

### Q: 可以為 Function 設定不同環境的配置嗎？

A: 可以。使用匯入/匯出功能在不同環境間遷移配置，並根據環境調整參數（如資料庫連線、快取設定等）。

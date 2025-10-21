# 授權服務 (Authorization Service)

## 概述

授權服務負責管理和檢查 API 客戶端對 API Function 的存取權限。系統採用基於角色的存取控制（RBAC）模型，支援細粒度的權限管理。

## 核心元件

### 1. AuthorizationManager

授權管理器是授權服務的主要入口點，負責協調權限檢查和角色管理。

#### 主要功能

- **authorize()**: 檢查客戶端是否有權限執行指定的 API Function
- **checkPermission()**: 檢查客戶端是否有指定的權限
- **assignRole()**: 為客戶端授予角色
- **removeRole()**: 從客戶端移除角色
- **clearClientPermissionCache()**: 清除客戶端的權限快取

#### 使用範例

```php
use App\Services\Authorization\AuthorizationManager;
use App\Models\ApiClient;
use App\Models\ApiFunction;

// 注入授權管理器
$authManager = app(AuthorizationManager::class);

// 檢查客戶端是否有權限執行 Function
$client = ApiClient::find(1);
$function = ApiFunction::find(1);

if ($authManager->authorize($client, $function)) {
    // 客戶端有權限，執行 Function
} else {
    // 客戶端無權限，拒絕請求
}

// 為客戶端指派角色
$authManager->assignRole($client, 'user');

// 從客戶端移除角色
$authManager->removeRole($client, 'guest');
```

### 2. PermissionChecker

權限檢查器負責實際的權限驗證邏輯。

#### 主要功能

- **check()**: 檢查客戶端是否有權限執行 Function
- **checkPermission()**: 檢查客戶端是否有指定的權限
- **getAccessibleFunctionIds()**: 取得客戶端可存取的所有 Function ID
- **isAdmin()**: 檢查客戶端是否為管理員

#### 權限檢查邏輯

權限檢查按以下優先順序進行：

1. **明確的 Function 權限**：檢查 `function_permissions` 資料表中的設定
2. **角色權限**：檢查客戶端角色是否有相應的權限
3. **預設拒絕**：如果沒有匹配的權限，預設拒絕存取

#### 使用範例

```php
use App\Services\Authorization\PermissionChecker;

$checker = app(PermissionChecker::class);

// 檢查客戶端是否有權限執行 Function
$hasPermission = $checker->check($client, $function);

// 檢查客戶端是否有特定權限
$canView = $checker->checkPermission(
    $client,
    'function',  // 資源類型
    $functionId, // 資源 ID
    'view'       // 動作
);

// 取得客戶端可存取的所有 Function
$functionIds = $checker->getAccessibleFunctionIds($client);
```

### 3. RoleManager

角色管理器負責管理角色和角色權限。

#### 主要功能

- **createRole()**: 建立新角色
- **assignRoleToClient()**: 為客戶端指派角色
- **removeRoleFromClient()**: 從客戶端移除角色
- **grantPermissionToRole()**: 為角色授予權限
- **revokePermissionFromRole()**: 從角色移除權限
- **createDefaultRoles()**: 建立預設角色

#### 使用範例

```php
use App\Services\Authorization\RoleManager;
use App\Models\Permission;

$roleManager = app(RoleManager::class);

// 建立新角色
$role = $roleManager->createRole('developer', '開發者');

// 為客戶端指派角色
$roleManager->assignRoleToClient($client, 'developer');

// 建立權限並授予角色
$permission = Permission::createFunctionExecutePermission($functionId);
$roleManager->grantPermissionToRole($role, $permission);

// 建立預設角色
$roleManager->createDefaultRoles();
```

## Middleware 使用

### AuthorizeApi Middleware

授權 Middleware 用於保護 API 路由，確保只有有權限的客戶端可以存取。

#### 註冊 Middleware

Middleware 已在 `app/Http/Kernel.php` 中註冊為 `authorize.api`。

#### 使用方式

```php
// 在路由中使用
Route::post('/api/v1/execute', [ApiGatewayController::class, 'execute'])
    ->middleware(['auth.api', 'authorize.api']);

// 在控制器建構函數中使用
public function __construct()
{
    $this->middleware(['auth.api', 'authorize.api']);
}
```

#### Middleware 流程

1. 檢查客戶端是否已驗證（由 `auth.api` Middleware 設定）
2. 檢查客戶端是否啟用
3. 從請求中取得要執行的 Function
4. 檢查 Function 是否啟用
5. 執行授權檢查
6. 如果授權失敗，拋出 `AuthorizationException`
7. 如果授權成功，將 Function 加入請求屬性並繼續處理

## 權限模型

### 資料表結構

#### roles
- `id`: 角色 ID
- `name`: 角色名稱（唯一）
- `description`: 角色描述

#### permissions
- `id`: 權限 ID
- `resource_type`: 資源類型（function, client, role, log）
- `resource_id`: 資源 ID（null 表示所有資源）
- `action`: 動作（*, view, create, update, delete, execute）

#### role_permissions
- `role_id`: 角色 ID
- `permission_id`: 權限 ID

#### client_roles
- `client_id`: 客戶端 ID
- `role_id`: 角色 ID

#### function_permissions
- `function_id`: Function ID
- `client_id`: 客戶端 ID
- `allowed`: 是否允許（boolean）

### 預設角色

系統提供三個預設角色：

1. **admin**: 系統管理員，擁有所有權限
2. **user**: 一般使用者，擁有基本權限
3. **guest**: 訪客，擁有最低權限

## 快取機制

授權服務使用 Redis 快取權限檢查結果，以提升效能。

### 快取鍵格式

```
permission:client:{client_id}:function:{function_id}
```

### 快取過期時間

預設為 3600 秒（1 小時），可透過 `setCacheExpiration()` 方法調整。

### 清除快取

```php
// 清除特定客戶端的權限快取
$authManager->clearClientPermissionCache($clientId);

// 清除特定 Function 的權限快取
$authManager->clearFunctionPermissionCache($functionId);

// 清除所有權限快取
$authManager->clearAllPermissionCache();
```

## 例外處理

### AuthorizationException

授權失敗時會拋出 `AuthorizationException`，包含以下資訊：

- **message**: 錯誤訊息
- **errorCode**: 錯誤代碼
- **statusCode**: HTTP 狀態碼（預設 403）

#### 錯誤代碼

- `PERMISSION_DENIED`: 權限不足
- `FUNCTION_DISABLED`: Function 已停用
- `CLIENT_DISABLED`: 客戶端已停用

#### 使用範例

```php
use App\Exceptions\AuthorizationException;

// 拋出一般授權例外
throw new AuthorizationException('權限不足');

// 拋出 Function 已停用例外
throw AuthorizationException::functionDisabled($functionName);

// 拋出客戶端已停用例外
throw AuthorizationException::clientDisabled();

// 拋出無權限存取 Function 例外
throw AuthorizationException::noFunctionAccess($functionName);
```

## 安全日誌

授權失敗事件會記錄到安全日誌（`storage/logs/security.log`），包含以下資訊：

- 事件類型
- 客戶端資訊
- Function 資訊
- IP 位址
- User Agent
- 時間戳

## 最佳實踐

1. **最小權限原則**：只授予客戶端必要的權限
2. **使用角色管理**：透過角色管理權限，而非直接為客戶端設定權限
3. **定期審查權限**：定期檢查和更新權限設定
4. **監控安全日誌**：定期檢查安全日誌，發現異常存取行為
5. **快取管理**：權限變更後記得清除相關快取

## 完整範例

```php
use App\Services\Authorization\AuthorizationManager;
use App\Services\Authorization\RoleManager;
use App\Models\ApiClient;
use App\Models\ApiFunction;
use App\Models\Permission;

// 1. 建立角色和權限
$roleManager = app(RoleManager::class);
$role = $roleManager->createRole('api_user', 'API 使用者');

// 2. 為角色授予權限
$function = ApiFunction::where('identifier', 'user.create')->first();
$permission = Permission::createFunctionExecutePermission($function->id);
$roleManager->grantPermissionToRole($role, $permission);

// 3. 為客戶端指派角色
$client = ApiClient::find(1);
$roleManager->assignRoleToClient($client, 'api_user');

// 4. 檢查授權
$authManager = app(AuthorizationManager::class);
if ($authManager->authorize($client, $function)) {
    // 執行 Function
    echo "授權成功！";
} else {
    // 拒絕請求
    echo "授權失敗！";
}
```

## 測試

授權服務包含完整的單元測試，位於 `tests/Unit/AuthorizationServiceTest.php`。

執行測試：

```bash
php artisan test --filter=AuthorizationServiceTest
```

## 相關需求

此實作滿足以下需求：

- **Requirement 10.2**: 支援基於角色的存取控制（RBAC）
- **Requirement 13.1**: 配置 API Function 的存取權限規則
- **Requirement 13.2**: 支援基於客戶端身份的權限控制
- **Requirement 13.3**: 驗證客戶端是否有權限調用 Function
- **Requirement 13.4**: 權限驗證失敗時返回 HTTP 403
- **Requirement 13.5**: 記錄權限驗證失敗的請求到安全日誌

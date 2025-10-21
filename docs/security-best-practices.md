# 安全最佳實踐

本文檔說明 Dynamic API Manager 的安全最佳實踐和建議。

## 目錄

1. [驗證和授權](#驗證和授權)
2. [資料保護](#資料保護)
3. [網路安全](#網路安全)
4. [輸入驗證](#輸入驗證)
5. [日誌和監控](#日誌和監控)
6. [定期維護](#定期維護)

## 驗證和授權

### 1. 強密碼政策

系統預設的密碼政策要求：

- 最少 8 個字元
- 包含大寫字母
- 包含小寫字母
- 包含數字
- 包含特殊字元

可在 `config/security.php` 中調整：

```php
'password' => [
    'min_length' => 12,  // 建議使用更長的密碼
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_numbers' => true,
    'require_special_chars' => true,
    'expires_days' => 90,  // 定期更換密碼
],
```

### 2. Token 管理

#### 生成安全的 Token

```php
use App\Helpers\SecurityHelper;

// 生成 64 字元的安全 Token
$token = SecurityHelper::generateSecureToken(32);
```

#### Token 過期時間

建議設定：

- Access Token: 60 分鐘
- Refresh Token: 14 天

```env
TOKEN_TTL=60
TOKEN_REFRESH_TTL=20160
```

#### Token 撤銷

當偵測到可疑活動時，立即撤銷 Token：

```php
$apiToken->revoke();
```

### 3. 多因素驗證（MFA）

建議為管理員帳號啟用 MFA：

- Google Authenticator
- SMS 驗證碼
- 郵件驗證碼

### 4. 權限最小化原則

- 只授予必要的權限
- 定期審查權限配置
- 使用角色管理權限

## 資料保護

### 1. 敏感資料加密

#### 資料庫加密

敏感欄位使用 Laravel 的加密功能：

```php
use Illuminate\Support\Facades\Crypt;

// 加密
$encrypted = Crypt::encryptString($sensitiveData);

// 解密
$decrypted = Crypt::decryptString($encrypted);
```

#### 傳輸加密

- 強制使用 HTTPS
- 啟用 HSTS
- 使用 TLS 1.2 或更高版本

### 2. 密碼儲存

永遠不要以明文儲存密碼：

```php
use Illuminate\Support\Facades\Hash;

// 雜湊密碼
$hashedPassword = Hash::make($password);

// 驗證密碼
if (Hash::check($password, $hashedPassword)) {
    // 密碼正確
}
```

### 3. API Key 保護

- 使用環境變數儲存 API Key
- 定期輪替 API Key
- 限制 API Key 的權限範圍

```env
# 不要將 API Key 提交到版本控制
API_KEY=your-secret-key-here
```

## 網路安全

### 1. HTTPS 配置

#### 強制 HTTPS

在 Nginx 配置中：

```nginx
server {
    listen 80;
    server_name api.example.com;
    return 301 https://$host$request_uri;
}
```

#### HSTS 設定

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
```

### 2. CORS 政策

限制允許的來源：

```env
# 只允許特定網域
CORS_ALLOWED_ORIGINS=https://admin.example.com,https://app.example.com

# 不要在 Production 使用 *
# CORS_ALLOWED_ORIGINS=*  # 危險！
```

### 3. IP 白名單

限制 Admin UI 的存取：

```env
# 只允許內部網路存取 Admin UI
IP_WHITELIST_ADMIN=192.168.1.0/24,10.0.0.0/8
```

在路由中使用：

```php
Route::middleware(['ip.whitelist:admin'])->group(function () {
    // Admin 路由
});
```

### 4. 防火牆規則

```bash
# 只開放必要的埠
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw deny 3306/tcp  # 禁止外部存取 MySQL
sudo ufw deny 6379/tcp  # 禁止外部存取 Redis
```

## 輸入驗證

### 1. 使用 SecureRequest

所有 API 請求都應該繼承 `SecureRequest`：

```php
use App\Http\Requests\SecureRequest;

class CreateFunctionRequest extends SecureRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-function');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|unique:api_functions',
            'description' => 'nullable|string',
        ];
    }
}
```

### 2. SQL Injection 防護

#### 使用參數綁定

```php
// 正確 ✓
DB::select('SELECT * FROM users WHERE email = ?', [$email]);

// 錯誤 ✗
DB::select("SELECT * FROM users WHERE email = '$email'");
```

#### 驗證 SQL 參數

```php
use App\Helpers\SecurityHelper;

if (!SecurityHelper::validateSqlParameter($value, 'string')) {
    throw new ValidationException('可疑的輸入');
}
```

### 3. XSS 防護

#### 清理輸入

```php
use App\Helpers\SecurityHelper;

$cleanInput = SecurityHelper::sanitizeInput($userInput);
```

#### 輸出編碼

在 Blade 模板中：

```blade
{{-- 自動編碼 --}}
{{ $userInput }}

{{-- 不編碼（危險！只在確定安全時使用） --}}
{!! $trustedHtml !!}
```

### 4. CSRF 保護

Laravel 預設啟用 CSRF 保護，確保所有表單都包含 CSRF Token：

```blade
<form method="POST" action="/api/function">
    @csrf
    <!-- 表單欄位 -->
</form>
```

## 日誌和監控

### 1. 安全事件日誌

記錄所有安全相關事件：

```php
use App\Helpers\SecurityHelper;

SecurityHelper::logSecurityEvent('authentication_failed', [
    'client_id' => $clientId,
    'ip_address' => $request->ip(),
    'reason' => 'Invalid credentials',
]);
```

### 2. 監控異常活動

設定告警規則：

- 短時間內多次登入失敗
- 來自異常 IP 的請求
- 超過速率限制的請求
- SQL Injection 嘗試
- XSS 攻擊嘗試

### 3. 日誌保護

- 不要在日誌中記錄敏感資訊（密碼、Token、信用卡號）
- 定期輪替日誌檔案
- 限制日誌檔案的存取權限

```bash
chmod 640 storage/logs/*.log
chown www-data:www-data storage/logs/*.log
```

### 4. 審計追蹤

記錄所有重要操作：

- 配置變更
- 權限變更
- 使用者創建/刪除
- API Function 創建/修改/刪除

## 定期維護

### 1. 安全更新

#### 每週

```bash
# 更新系統套件
sudo apt-get update && sudo apt-get upgrade -y

# 更新 Composer 依賴
composer update --with-dependencies

# 檢查安全漏洞
composer audit
```

#### 每月

```bash
# 更新 Docker 映像
docker-compose pull
docker-compose up -d

# 檢查 Laravel 安全公告
# https://laravel.com/docs/security
```

### 2. 安全審計

#### 每季

- 審查使用者權限
- 審查 API 存取日誌
- 檢查異常活動
- 更新安全政策

#### 每年

- 進行滲透測試
- 進行程式碼安全審查
- 更新災難恢復計畫
- 進行安全培訓

### 3. 備份驗證

定期測試備份恢復：

```bash
# 測試資料庫備份恢復
gunzip < backup_20251021.sql.gz | docker exec -i api-manager-mysql-prod mysql -uroot -p api_manager_test
```

### 4. 憑證管理

#### SSL 憑證

```bash
# 檢查憑證有效期
openssl x509 -in docker/nginx/ssl/cert.pem -noout -dates

# 設定自動更新（Let's Encrypt）
sudo certbot renew --dry-run
```

#### API Key 輪替

建議每 90 天輪替一次 API Key：

```php
// 生成新的 API Key
$newApiKey = SecurityHelper::generateSecureToken(32);

// 更新客戶端
$client->update(['api_key' => $newApiKey]);

// 通知客戶端
Mail::to($client->email)->send(new ApiKeyRotated($newApiKey));
```

## 安全檢查清單

### 部署前

- [ ] 已設定強密碼
- [ ] 已啟用 HTTPS
- [ ] 已配置 HSTS
- [ ] 已設定 CORS 政策
- [ ] 已配置防火牆
- [ ] 已設定 IP 白名單
- [ ] 已關閉 Debug 模式
- [ ] 已移除預設帳號
- [ ] 已設定安全標頭
- [ ] 已配置日誌監控

### 運行中

- [ ] 定期更新系統和依賴
- [ ] 定期審查日誌
- [ ] 定期審查權限
- [ ] 定期輪替憑證
- [ ] 定期備份資料
- [ ] 定期測試恢復
- [ ] 監控異常活動
- [ ] 回應安全事件

## 安全事件回應

### 1. 偵測到攻擊

1. 立即封鎖攻擊來源 IP
2. 記錄所有相關資訊
3. 通知安全團隊
4. 分析攻擊模式

### 2. 資料洩露

1. 立即撤銷所有受影響的憑證
2. 通知受影響的使用者
3. 進行完整的安全審計
4. 修補漏洞
5. 更新安全政策

### 3. 服務中斷

1. 啟動災難恢復計畫
2. 從備份恢復資料
3. 分析中斷原因
4. 實施預防措施

## 聯絡資訊

### 安全問題回報

如發現安全漏洞，請立即聯絡：

- 安全團隊：security@example.com
- 緊急聯絡：+886-xxx-xxx-xxx

### 安全資源

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security](https://laravel.com/docs/security)
- [PHP Security](https://www.php.net/manual/en/security.php)
- [Docker Security](https://docs.docker.com/engine/security/)

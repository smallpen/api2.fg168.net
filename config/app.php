<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | 此值是應用程式的名稱。這個值會在框架需要將應用程式名稱放置在
    | 通知或其他 UI 元素中時使用。
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | 此值決定應用程式目前運行的「環境」。這可能會決定您希望如何為
    | 應用程式配置各種服務。在 ".env" 檔案中設定此值。
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | 當應用程式處於除錯模式時，詳細的錯誤訊息會顯示在每個錯誤發生時。
    | 如果停用，將顯示一個簡單的通用錯誤頁面。
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | 此 URL 會被控制台用來正確產生 URL。您應該將此設定為應用程式的根目錄，
    | 以便在使用 Artisan 命令列工具時正確使用。
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | 在這裡您可以為應用程式指定預設時區，這將被 PHP 日期和日期時間
    | 函數使用。我們已經為您設定了一個合理的預設值。
    |
    */

    'timezone' => 'Asia/Taipei',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | 應用程式語言環境決定了翻譯服務提供者將使用的預設語言環境。
    | 您可以自由地將此值設定為應用程式支援的任何語言環境。
    |
    */

    'locale' => 'zh_TW',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | 備用語言環境決定了當目前語言環境不可用時將使用的語言環境。
    | 您可以將其更改為應用程式支援的任何語言環境。
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | 此語言環境將被 Faker PHP 函式庫使用，當產生假資料用於資料庫種子時。
    | 例如，這將用於取得本地化的電話號碼、街道地址資訊等。
    |
    */

    'faker_locale' => 'zh_TW',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | 此金鑰會被 Illuminate 加密服務使用，應該設定為 32 個字元的隨機字串，
    | 否則這些加密字串將不安全。請在部署應用程式之前執行此操作！
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | 這些配置選項決定了用於儲存和檢索有關應用程式維護模式狀態資訊的驅動程式
    | 和儲存位置。
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | 此處列出的服務提供者將自動載入到您的應用程式中。您可以自由地將
    | 自己的服務新增到此陣列中，以授予應用程式擴充功能。
    |
    */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\AuthenticationServiceProvider::class,
        App\Providers\AuthorizationServiceProvider::class,
        App\Providers\RateLimitServiceProvider::class,
        App\Providers\ConfigurationServiceProvider::class,
        App\Providers\DatabaseServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | 此別名陣列將在載入此應用程式時註冊。您可以自由地新增自己的別名到
    | 此陣列中。這些別名會被延遲載入，因此不會影響效能。
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'ExampleClass' => App\Example\ExampleClass::class,
    ])->toArray(),

];

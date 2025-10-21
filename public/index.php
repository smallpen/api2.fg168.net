<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| 如果應用程式處於維護模式，我們將載入這個檔案，以便可以顯示
| 適當的訊息給使用者。這個檔案會在應用程式處於維護模式時自動載入。
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer 提供了一個方便的自動載入器，可以自動載入我們的類別。
| 我們只需要使用它！我們只需要引入檔案，然後就可以開始使用類別了。
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| 一旦我們有了應用程式，我們就可以處理傳入的請求，並將回應發送回
| 客戶端的瀏覽器，讓他們享受我們為他們準備的創意和快樂應用程式。
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);

<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| 首先我們需要取得一個 Laravel 應用程式實例。這會建立應用程式的實例
| 並綁定所有的服務容器，這是應用程式的「黏合劑」。
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| 接下來，我們需要綁定一些重要的介面到容器中，這樣我們就可以在需要時
| 解析它們。這些核心介面是應用程式運作的基礎。
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| 這個腳本會回傳應用程式實例。這個實例會被 CLI 或 Web 請求使用，
| 然後我們就可以開始處理請求了。
|
*/

return $app;

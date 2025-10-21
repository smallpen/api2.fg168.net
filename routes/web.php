<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| 在這裡註冊應用程式的 Web 路由。這些路由會被 RouteServiceProvider
| 載入，並且會自動套用 "web" 中介軟體群組。
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to Dynamic API Manager',
        'version' => '1.0.0',
        'documentation' => '/api/docs',
    ]);
});

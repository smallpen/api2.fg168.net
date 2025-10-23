<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>{{ config('app.name', 'Dynamic API Manager') }} - 管理介面</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body {
            margin: 0;
            padding: 0;
            /* 防止在移動裝置上的橫向滾動 */
            overflow-x: hidden;
            /* 優化觸控滾動 */
            -webkit-overflow-scrolling: touch;
        }
        #app {
            min-height: 100vh;
            /* 防止內容溢出 */
            overflow-x: hidden;
        }
        /* 優化移動裝置上的點擊反應 */
        * {
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div id="app"></div>
</body>
</html>

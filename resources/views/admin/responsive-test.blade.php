<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>響應式設計測試頁面</title>
    
    @vite(['resources/css/app.css'])
    
    <style>
        body {
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .test-section {
            margin-bottom: 40px;
        }
        
        .test-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1f2937;
        }
        
        .screen-info {
            position: fixed;
            top: 10px;
            right: 10px;
            background-color: #3b82f6;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            z-index: 9999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .device-indicator {
            display: none;
        }
        
        @media (max-width: 640px) {
            .device-indicator.mobile { display: inline; }
        }
        
        @media (min-width: 641px) and (max-width: 1024px) {
            .device-indicator.tablet { display: inline; }
        }
        
        @media (min-width: 1025px) {
            .device-indicator.desktop { display: inline; }
        }
    </style>
</head>
<body>
    <div class="screen-info">
        <div>螢幕寬度: <span id="screenWidth"></span>px</div>
        <div>裝置類型: 
            <span class="device-indicator mobile">📱 手機</span>
            <span class="device-indicator tablet">📱 平板</span>
            <span class="device-indicator desktop">💻 桌面</span>
        </div>
    </div>

    <h1 style="margin-bottom: 30px;">響應式設計測試頁面</h1>

    <!-- 網格系統測試 -->
    <div class="test-section">
        <h2 class="test-title">網格系統測試</h2>
        
        <h3>4 欄網格（桌面 4 欄 / 平板 2 欄 / 手機 1 欄）</h3>
        <div class="grid grid-cols-4" style="margin-bottom: 20px;">
            <div class="stat-card">
                <div class="stat-value">123</div>
                <div class="stat-label">統計 1</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">456</div>
                <div class="stat-label">統計 2</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">789</div>
                <div class="stat-label">統計 3</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">012</div>
                <div class="stat-label">統計 4</div>
            </div>
        </div>

        <h3>3 欄網格（桌面 3 欄 / 平板 2 欄 / 手機 1 欄）</h3>
        <div class="grid grid-cols-3" style="margin-bottom: 20px;">
            <div class="card">
                <div class="card-header">卡片 1</div>
                <div class="card-body">這是卡片內容</div>
            </div>
            <div class="card">
                <div class="card-header">卡片 2</div>
                <div class="card-body">這是卡片內容</div>
            </div>
            <div class="card">
                <div class="card-header">卡片 3</div>
                <div class="card-body">這是卡片內容</div>
            </div>
        </div>
    </div>

    <!-- 按鈕測試 -->
    <div class="test-section">
        <h2 class="test-title">按鈕測試</h2>
        
        <div class="card">
            <h3>一般按鈕</h3>
            <div class="flex flex-wrap gap-3" style="margin-bottom: 20px;">
                <button class="btn btn-primary">主要按鈕</button>
                <button class="btn btn-secondary">次要按鈕</button>
                <button class="btn btn-success">成功按鈕</button>
                <button class="btn btn-danger">危險按鈕</button>
            </div>

            <h3>手機全寬按鈕</h3>
            <div class="flex flex-col-mobile gap-3">
                <button class="btn btn-primary btn-block-mobile">主要操作</button>
                <button class="btn btn-secondary btn-block-mobile">次要操作</button>
            </div>
        </div>
    </div>

    <!-- 表格測試 -->
    <div class="test-section">
        <h2 class="test-title">表格測試</h2>
        
        <div class="card">
            <div class="card-header">響應式表格</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>名稱</th>
                                <th>電子郵件</th>
                                <th class="hide-mobile">建立時間</th>
                                <th>狀態</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>張三</td>
                                <td>zhang@example.com</td>
                                <td class="hide-mobile">2024-01-01</td>
                                <td><span style="color: #10b981;">啟用</span></td>
                                <td>
                                    <button class="btn btn-primary btn-sm">編輯</button>
                                </td>
                            </tr>
                            <tr>
                                <td>李四</td>
                                <td>li@example.com</td>
                                <td class="hide-mobile">2024-01-02</td>
                                <td><span style="color: #ef4444;">停用</span></td>
                                <td>
                                    <button class="btn btn-primary btn-sm">編輯</button>
                                </td>
                            </tr>
                            <tr>
                                <td>王五</td>
                                <td>wang@example.com</td>
                                <td class="hide-mobile">2024-01-03</td>
                                <td><span style="color: #10b981;">啟用</span></td>
                                <td>
                                    <button class="btn btn-primary btn-sm">編輯</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 表單測試 -->
    <div class="test-section">
        <h2 class="test-title">表單測試</h2>
        
        <div class="card">
            <div class="card-header">響應式表單</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">使用者名稱</label>
                    <input type="text" class="form-control" placeholder="請輸入使用者名稱">
                </div>

                <div class="form-group">
                    <label class="form-label">電子郵件</label>
                    <input type="email" class="form-control" placeholder="請輸入電子郵件">
                </div>

                <div class="form-group">
                    <label class="form-label">描述</label>
                    <textarea class="form-control" rows="4" placeholder="請輸入描述"></textarea>
                </div>

                <div class="flex flex-col-mobile gap-3">
                    <button class="btn btn-primary btn-block-mobile">提交</button>
                    <button class="btn btn-secondary btn-block-mobile">取消</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 工具類別測試 -->
    <div class="test-section">
        <h2 class="test-title">顯示/隱藏工具類別測試</h2>
        
        <div class="card">
            <div class="alert alert-info hide-mobile">
                這個訊息在手機上會隱藏（使用 .hide-mobile）
            </div>
            
            <div class="alert alert-success hide-tablet">
                這個訊息在平板及以下裝置會隱藏（使用 .hide-tablet）
            </div>
            
            <div class="alert alert-warning show-mobile-only">
                這個訊息只在手機上顯示（使用 .show-mobile-only）
            </div>
        </div>
    </div>

    <script>
        // 顯示螢幕寬度
        function updateScreenWidth() {
            document.getElementById('screenWidth').textContent = window.innerWidth;
        }
        
        updateScreenWidth();
        window.addEventListener('resize', updateScreenWidth);
    </script>
</body>
</html>

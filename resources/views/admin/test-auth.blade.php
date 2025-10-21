<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>認證測試</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .result {
            background: #f5f5f5;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        button {
            padding: 10px 20px;
            margin: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>認證狀態測試</h1>
    
    <div>
        <p><strong>當前使用者：</strong> {{ Auth::check() ? Auth::user()->name : '未登入' }}</p>
        <p><strong>使用者 ID：</strong> {{ Auth::check() ? Auth::user()->id : 'N/A' }}</p>
        <p><strong>是否為管理員：</strong> {{ Auth::check() && Auth::user()->isAdmin() ? '是' : '否' }}</p>
    </div>

    <hr>

    <h2>API 測試</h2>
    
    <button onclick="testWebRoute()">測試 Web 路由 (/admin/dashboard)</button>
    <button onclick="testWebAuthStatus()">測試 Web 認證狀態 (/admin/api/auth-status)</button>
    <button onclick="testApiAuthStatus()">測試 API 認證狀態 (/api/admin/test-auth)</button>
    <button onclick="testApiRoute()">測試 API 路由 (/api/admin/clients)</button>
    <button onclick="testCsrfCookie()">測試 CSRF Cookie</button>
    <button onclick="clearResults()">清除結果</button>
    
    <div id="results"></div>

    <script>
        const resultsDiv = document.getElementById('results');
        
        function addResult(title, data, isSuccess) {
            const div = document.createElement('div');
            div.className = 'result ' + (isSuccess ? 'success' : 'error');
            div.innerHTML = `<h3>${title}</h3><pre>${JSON.stringify(data, null, 2)}</pre>`;
            resultsDiv.appendChild(div);
        }

        async function testWebRoute() {
            try {
                const response = await fetch('/admin/dashboard', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin'
                });
                const data = await response.json();
                addResult('Web 路由測試', { status: response.status, data }, response.ok);
            } catch (error) {
                addResult('Web 路由測試', { error: error.message }, false);
            }
        }

        async function testApiRoute() {
            try {
                const response = await fetch('/api/admin/clients', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include'
                });
                const data = await response.json();
                addResult('API 路由測試', { status: response.status, data }, response.ok);
            } catch (error) {
                addResult('API 路由測試', { error: error.message }, false);
            }
        }

        async function testWebAuthStatus() {
            try {
                const response = await fetch('/admin/api/auth-status', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin'
                });
                const data = await response.json();
                addResult('Web 認證狀態測試', { status: response.status, data }, response.ok);
            } catch (error) {
                addResult('Web 認證狀態測試', { error: error.message }, false);
            }
        }

        async function testApiAuthStatus() {
            try {
                const response = await fetch('/api/admin/test-auth', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include'
                });
                const data = await response.json();
                addResult('API 認證狀態測試', { status: response.status, data }, response.ok);
            } catch (error) {
                addResult('API 認證狀態測試', { error: error.message }, false);
            }
        }

        async function testCsrfCookie() {
            try {
                const response = await fetch('/sanctum/csrf-cookie', {
                    credentials: 'include'
                });
                addResult('CSRF Cookie 測試', { 
                    status: response.status,
                    cookies: document.cookie 
                }, response.ok);
            } catch (error) {
                addResult('CSRF Cookie 測試', { error: error.message }, false);
            }
        }

        function clearResults() {
            resultsDiv.innerHTML = '';
        }
    </script>
</body>
</html>

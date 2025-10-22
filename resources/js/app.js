import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import axios from 'axios';
import 'sweetalert2/dist/sweetalert2.min.css';

// 導入根元件
import App from './App.vue';

// 導入元件
import AdminLayout from './components/AdminLayout.vue';
import Dashboard from './pages/Dashboard.vue';
import FunctionList from './pages/FunctionList.vue';
import FunctionEditor from './pages/FunctionEditor.vue';
import LogViewer from './pages/LogViewer.vue';
import ClientManager from './pages/ClientManager.vue';

// 配置 Axios
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

// Axios 會自動從 cookie 中讀取 XSRF-TOKEN 並設定到 X-XSRF-TOKEN header
// 這是 Sanctum 的標準做法
axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

// 設定回應攔截器，處理 401 錯誤
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // 如果收到 401 錯誤，重新導向到登入頁面
            console.error('認證失敗，請重新登入');
            window.location.href = '/admin/login';
        }
        return Promise.reject(error);
    }
);

// 建立路由
const router = createRouter({
    history: createWebHistory('/admin'),
    routes: [
        {
            path: '/',
            component: AdminLayout,
            children: [
                {
                    path: '',
                    name: 'dashboard',
                    component: Dashboard,
                },
                {
                    path: 'dashboard',
                    redirect: '/',
                },
                {
                    path: 'functions',
                    name: 'functions',
                    component: FunctionList,
                },
                {
                    path: 'functions/:id',
                    name: 'function-editor',
                    component: FunctionEditor,
                },
                {
                    path: 'logs',
                    name: 'logs',
                    component: LogViewer,
                },
                {
                    path: 'clients',
                    name: 'clients',
                    component: ClientManager,
                },
            ],
        },
    ],
});

// 建立 Vue 應用程式
const app = createApp(App);

// 全域屬性
app.config.globalProperties.$axios = axios;

// 錯誤處理
app.config.errorHandler = (err, instance, info) => {
    console.error('Vue 錯誤:', err);
    console.error('錯誤資訊:', info);
};

// 使用路由
app.use(router);

// 路由錯誤處理
router.onError((error) => {
    console.error('路由錯誤:', error);
});

// 等待路由準備完成後再掛載
router.isReady().then(() => {
    app.mount('#app');
});

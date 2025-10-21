import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import axios from 'axios';

// 導入元件
import AdminLayout from './components/AdminLayout.vue';
import Dashboard from './pages/Dashboard.vue';
import FunctionList from './pages/FunctionList.vue';
import FunctionEditor from './pages/FunctionEditor.vue';
import LogViewer from './pages/LogViewer.vue';

// 配置 Axios
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

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
                // 預留給後續任務的路由
                // {
                //     path: 'clients',
                //     name: 'clients',
                //     component: () => import('./pages/Clients.vue'),
                // },
            ],
        },
    ],
});

// 建立 Vue 應用程式
const app = createApp({});

// 全域屬性
app.config.globalProperties.$axios = axios;

// 使用路由
app.use(router);

// 掛載應用程式
app.mount('#app');

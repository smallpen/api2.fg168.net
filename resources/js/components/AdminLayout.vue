<template>
  <div class="admin-layout">
    <!-- 側邊欄 -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <h1 class="sidebar-title">Dynamic API Manager</h1>
      </div>
      
      <nav class="sidebar-nav">
        <router-link to="/" class="nav-item" active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span class="nav-text">儀表板</span>
        </router-link>

        <router-link to="/functions" class="nav-item" active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
          </svg>
          <span class="nav-text">API Functions</span>
        </router-link>

        <router-link to="/logs" class="nav-item" active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span class="nav-text">日誌查詢</span>
        </router-link>

        <router-link to="/clients" class="nav-item" active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <span class="nav-text">客戶端管理</span>
        </router-link>
      </nav>
    </aside>

    <!-- 主要內容區 -->
    <div class="main-content">
      <!-- 頂部導航欄 -->
      <header class="topbar">
        <div class="topbar-left">
          <h2 class="page-title">{{ pageTitle }}</h2>
        </div>
        <div class="topbar-right">
          <div class="user-menu">
            <span class="user-name">{{ userName }}</span>
            <button @click="logout" class="btn btn-secondary btn-sm">登出</button>
          </div>
        </div>
      </header>

      <!-- 頁面內容 -->
      <main class="content">
        <router-view></router-view>
      </main>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdminLayout',
  data() {
    return {
      userName: 'Admin User', // TODO: 從後端取得實際使用者名稱
    };
  },

  computed: {
    pageTitle() {
      const routeTitles = {
        'dashboard': '儀表板',
        'functions': 'API Functions',
        'clients': '客戶端管理',
        'logs': '日誌查詢',
      };
      return routeTitles[this.$route.name] || '管理介面';
    },
  },
  methods: {
    async logout() {
      try {
        await this.$axios.post('/admin/logout');
        window.location.href = '/admin/login';
      } catch (error) {
        console.error('登出失敗:', error);
        alert('登出失敗，請稍後再試');
      }
    },
  },
};
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
}

/* 側邊欄樣式 */
.sidebar {
  width: 250px;
  background-color: #1f2937;
  color: white;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  padding: 20px;
  border-bottom: 1px solid #374151;
}

.sidebar-title {
  font-size: 18px;
  font-weight: 700;
  margin: 0;
}

.sidebar-nav {
  flex: 1;
  padding: 20px 0;
}

.nav-item {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: #d1d5db;
  text-decoration: none;
  transition: all 0.2s;
}

.nav-item:hover {
  background-color: #374151;
  color: white;
}

.nav-item.active {
  background-color: #3b82f6;
  color: white;
}

.nav-icon {
  width: 20px;
  height: 20px;
  margin-right: 10px;
}

.nav-text {
  font-size: 14px;
  font-weight: 500;
}

/* 主要內容區樣式 */
.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  background-color: #f5f5f5;
}

/* 頂部導航欄樣式 */
.topbar {
  height: 60px;
  background-color: white;
  border-bottom: 1px solid #e5e5e5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 30px;
}

.topbar-left {
  flex: 1;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  margin: 0;
}

.topbar-right {
  display: flex;
  align-items: center;
}

.user-menu {
  display: flex;
  align-items: center;
  gap: 15px;
}

.user-name {
  font-size: 14px;
  color: #6b7280;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
}

/* 內容區樣式 */
.content {
  flex: 1;
  padding: 30px;
  overflow-y: auto;
}

/* 響應式設計 */
@media (max-width: 768px) {
  .sidebar {
    width: 200px;
  }

  .content {
    padding: 20px;
  }
}
</style>

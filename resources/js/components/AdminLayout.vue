<template>
  <div class="admin-layout">
    <!-- 側邊欄 -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <h1 class="sidebar-title">會員系統 API 控制台</h1>
      </div>
      
      <nav class="sidebar-nav">
        <router-link to="/" class="nav-item" exact-active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span class="nav-text">儀表板</span>
        </router-link>

        <router-link to="/functions" class="nav-item" exact-active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
          </svg>
          <span class="nav-text">API Functions</span>
        </router-link>

        <router-link to="/clients" class="nav-item" exact-active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <span class="nav-text">客戶端管理</span>
        </router-link>

        <router-link to="/permissions" class="nav-item" exact-active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
          <span class="nav-text">權限配置</span>
        </router-link>

        <router-link to="/users" class="nav-item" exact-active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <span class="nav-text">系統帳號管理</span>
        </router-link>

        <router-link to="/logs" class="nav-item" exact-active-class="active">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span class="nav-text">日誌查詢</span>
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
            <button @click="showProfileModal" class="user-profile-btn">
              <svg class="user-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <span class="user-name">{{ userName }}</span>
            </button>
            <button @click="logout" class="btn btn-secondary btn-sm">登出</button>
          </div>
        </div>
      </header>

      <!-- 頁面內容 -->
      <main class="content">
        <router-view></router-view>
      </main>
    </div>

    <!-- 個人資料編輯 Modal -->
    <ProfileModal
      :show="showProfile"
      :user="currentUser"
      @close="showProfile = false"
      @updated="handleProfileUpdated"
    />
  </div>
</template>

<script>
import ProfileModal from './ProfileModal.vue';

export default {
  name: 'AdminLayout',
  components: {
    ProfileModal,
  },
  data() {
    return {
      userName: 'Admin User',
      currentUser: null,
      showProfile: false,
    };
  },
  mounted() {
    this.loadUserProfile();
  },

  computed: {
    pageTitle() {
      const routeTitles = {
        'dashboard': '儀表板',
        'functions': 'API Functions',
        'clients': '客戶端管理',
        'logs': '日誌查詢',
        'users': '系統帳號管理',
        'permissions': '權限配置',
      };
      return routeTitles[this.$route.name] || '管理介面';
    },
  },
  methods: {
    async loadUserProfile() {
      try {
        const response = await this.$axios.get('/api/admin/profile');
        if (response.data.success) {
          this.currentUser = response.data.data;
          this.userName = this.currentUser.name;
        }
      } catch (error) {
        console.error('載入使用者資料失敗:', error);
      }
    },

    showProfileModal() {
      this.showProfile = true;
    },

    handleProfileUpdated(user) {
      this.currentUser = user;
      this.userName = user.name;
    },

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

.user-profile-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: none;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.user-profile-btn:hover {
  background-color: #f9fafb;
  border-color: #d1d5db;
}

.user-icon {
  width: 20px;
  height: 20px;
  color: #6b7280;
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

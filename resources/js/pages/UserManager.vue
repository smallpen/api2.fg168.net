<template>
  <div class="user-manager">
    <!-- 頁面標題和操作按鈕 -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">系統帳號管理</h1>
        <p class="page-description">管理系統管理員帳號和權限</p>
      </div>
      <div class="header-right">
        <button @click="showCreateModal" class="btn btn-primary">
          <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          新增使用者
        </button>
      </div>
    </div>

    <!-- 搜尋和篩選區 -->
    <div class="filter-section">
      <div class="filter-row">
        <div class="filter-item filter-search">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="搜尋使用者名稱或電子郵件..."
            class="search-input"
            @input="debouncedSearch"
          />
        </div>
      </div>
    </div>

    <!-- 載入中狀態 -->
    <div v-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p>載入中...</p>
    </div>

    <!-- 使用者列表 -->
    <div v-if="!loading" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>名稱</th>
            <th>電子郵件</th>
            <th>角色</th>
            <th>建立時間</th>
            <th class="actions-column">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="users.length === 0">
            <td colspan="5" class="empty-state">
              <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              <p>尚無使用者資料</p>
              <button @click="showCreateModal" class="btn btn-primary btn-sm">
                建立第一個使用者
              </button>
            </td>
          </tr>
          <tr v-for="user in users" :key="user.id" class="data-row">
            <td data-label="名稱">
              <strong>{{ user.name }}</strong>
            </td>
            <td data-label="電子郵件">{{ user.email }}</td>
            <td data-label="角色">
              <div class="roles-cell">
                <span v-if="user.admin_roles && user.admin_roles.length > 0" class="role-badge">
                  {{ user.admin_roles[0].display_name || user.admin_roles[0].name }}
                </span>
                <span v-else class="no-roles">無後台角色</span>
              </div>
            </td>
            <td data-label="建立時間">
              <span class="date-text">{{ formatDate(user.created_at) }}</span>
            </td>
            <td data-label="操作" class="actions-column">
              <div class="action-buttons">
                <button
                  @click="showEditModal(user)"
                  class="btn-action btn-action-primary"
                  title="編輯"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  <span class="btn-text">編輯</span>
                </button>
                
                <button
                  @click="confirmDelete(user)"
                  class="btn-action btn-action-danger"
                  title="刪除"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  <span class="btn-text">刪除</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- 分頁 -->
      <div v-if="pagination.total > 0" class="pagination">
        <div class="pagination-info">
          顯示 {{ pagination.from }} - {{ pagination.to }} 筆，共 {{ pagination.total }} 筆
        </div>
        <div class="pagination-controls">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="btn btn-secondary btn-sm"
          >
            上一頁
          </button>
          
          <span class="page-numbers">
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="changePage(page)"
              :class="['page-btn', { active: page === pagination.current_page }]"
            >
              {{ page }}
            </button>
          </span>
          
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="btn btn-secondary btn-sm"
          >
            下一頁
          </button>
        </div>
      </div>
    </div>

    <!-- 新增/編輯使用者 Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ isEditing ? '編輯使用者' : '新增使用者' }}</h2>
          <button @click="closeModal" class="btn-close">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="saveUser">
            <div class="form-group">
              <label>名稱 <span class="required">*</span></label>
              <input v-model="formData.name" type="text" class="form-input" required />
            </div>

            <div class="form-group">
              <label>電子郵件 <span class="required">*</span></label>
              <input v-model="formData.email" type="email" class="form-input" required />
              <small class="form-hint">此電子郵件將用於登入後台系統</small>
            </div>

            <div class="form-group">
              <label>密碼 <span v-if="!isEditing" class="required">*</span></label>
              <input
                v-model="formData.password"
                type="password"
                class="form-input"
                :required="!isEditing"
                :placeholder="isEditing ? '留空表示不修改密碼' : ''"
              />
            </div>

            <div class="form-group">
              <label>後台角色</label>
              <div v-if="availableAdminRoles.length > 0" class="radio-group">
                <label v-for="role in availableAdminRoles" :key="role.id" class="radio-label">
                  <input
                    type="radio"
                    :value="role.id"
                    v-model="formData.admin_role_id"
                    name="admin_role"
                  />
                  {{ role.display_name || role.name }}
                </label>
              </div>
              <div v-else class="no-data-hint">
                載入角色中...
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" @click="closeModal" class="btn btn-secondary">取消</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? '儲存中...' : '儲存' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { showConfirm, showSuccess, showError } from '../utils/sweetalert';

export default {
  name: 'UserManager',
  data() {
    return {
      users: [],
      loading: false,
      searchQuery: '',
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
      },
      showModal: false,
      isEditing: false,
      saving: false,
      formData: {
        id: null,
        name: '',
        email: '',
        password: '',
        admin_role_id: null,
      },
      availableAdminRoles: [],
      searchTimeout: null,
    };
  },
  computed: {
    visiblePages() {
      const current = this.pagination.current_page;
      const last = this.pagination.last_page;
      const delta = 2;
      const pages = [];

      for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
        pages.push(i);
      }

      return pages;
    },
  },
  mounted() {
    this.loadUsers();
    this.loadAdminRoles();
  },
  methods: {
    async loadUsers(page = 1) {
      this.loading = true;
      try {
        const response = await this.$axios.get('/api/admin/users', {
          params: {
            page,
            search: this.searchQuery,
            per_page: this.pagination.per_page,
          },
        });

        if (response.data.success) {
          this.users = response.data.data.data;
          this.pagination = {
            current_page: response.data.data.current_page,
            last_page: response.data.data.last_page,
            per_page: response.data.data.per_page,
            total: response.data.data.total,
            from: (response.data.data.current_page - 1) * response.data.data.per_page + 1,
            to: Math.min(response.data.data.current_page * response.data.data.per_page, response.data.data.total),
          };
        }
      } catch (error) {
        console.error('載入使用者列表失敗:', error);
        showError('載入使用者列表失敗');
      } finally {
        this.loading = false;
      }
    },

    async loadAdminRoles() {
      try {
        const response = await this.$axios.get('/api/admin/admin-roles');
        if (response.data.success) {
          this.availableAdminRoles = response.data.data;
        }
      } catch (error) {
        console.error('載入後台角色列表失敗:', error);
      }
    },

    debouncedSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.loadUsers(1);
      }, 500);
    },

    changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.loadUsers(page);
      }
    },

    showCreateModal() {
      this.isEditing = false;
      this.formData = {
        id: null,
        name: '',
        email: '',
        password: '',
        admin_role_id: null,
      };
      this.showModal = true;
    },

    showEditModal(user) {
      this.isEditing = true;
      this.formData = {
        id: user.id,
        name: user.name,
        email: user.email,
        password: '',
        admin_role_id: user.admin_roles && user.admin_roles.length > 0 ? user.admin_roles[0].id : null,
      };
      this.showModal = true;
    },

    closeModal() {
      this.showModal = false;
      this.formData = {
        id: null,
        name: '',
        email: '',
        password: '',
        admin_role_id: null,
      };
    },

    async saveUser() {
      this.saving = true;
      try {
        const data = {
          name: this.formData.name,
          email: this.formData.email,
          admin_role_id: this.formData.admin_role_id,
        };

        if (this.formData.password) {
          data.password = this.formData.password;
        }

        let response;
        if (this.isEditing) {
          response = await this.$axios.put(`/api/admin/users/${this.formData.id}`, data);
        } else {
          response = await this.$axios.post('/api/admin/users', data);
        }

        if (response.data.success) {
          showSuccess(this.isEditing ? '使用者更新成功' : '使用者建立成功');
          this.closeModal();
          this.loadUsers(this.pagination.current_page);
        }
      } catch (error) {
        console.error('儲存使用者失敗:', error);
        const message = error.response?.data?.message || '儲存失敗';
        showError(message);
      } finally {
        this.saving = false;
      }
    },

    async confirmDelete(user) {
      const confirmed = await showConfirm(
        '確定要刪除此使用者嗎？',
        `使用者：${user.name} (${user.email})`
      );

      if (confirmed) {
        try {
          const response = await this.$axios.delete(`/api/admin/users/${user.id}`);
          if (response.data.success) {
            showSuccess('使用者已刪除');
            this.loadUsers(this.pagination.current_page);
          }
        } catch (error) {
          console.error('刪除使用者失敗:', error);
          const message = error.response?.data?.message || '刪除失敗';
          showError(message);
        }
      }
    },

    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleString('zh-TW');
    },
  },
};
</script>

<style scoped>
/* 頁面標題區 */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 30px;
}

.header-left {
  flex: 1;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  margin: 0 0 8px 0;
  color: #111827;
}

.page-description {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.header-right {
  display: flex;
  gap: 10px;
}

/* 篩選區 */
.filter-section {
  background: white;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filter-row {
  display: flex;
  gap: 12px;
  align-items: center;
}

.filter-item {
  flex-shrink: 0;
}

.filter-search {
  flex: 1;
  min-width: 300px;
}

.search-input {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
}

.search-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* 載入中 */
.loading-container {
  text-align: center;
  padding: 60px 20px;
}

.spinner {
  width: 40px;
  height: 40px;
  margin: 0 auto 15px;
  border: 4px solid #f3f4f6;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* 表格容器 */
.table-container {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

/* 資料表格 */
.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead {
  background-color: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.data-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.data-table td {
  padding: 16px;
  border-bottom: 1px solid #f3f4f6;
  font-size: 14px;
}

.data-row:hover {
  background-color: #f9fafb;
}

.actions-column {
  width: 200px;
  text-align: right;
  white-space: nowrap;
}

/* 角色顯示 */
.roles-cell {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.role-badge {
  display: inline-block;
  padding: 4px 10px;
  background-color: #dbeafe;
  color: #1e40af;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.no-roles {
  color: #9ca3af;
  font-size: 13px;
}

/* 日期文字 */
.date-text {
  color: #6b7280;
  font-size: 13px;
}

/* 操作按鈕 */
.action-buttons {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
  flex-wrap: nowrap;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  border: 1px solid transparent;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
}

.btn-action .icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.btn-action .btn-text {
  display: inline-block;
}

.btn-action-primary {
  color: #3b82f6;
  background-color: #eff6ff;
  border-color: #bfdbfe;
}

.btn-action-primary:hover {
  background-color: #dbeafe;
  border-color: #93c5fd;
}

.btn-action-danger {
  color: #ef4444;
  background-color: #fef2f2;
  border-color: #fecaca;
}

.btn-action-danger:hover {
  background-color: #fee2e2;
  border-color: #fca5a5;
}

/* 空狀態 */
.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 15px;
  color: #9ca3af;
}

.empty-state p {
  color: #6b7280;
  margin-bottom: 20px;
}

/* 分頁 */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-top: 1px solid #e5e7eb;
}

.pagination-info {
  font-size: 14px;
  color: #6b7280;
}

.pagination-controls {
  display: flex;
  gap: 10px;
  align-items: center;
}

.page-numbers {
  display: flex;
  gap: 5px;
}

.page-btn {
  padding: 6px 12px;
  border: 1px solid #d1d5db;
  background-color: white;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.page-btn:hover {
  background-color: #f3f4f6;
}

.page-btn.active {
  background-color: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

/* 按鈕樣式 */
.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background-color: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background-color: #2563eb;
}

.btn-secondary {
  background-color: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover {
  background-color: #e5e7eb;
}

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
}

.btn-icon {
  width: 16px;
  height: 16px;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.btn-close {
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-close:hover {
  color: #374151;
}

.btn-close .icon {
  width: 20px;
  height: 20px;
}

.modal-body {
  padding: 20px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
}

/* 表單 */
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.required {
  color: #ef4444;
}

.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
}

.form-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.radio-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.radio-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: normal;
  cursor: pointer;
}

.radio-label input[type="radio"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.no-data-hint {
  color: #6b7280;
  font-size: 14px;
  padding: 10px;
  background-color: #f9fafb;
  border-radius: 4px;
}

.form-hint {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: #6b7280;
}

/* 響應式設計 - 平板 */
@media (max-width: 1024px) {
  .page-header {
    flex-direction: column;
    gap: 15px;
    align-items: stretch;
  }

  .header-right {
    width: 100%;
  }

  .header-right .btn {
    width: 100%;
    justify-content: center;
  }

  .btn-action .btn-text {
    display: none;
  }
  
  .btn-action {
    padding: 6px;
  }
  
  .actions-column {
    width: 100px;
  }
}

/* 響應式設計 - 手機 */
@media (max-width: 768px) {
  .page-title {
    font-size: 20px;
  }

  .page-description {
    font-size: 13px;
  }

  .filter-section {
    padding: 15px;
  }

  .filter-search {
    min-width: 100%;
  }

  /* 隱藏表格，改用卡片式佈局 */
  .table-container {
    overflow: visible;
  }

  .data-table thead {
    display: none;
  }

  .data-table,
  .data-table tbody,
  .data-table tr,
  .data-table td {
    display: block;
    width: 100%;
  }

  .data-row {
    margin-bottom: 15px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    background-color: white;
  }

  .data-row:hover {
    background-color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  .data-table td {
    padding: 8px 0;
    border-bottom: none;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
  }

  .data-table td::before {
    content: attr(data-label);
    font-weight: 600;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    flex-shrink: 0;
    width: 100px;
  }

  .data-table td:last-child {
    padding-top: 12px;
    margin-top: 12px;
    border-top: 1px solid #f3f4f6;
  }

  .actions-column {
    width: 100%;
    text-align: left;
  }

  .action-buttons {
    width: 100%;
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .btn-action {
    flex: 1;
    min-width: calc(50% - 3px);
    justify-content: center;
  }

  .btn-action .btn-text {
    display: inline-block !important;
  }

  .roles-cell {
    flex: 1;
    justify-content: flex-end;
  }

  .pagination {
    flex-direction: column;
    gap: 15px;
    align-items: stretch;
  }

  .pagination-info {
    text-align: center;
  }

  .pagination-controls {
    flex-direction: column;
    gap: 10px;
  }

  .page-numbers {
    order: -1;
    justify-content: center;
    flex-wrap: wrap;
  }

  .pagination-controls > .btn {
    width: 100%;
    justify-content: center;
  }

  /* Modal 優化 */
  .modal-content {
    width: 95%;
    max-height: 95vh;
  }

  .modal-header {
    padding: 15px;
  }

  .modal-header h2 {
    font-size: 16px;
  }

  .modal-body {
    padding: 15px;
  }

  .form-group {
    margin-bottom: 15px;
  }

  .form-group label {
    font-size: 13px;
  }

  .form-input {
    padding: 8px 10px;
    font-size: 13px;
  }

  .form-hint {
    font-size: 11px;
  }

  .modal-footer {
    flex-direction: column-reverse;
    gap: 8px;
    padding-top: 15px;
  }

  .modal-footer .btn {
    width: 100%;
    justify-content: center;
  }

  .radio-group {
    gap: 8px;
  }

  .radio-label {
    font-size: 13px;
  }
}

/* 響應式設計 - 小型手機 */
@media (max-width: 640px) {
  .page-title {
    font-size: 18px;
  }

  .filter-section {
    padding: 12px;
  }

  .data-row {
    padding: 12px;
  }

  .data-table td {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .data-table td::before {
    width: 100%;
  }

  .roles-cell {
    width: 100%;
    justify-content: flex-start;
  }

  .action-buttons {
    gap: 8px;
  }

  .btn-action {
    min-width: 100%;
    padding: 10px;
  }

  .page-btn {
    padding: 8px 12px;
    font-size: 13px;
  }

  .modal-header {
    padding: 12px;
  }

  .modal-header h2 {
    font-size: 15px;
  }

  .modal-body {
    padding: 12px;
  }

  .form-group {
    margin-bottom: 12px;
  }

  .form-group label {
    font-size: 12px;
    margin-bottom: 6px;
  }

  .form-input {
    padding: 7px 9px;
    font-size: 12px;
  }

  .form-hint {
    font-size: 10px;
    margin-top: 4px;
  }

  .radio-label {
    font-size: 12px;
  }

  .no-data-hint {
    font-size: 12px;
    padding: 8px;
  }
}
</style>

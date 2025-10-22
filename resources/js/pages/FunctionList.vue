<template>
  <div class="function-list">
    <!-- 頁面標題和操作按鈕 -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">API Functions 管理</h1>
        <p class="page-description">管理所有動態 API 端點配置</p>
      </div>
      <div class="header-right">
        <button @click="createFunction" class="btn btn-primary">
          <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          新增 Function
        </button>
      </div>
    </div>

    <!-- 搜尋和篩選區 -->
    <div class="filter-section">
      <div class="filter-row">
        <div class="filter-item filter-search">
          <input
            v-model="filters.search"
            type="text"
            placeholder="搜尋 Function 名稱、識別碼或描述..."
            class="search-input"
            @input="debouncedSearch"
          />
        </div>
        
        <div class="filter-item filter-select-item">
          <select v-model="filters.is_active" @change="loadFunctions" class="filter-select">
            <option value="">全部狀態</option>
            <option value="1">已啟用</option>
            <option value="0">已停用</option>
          </select>
        </div>

        <div class="filter-item filter-button">
          <button @click="resetFilters" class="btn btn-secondary">
            <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            重置篩選
          </button>
        </div>
      </div>
    </div>

    <!-- 載入中狀態 -->
    <div v-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p>載入中...</p>
    </div>

    <!-- 錯誤訊息 -->
    <div v-if="error" class="error-message">
      <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>{{ error }}</span>
    </div>

    <!-- Function 列表 -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>名稱</th>
            <th>識別碼</th>
            <th>Stored Procedure</th>
            <th>狀態</th>
            <th>參數數量</th>
            <th>建立時間</th>
            <th class="actions-column">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="functions.length === 0">
            <td colspan="7" class="empty-state">
              <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
              </svg>
              <p>尚無 API Function</p>
              <button @click="createFunction" class="btn btn-primary btn-sm">
                建立第一個 Function
              </button>
            </td>
          </tr>
          <tr v-for="func in functions" :key="func.id" class="data-row">
            <td>
              <div class="function-name">
                <strong>{{ func.name }}</strong>
                <span v-if="func.description" class="function-description">{{ func.description }}</span>
              </div>
            </td>
            <td>
              <code class="identifier-code">{{ func.identifier }}</code>
            </td>
            <td>
              <code class="sp-code">{{ func.stored_procedure }}</code>
            </td>
            <td>
              <span :class="['status-badge', func.is_active ? 'status-active' : 'status-inactive']">
                {{ func.is_active ? '已啟用' : '已停用' }}
              </span>
            </td>
            <td>
              <span class="param-count">{{ func.parameters_count || 0 }}</span>
            </td>
            <td>
              <span class="date-text">{{ formatDate(func.created_at) }}</span>
            </td>
            <td class="actions-column">
              <div class="action-buttons">
                <button
                  @click="editFunction(func)"
                  class="btn-action btn-action-primary"
                  title="編輯"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  <span class="btn-text">編輯</span>
                </button>
                
                <button
                  @click="toggleStatus(func)"
                  :class="['btn-action', func.is_active ? 'btn-action-warning' : 'btn-action-success']"
                  :title="func.is_active ? '停用' : '啟用'"
                >
                  <svg v-if="func.is_active" class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <svg v-else class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="btn-text">{{ func.is_active ? '停用' : '啟用' }}</span>
                </button>
                
                <button
                  @click="deleteFunction(func)"
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
            @click="goToPage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="btn btn-secondary btn-sm"
          >
            上一頁
          </button>
          
          <span class="page-numbers">
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="goToPage(page)"
              :class="['page-btn', { active: page === pagination.current_page }]"
            >
              {{ page }}
            </button>
          </span>
          
          <button
            @click="goToPage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="btn btn-secondary btn-sm"
          >
            下一頁
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { confirmWarning, error as showError, toast } from '../utils/sweetalert';

export default {
  name: 'FunctionList',
  data() {
    return {
      functions: [],
      loading: false,
      error: null,
      filters: {
        search: '',
        is_active: '',
      },
      pagination: {
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
        from: 0,
        to: 0,
      },
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
    this.loadFunctions();
  },
  methods: {
    /**
     * 載入 Functions 列表
     */
    async loadFunctions() {
      this.loading = true;
      this.error = null;

      try {
        const params = {
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
        };

        if (this.filters.search) {
          params.search = this.filters.search;
        }

        if (this.filters.is_active !== '') {
          params.is_active = this.filters.is_active;
        }

        const response = await this.$axios.get('/api/admin/functions', { params });

        if (response.data.success) {
          this.functions = response.data.data;
          this.pagination = {
            current_page: response.data.meta.current_page,
            per_page: response.data.meta.per_page,
            total: response.data.meta.total,
            last_page: response.data.meta.last_page,
            from: (response.data.meta.current_page - 1) * response.data.meta.per_page + 1,
            to: Math.min(response.data.meta.current_page * response.data.meta.per_page, response.data.meta.total),
          };
        } else {
          this.error = '載入 Functions 失敗';
        }
      } catch (err) {
        console.error('載入 Functions 失敗:', err);
        this.error = err.response?.data?.error?.message || '載入 Functions 失敗，請稍後再試';
      } finally {
        this.loading = false;
      }
    },

    /**
     * 防抖搜尋
     */
    debouncedSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.pagination.current_page = 1;
        this.loadFunctions();
      }, 500);
    },

    /**
     * 重置篩選條件
     */
    resetFilters() {
      this.filters = {
        search: '',
        is_active: '',
      };
      this.pagination.current_page = 1;
      this.loadFunctions();
    },

    /**
     * 切換頁面
     */
    goToPage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.pagination.current_page = page;
        this.loadFunctions();
      }
    },

    /**
     * 切換 Function 狀態
     */
    async toggleStatus(func) {
      const action = func.is_active ? '停用' : '啟用';
      
      const confirmed = await confirmWarning(
        `${action} Function`,
        `確定要${action} "${func.name}" 嗎？`,
        action,
        '取消'
      );
      
      if (!confirmed) {
        return;
      }

      try {
        const response = await this.$axios.post(`/api/admin/functions/${func.id}/toggle-status`);

        if (response.data.success) {
          func.is_active = response.data.data.is_active;
          toast(response.data.message, 'success');
        }
      } catch (err) {
        console.error('切換狀態失敗:', err);
        showError('操作失敗', err.response?.data?.error?.message || '切換狀態失敗，請稍後再試');
      }
    },

    /**
     * 建立新 Function
     */
    createFunction() {
      this.$router.push({ name: 'function-editor', params: { id: 'new' } });
    },

    /**
     * 編輯 Function
     */
    editFunction(func) {
      this.$router.push({ name: 'function-editor', params: { id: func.id } });
    },

    /**
     * 刪除 Function
     */
    async deleteFunction(func) {
      const confirmed = await confirmWarning(
        '刪除 Function',
        `確定要刪除 "${func.name}" 嗎？此操作無法復原。`,
        '刪除',
        '取消'
      );
      
      if (!confirmed) {
        return;
      }

      try {
        const response = await this.$axios.delete(`/api/admin/functions/${func.id}`);

        if (response.data.success) {
          toast(response.data.message, 'success');
          this.loadFunctions();
        }
      } catch (err) {
        console.error('刪除失敗:', err);
        showError('刪除失敗', err.response?.data?.error?.message || '刪除失敗，請稍後再試');
      }
    },

    /**
     * 格式化日期
     */
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
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

.filter-select-item {
  width: 160px;
}

.filter-button {
  width: auto;
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

.filter-select {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background-color: white;
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

/* 錯誤訊息 */
.error-message {
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #991b1b;
}

.error-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
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
  width: 280px;
  text-align: right;
  white-space: nowrap;
}

/* Function 名稱 */
.function-name {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.function-description {
  font-size: 12px;
  color: #6b7280;
}

/* 程式碼樣式 */
.identifier-code,
.sp-code {
  font-family: 'Courier New', monospace;
  font-size: 13px;
  background-color: #f3f4f6;
  padding: 4px 8px;
  border-radius: 4px;
  color: #1f2937;
}

/* 狀態標籤 */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.status-active {
  background-color: #d1fae5;
  color: #065f46;
}

.status-inactive {
  background-color: #fee2e2;
  color: #991b1b;
}

/* 參數數量 */
.param-count {
  display: inline-block;
  background-color: #dbeafe;
  color: #1e40af;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
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

.btn-action-warning {
  color: #f59e0b;
  background-color: #fffbeb;
  border-color: #fde68a;
}

.btn-action-warning:hover {
  background-color: #fef3c7;
  border-color: #fcd34d;
}

.btn-action-success {
  color: #10b981;
  background-color: #f0fdf4;
  border-color: #bbf7d0;
}

.btn-action-success:hover {
  background-color: #dcfce7;
  border-color: #86efac;
}

/* 響應式：在極小螢幕上隱藏按鈕文字 */
@media (max-width: 1024px) {
  .btn-action .btn-text {
    display: none;
  }
  
  .btn-action {
    padding: 6px;
  }
  
  .actions-column {
    width: 140px;
  }
}

/* 確保按鈕文字在正常螢幕上顯示 */
@media (min-width: 1025px) {
  .btn-action .btn-text {
    display: inline-block !important;
  }
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
</style>

<template>
  <div class="client-manager">
    <!-- 頁面標題和操作按鈕 -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">API 客戶端管理</h1>
        <p class="page-description">管理 API 客戶端和驗證憑證</p>
      </div>
      <div class="header-right">
        <button @click="showCreateModal = true" class="btn btn-primary">
          <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          新增客戶端
        </button>
      </div>
    </div>

    <!-- 搜尋和篩選區 -->
    <div class="filter-section">
      <div class="filter-row">
        <div class="filter-item">
          <input
            v-model="filters.search"
            type="text"
            placeholder="搜尋客戶端名稱或 API Key..."
            class="search-input"
            @input="debouncedSearch"
          />
        </div>
        
        <div class="filter-item">
          <select v-model="filters.client_type" @change="loadClients" class="filter-select">
            <option value="">全部類型</option>
            <option value="api_key">API Key</option>
            <option value="bearer_token">Bearer Token</option>
            <option value="oauth">OAuth 2.0</option>
          </select>
        </div>

        <div class="filter-item">
          <select v-model="filters.is_active" @change="loadClients" class="filter-select">
            <option value="">全部狀態</option>
            <option value="1">已啟用</option>
            <option value="0">已停用</option>
          </select>
        </div>

        <div class="filter-item">
          <button @click="resetFilters" class="btn btn-secondary">
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

    <!-- 客戶端列表 -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>名稱</th>
            <th>類型</th>
            <th>API Key</th>
            <th>速率限制</th>
            <th>狀態</th>
            <th>角色</th>
            <th>建立時間</th>
            <th class="actions-column">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="clients.length === 0">
            <td colspan="8" class="empty-state">
              <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              <p>尚無 API 客戶端</p>
              <button @click="showCreateModal = true" class="btn btn-primary btn-sm">
                建立第一個客戶端
              </button>
            </td>
          </tr>
          <tr v-for="client in clients" :key="client.id" class="data-row">
            <td>
              <strong>{{ client.name }}</strong>
            </td>
            <td>
              <span :class="['type-badge', `type-${client.client_type}`]">
                {{ getClientTypeLabel(client.client_type) }}
              </span>
            </td>
            <td>
              <div class="api-key-cell">
                <code class="api-key-code">{{ maskApiKey(client.api_key) }}</code>
                <button
                  @click="copyToClipboard(client.api_key)"
                  class="btn-copy"
                  title="複製 API Key"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </button>
              </div>
            </td>
            <td>
              <span class="rate-limit">{{ client.rate_limit || 60 }} / 分鐘</span>
            </td>
            <td>
              <span :class="['status-badge', client.is_active ? 'status-active' : 'status-inactive']">
                {{ client.is_active ? '已啟用' : '已停用' }}
              </span>
            </td>
            <td>
              <div class="roles-cell">
                <span v-if="client.roles && client.roles.length > 0" class="role-badge" v-for="role in client.roles" :key="role.id">
                  {{ role.name }}
                </span>
                <span v-else class="no-roles">無角色</span>
              </div>
            </td>
            <td>
              <span class="date-text">{{ formatDate(client.created_at) }}</span>
            </td>
            <td class="actions-column">
              <div class="action-buttons">
                <button
                  @click="toggleStatus(client)"
                  :class="['btn-icon-action', client.is_active ? 'btn-warning' : 'btn-success']"
                  :title="client.is_active ? '停用' : '啟用'"
                >
                  <svg v-if="client.is_active" class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <svg v-else class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>
                
                <button
                  @click="showRegenerateMenu(client)"
                  class="btn-icon-action btn-info"
                  title="重新生成憑證"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                </button>
                
                <button
                  @click="editClient(client)"
                  class="btn-icon-action btn-primary"
                  title="編輯"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                
                <button
                  @click="revokeClient(client)"
                  class="btn-icon-action btn-danger"
                  title="撤銷"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                  </svg>
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

    <!-- 創建客戶端 Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>新增 API 客戶端</h2>
          <button @click="showCreateModal = false" class="btn-close">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="createClient">
            <div class="form-group">
              <label>客戶端名稱 *</label>
              <input v-model="newClient.name" type="text" class="form-input" required />
            </div>

            <div class="form-group">
              <label>客戶端類型 *</label>
              <select v-model="newClient.client_type" class="form-input" required>
                <option value="api_key">API Key</option>
                <option value="bearer_token">Bearer Token</option>
                <option value="oauth">OAuth 2.0</option>
              </select>
            </div>

            <div class="form-group">
              <label>速率限制（每分鐘請求數）</label>
              <input v-model.number="newClient.rate_limit" type="number" class="form-input" min="1" placeholder="60" />
            </div>

            <div class="form-group">
              <label>
                <input v-model="newClient.is_active" type="checkbox" />
                啟用客戶端
              </label>
            </div>

            <div class="modal-footer">
              <button type="button" @click="showCreateModal = false" class="btn btn-secondary">
                取消
              </button>
              <button type="submit" class="btn btn-primary" :disabled="creating">
                {{ creating ? '建立中...' : '建立客戶端' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- 顯示憑證 Modal -->
    <div v-if="showCredentialsModal" class="modal-overlay" @click.self="showCredentialsModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>客戶端憑證</h2>
          <button @click="showCredentialsModal = false" class="btn-close">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning">
            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>請妥善保存以下憑證，此訊息僅顯示一次！</span>
          </div>

          <div class="credentials-display">
            <div class="credential-item">
              <label>API Key</label>
              <div class="credential-value">
                <code>{{ credentials.api_key }}</code>
                <button @click="copyToClipboard(credentials.api_key)" class="btn-copy">
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </button>
              </div>
            </div>

            <div class="credential-item">
              <label>Secret</label>
              <div class="credential-value">
                <code>{{ credentials.secret }}</code>
                <button @click="copyToClipboard(credentials.secret)" class="btn-copy">
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button @click="showCredentialsModal = false" class="btn btn-primary">
              我已保存憑證
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ClientManager',
  data() {
    return {
      clients: [],
      loading: false,
      error: null,
      filters: {
        search: '',
        client_type: '',
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
      showCreateModal: false,
      showCredentialsModal: false,
      creating: false,
      newClient: {
        name: '',
        client_type: 'api_key',
        rate_limit: 60,
        is_active: true,
      },
      credentials: {
        api_key: '',
        secret: '',
      },
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
    this.loadClients();
  },
  methods: {
    /**
     * 載入客戶端列表
     */
    async loadClients() {
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

        if (this.filters.client_type) {
          params.client_type = this.filters.client_type;
        }

        if (this.filters.is_active !== '') {
          params.is_active = this.filters.is_active;
        }

        const response = await this.$axios.get('/api/admin/clients', { params });

        if (response.data.success) {
          this.clients = response.data.data;
          this.pagination = {
            current_page: response.data.meta.current_page,
            per_page: response.data.meta.per_page,
            total: response.data.meta.total,
            last_page: response.data.meta.last_page,
            from: (response.data.meta.current_page - 1) * response.data.meta.per_page + 1,
            to: Math.min(response.data.meta.current_page * response.data.meta.per_page, response.data.meta.total),
          };
        } else {
          this.error = '載入客戶端列表失敗';
        }
      } catch (err) {
        console.error('載入客戶端列表失敗:', err);
        this.error = err.response?.data?.error?.message || '載入客戶端列表失敗，請稍後再試';
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
        this.loadClients();
      }, 500);
    },

    /**
     * 重置篩選條件
     */
    resetFilters() {
      this.filters = {
        search: '',
        client_type: '',
        is_active: '',
      };
      this.pagination.current_page = 1;
      this.loadClients();
    },

    /**
     * 切換頁面
     */
    goToPage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.pagination.current_page = page;
        this.loadClients();
      }
    },

    /**
     * 創建客戶端
     */
    async createClient() {
      this.creating = true;

      try {
        const response = await this.$axios.post('/api/admin/clients', this.newClient);

        if (response.data.success) {
          this.credentials = response.data.data.credentials;
          this.showCreateModal = false;
          this.showCredentialsModal = true;
          
          // 重置表單
          this.newClient = {
            name: '',
            client_type: 'api_key',
            rate_limit: 60,
            is_active: true,
          };

          // 重新載入列表
          this.loadClients();
        }
      } catch (err) {
        console.error('創建客戶端失敗:', err);
        alert(err.response?.data?.error?.message || '創建客戶端失敗，請稍後再試');
      } finally {
        this.creating = false;
      }
    },

    /**
     * 切換客戶端狀態
     */
    async toggleStatus(client) {
      const action = client.is_active ? '停用' : '啟用';
      
      if (!confirm(`確定要${action} "${client.name}" 嗎？`)) {
        return;
      }

      try {
        const response = await this.$axios.post(`/api/admin/clients/${client.id}/toggle-status`);

        if (response.data.success) {
          client.is_active = response.data.data.is_active;
          this.$emit('show-toast', {
            type: 'success',
            message: response.data.message,
          });
        }
      } catch (err) {
        console.error('切換狀態失敗:', err);
        alert(err.response?.data?.error?.message || '切換狀態失敗，請稍後再試');
      }
    },

    /**
     * 顯示重新生成選單
     */
    showRegenerateMenu(client) {
      const choice = confirm('選擇要重新生成的憑證：\n\n確定 = 重新生成 API Key\n取消 = 重新生成 Secret');
      
      if (choice) {
        this.regenerateApiKey(client);
      } else {
        this.regenerateSecret(client);
      }
    },

    /**
     * 重新生成 API Key
     */
    async regenerateApiKey(client) {
      if (!confirm(`確定要重新生成 "${client.name}" 的 API Key 嗎？舊的 API Key 將立即失效。`)) {
        return;
      }

      try {
        const response = await this.$axios.post(`/api/admin/clients/${client.id}/regenerate-api-key`);

        if (response.data.success) {
          client.api_key = response.data.data.api_key;
          this.credentials = {
            api_key: response.data.data.api_key,
            secret: '',
          };
          this.showCredentialsModal = true;
          
          this.$emit('show-toast', {
            type: 'success',
            message: response.data.message,
          });
        }
      } catch (err) {
        console.error('重新生成 API Key 失敗:', err);
        alert(err.response?.data?.error?.message || '重新生成 API Key 失敗，請稍後再試');
      }
    },

    /**
     * 重新生成 Secret
     */
    async regenerateSecret(client) {
      if (!confirm(`確定要重新生成 "${client.name}" 的 Secret 嗎？舊的 Secret 將立即失效。`)) {
        return;
      }

      try {
        const response = await this.$axios.post(`/api/admin/clients/${client.id}/regenerate-secret`);

        if (response.data.success) {
          this.credentials = {
            api_key: client.api_key,
            secret: response.data.data.secret,
          };
          this.showCredentialsModal = true;
          
          this.$emit('show-toast', {
            type: 'success',
            message: response.data.message,
          });
        }
      } catch (err) {
        console.error('重新生成 Secret 失敗:', err);
        alert(err.response?.data?.error?.message || '重新生成 Secret 失敗，請稍後再試');
      }
    },

    /**
     * 編輯客戶端
     */
    editClient(client) {
      // TODO: 實作編輯功能
      alert('編輯功能開發中');
    },

    /**
     * 撤銷客戶端
     */
    async revokeClient(client) {
      if (!confirm(`確定要撤銷 "${client.name}" 嗎？此操作將停用客戶端並撤銷所有 Token。`)) {
        return;
      }

      try {
        const response = await this.$axios.post(`/api/admin/clients/${client.id}/revoke`);

        if (response.data.success) {
          this.$emit('show-toast', {
            type: 'success',
            message: response.data.message,
          });
          this.loadClients();
        }
      } catch (err) {
        console.error('撤銷客戶端失敗:', err);
        alert(err.response?.data?.error?.message || '撤銷客戶端失敗，請稍後再試');
      }
    },

    /**
     * 複製到剪貼簿
     */
    async copyToClipboard(text) {
      try {
        await navigator.clipboard.writeText(text);
        this.$emit('show-toast', {
          type: 'success',
          message: '已複製到剪貼簿',
        });
      } catch (err) {
        console.error('複製失敗:', err);
        alert('複製失敗，請手動複製');
      }
    },

    /**
     * 遮罩 API Key
     */
    maskApiKey(apiKey) {
      if (!apiKey || apiKey.length < 12) return apiKey;
      return apiKey.substring(0, 8) + '...' + apiKey.substring(apiKey.length - 4);
    },

    /**
     * 取得客戶端類型標籤
     */
    getClientTypeLabel(type) {
      const labels = {
        api_key: 'API Key',
        bearer_token: 'Bearer Token',
        oauth: 'OAuth 2.0',
      };
      return labels[type] || type;
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
/* 使用與 FunctionList 相同的基礎樣式 */
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

.filter-section {
  background: white;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filter-row {
  display: flex;
  gap: 15px;
  align-items: center;
}

.filter-item {
  flex: 1;
}

.filter-item:last-child {
  flex: 0;
}

.search-input,
.filter-select {
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

.table-container {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

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
  width: 180px;
  text-align: center;
}

/* 類型標籤 */
.type-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.type-api_key {
  background-color: #dbeafe;
  color: #1e40af;
}

.type-bearer_token {
  background-color: #fef3c7;
  color: #92400e;
}

.type-oauth {
  background-color: #e0e7ff;
  color: #3730a3;
}

/* API Key 顯示 */
.api-key-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.api-key-code {
  font-family: 'Courier New', monospace;
  font-size: 13px;
  background-color: #f3f4f6;
  padding: 4px 8px;
  border-radius: 4px;
  color: #1f2937;
}

.btn-copy {
  padding: 4px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: #6b7280;
  transition: color 0.2s;
}

.btn-copy:hover {
  color: #3b82f6;
}

.btn-copy .icon {
  width: 16px;
  height: 16px;
}

/* 速率限制 */
.rate-limit {
  color: #6b7280;
  font-size: 13px;
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

/* 角色顯示 */
.roles-cell {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.role-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  background-color: #f3e8ff;
  color: #6b21a8;
}

.no-roles {
  color: #9ca3af;
  font-size: 12px;
}

.date-text {
  color: #6b7280;
  font-size: 13px;
}

/* 操作按鈕 */
.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.btn-icon-action {
  padding: 6px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  background-color: transparent;
}

.btn-icon-action .icon {
  width: 18px;
  height: 18px;
}

.btn-icon-action.btn-primary {
  color: #3b82f6;
}

.btn-icon-action.btn-primary:hover {
  background-color: #dbeafe;
}

.btn-icon-action.btn-danger {
  color: #ef4444;
}

.btn-icon-action.btn-danger:hover {
  background-color: #fee2e2;
}

.btn-icon-action.btn-warning {
  color: #f59e0b;
}

.btn-icon-action.btn-warning:hover {
  background-color: #fef3c7;
}

.btn-icon-action.btn-success {
  color: #10b981;
}

.btn-icon-action.btn-success:hover {
  background-color: #d1fae5;
}

.btn-icon-action.btn-info {
  color: #8b5cf6;
}

.btn-icon-action.btn-info:hover {
  background-color: #ede9fe;
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

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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

/* Modal 樣式 */
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
  border-radius: 12px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
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
  font-size: 20px;
  font-weight: 600;
}

.btn-close {
  padding: 4px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: #6b7280;
  transition: color 0.2s;
}

.btn-close:hover {
  color: #111827;
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
}

/* 表單樣式 */
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

.form-input {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
}

.form-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* 警告訊息 */
.alert {
  padding: 15px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
}

.alert-warning {
  background-color: #fef3c7;
  border: 1px solid #fde68a;
  color: #92400e;
}

.alert-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

/* 憑證顯示 */
.credentials-display {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.credential-item label {
  display: block;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.credential-value {
  display: flex;
  align-items: center;
  gap: 10px;
  background-color: #f9fafb;
  padding: 12px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
}

.credential-value code {
  flex: 1;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  color: #1f2937;
  word-break: break-all;
}
</style>

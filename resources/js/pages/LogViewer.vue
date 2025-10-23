<template>
  <div class="log-viewer">
    <!-- 頁面標題 -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">日誌查詢</h1>
        <p class="page-description">查看系統運行日誌和請求記錄</p>
      </div>
      <div class="header-right">
        <button @click="loadStatistics" class="btn btn-secondary">
          <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          統計資訊
        </button>
      </div>
    </div>

    <!-- 日誌類型標籤 -->
    <div class="log-tabs">
      <button
        v-for="tab in logTabs"
        :key="tab.value"
        @click="selectTab(tab.value)"
        :class="['tab-button', { active: currentTab === tab.value }]"
      >
        <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
        </svg>
        {{ tab.label }}
        <span v-if="tab.count !== null" class="tab-count">{{ tab.count }}</span>
      </button>
    </div>

    <!-- 篩選區 -->
    <div class="filter-section">
      <div class="filter-row">
        <!-- 時間範圍篩選 -->
        <div class="filter-item">
          <label class="filter-label">開始時間</label>
          <input
            v-model="filters.start_date"
            type="datetime-local"
            class="filter-input"
            @change="loadLogs"
          />
        </div>
        
        <div class="filter-item">
          <label class="filter-label">結束時間</label>
          <input
            v-model="filters.end_date"
            type="datetime-local"
            class="filter-input"
            @change="loadLogs"
          />
        </div>

        <!-- API 請求日誌專用篩選 -->
        <template v-if="currentTab === 'api-requests'">
          <div class="filter-item">
            <label class="filter-label">HTTP 狀態碼</label>
            <select v-model="filters.http_status" @change="loadLogs" class="filter-select">
              <option value="">全部</option>
              <option value="200">200 - 成功</option>
              <option value="400">400 - 請求錯誤</option>
              <option value="401">401 - 未授權</option>
              <option value="403">403 - 禁止存取</option>
              <option value="404">404 - 找不到</option>
              <option value="429">429 - 請求過多</option>
              <option value="500">500 - 伺服器錯誤</option>
            </select>
          </div>
        </template>

        <!-- 錯誤日誌專用篩選 -->
        <template v-if="currentTab === 'errors'">
          <div class="filter-item">
            <label class="filter-label">錯誤類型</label>
            <input
              v-model="filters.type"
              type="text"
              placeholder="輸入錯誤類型..."
              class="filter-input"
              @input="debouncedSearch"
            />
          </div>
          
          <div class="filter-item">
            <label class="filter-label">關鍵字搜尋</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="搜尋錯誤訊息..."
              class="filter-input"
              @input="debouncedSearch"
            />
          </div>
        </template>

        <!-- 安全日誌專用篩選 -->
        <template v-if="currentTab === 'security'">
          <div class="filter-item">
            <label class="filter-label">事件類型</label>
            <select v-model="filters.event_type" @change="loadLogs" class="filter-select">
              <option value="">全部</option>
              <option value="authentication_failure">驗證失敗</option>
              <option value="authorization_failure">授權失敗</option>
              <option value="rate_limit_exceeded">超過速率限制</option>
              <option value="suspicious_activity">可疑活動</option>
            </select>
          </div>
        </template>

        <!-- 審計日誌專用篩選 -->
        <template v-if="currentTab === 'audit'">
          <div class="filter-item">
            <label class="filter-label">操作類型</label>
            <select v-model="filters.action" @change="loadLogs" class="filter-select">
              <option value="">全部</option>
              <option value="create">建立</option>
              <option value="update">更新</option>
              <option value="delete">刪除</option>
            </select>
          </div>
          
          <div class="filter-item">
            <label class="filter-label">資源類型</label>
            <select v-model="filters.resource_type" @change="loadLogs" class="filter-select">
              <option value="">全部</option>
              <option value="api_function">API Function</option>
              <option value="api_client">API 客戶端</option>
              <option value="role">角色</option>
              <option value="permission">權限</option>
            </select>
          </div>
        </template>

        <div class="filter-item">
          <label class="filter-label">&nbsp;</label>
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

    <!-- 日誌列表 -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th v-for="column in currentColumns" :key="column.key">
              {{ column.label }}
            </th>
            <th class="actions-column">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="logs.length === 0">
            <td :colspan="currentColumns.length + 1" class="empty-state">
              <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <p>目前沒有日誌記錄</p>
            </td>
          </tr>
          <tr v-for="log in logs" :key="log.id" class="data-row">
            <!-- API 請求日誌 -->
            <template v-if="currentTab === 'api-requests'">
              <td data-label="Function">
                <code class="identifier-code">{{ log.function?.identifier || '-' }}</code>
              </td>
              <td data-label="客戶端">
                <span class="client-name">{{ log.client?.name || '-' }}</span>
              </td>
              <td data-label="狀態碼">
                <span :class="['status-badge', getStatusClass(log.http_status)]">
                  {{ log.http_status }}
                </span>
              </td>
              <td data-label="執行時間">
                <span class="execution-time">{{ log.execution_time?.toFixed(3) }}s</span>
              </td>
              <td data-label="IP 地址">
                <span class="ip-address">{{ log.ip_address }}</span>
              </td>
              <td data-label="時間">
                <span class="date-text">{{ formatDate(log.created_at) }}</span>
              </td>
            </template>

            <!-- 錯誤日誌 -->
            <template v-if="currentTab === 'errors'">
              <td data-label="錯誤類型">
                <code class="error-type">{{ log.type }}</code>
              </td>
              <td data-label="錯誤訊息">
                <div class="error-message-cell">{{ truncate(log.message, 80) }}</div>
              </td>
              <td data-label="時間">
                <span class="date-text">{{ formatDate(log.created_at) }}</span>
              </td>
            </template>

            <!-- 安全日誌 -->
            <template v-if="currentTab === 'security'">
              <td data-label="事件類型">
                <span :class="['event-badge', getEventClass(log.event_type)]">
                  {{ formatEventType(log.event_type) }}
                </span>
              </td>
              <td data-label="客戶端">
                <span class="client-name">{{ log.client?.name || '-' }}</span>
              </td>
              <td data-label="IP 地址">
                <span class="ip-address">{{ log.ip_address }}</span>
              </td>
              <td data-label="時間">
                <span class="date-text">{{ formatDate(log.created_at) }}</span>
              </td>
            </template>

            <!-- 審計日誌 -->
            <template v-if="currentTab === 'audit'">
              <td data-label="使用者">
                <span class="user-name">{{ log.user?.name || '-' }}</span>
              </td>
              <td data-label="操作">
                <span :class="['action-badge', getActionClass(log.action)]">
                  {{ formatAction(log.action) }}
                </span>
              </td>
              <td data-label="資源類型">
                <span class="resource-type">{{ formatResourceType(log.resource_type) }}</span>
              </td>
              <td data-label="時間">
                <span class="date-text">{{ formatDate(log.created_at) }}</span>
              </td>
            </template>

            <td data-label="操作" class="actions-column">
              <div class="action-buttons">
                <button
                  @click="viewDetail(log)"
                  class="btn-action btn-action-primary"
                  title="查看詳情"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <span class="btn-text">查看詳情</span>
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

    <!-- 日誌詳情 Modal -->
    <div v-if="showDetailModal" class="modal-overlay" @click="closeDetailModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2 class="modal-title">日誌詳情</h2>
          <button @click="closeDetailModal" class="modal-close">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="selectedLog" class="log-detail">
            <pre class="json-display">{{ JSON.stringify(selectedLog, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>

    <!-- 統計資訊 Modal -->
    <div v-if="showStatisticsModal" class="modal-overlay" @click.self="closeStatisticsModal">
      <div class="modal-content modal-large" @click.stop>
        <div class="modal-header">
          <h2 class="modal-title">日誌統計資訊</h2>
          <button @click="closeStatisticsModal" class="modal-close">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <!-- 載入中 -->
          <div v-if="loadingStatistics" class="loading-container">
            <div class="spinner"></div>
            <p>載入統計資訊中...</p>
          </div>

          <!-- 統計資訊內容 -->
          <div v-else-if="statistics" class="statistics-grid">
            <!-- API 請求統計 -->
            <div class="stat-card">
              <h3 class="stat-title">API 請求</h3>
              <div class="stat-items">
                <div class="stat-item">
                  <span class="stat-label">總請求數</span>
                  <span class="stat-value">{{ statistics.api_requests?.total || 0 }}</span>
                </div>
                <div class="stat-item">
                  <span class="stat-label">成功請求</span>
                  <span class="stat-value stat-success">{{ statistics.api_requests?.success || 0 }}</span>
                </div>
                <div class="stat-item">
                  <span class="stat-label">客戶端錯誤</span>
                  <span class="stat-value stat-warning">{{ statistics.api_requests?.client_error || 0 }}</span>
                </div>
                <div class="stat-item">
                  <span class="stat-label">伺服器錯誤</span>
                  <span class="stat-value stat-danger">{{ statistics.api_requests?.server_error || 0 }}</span>
                </div>
                <div class="stat-item">
                  <span class="stat-label">平均執行時間</span>
                  <span class="stat-value">{{ formatExecutionTime(statistics.api_requests?.avg_execution_time) }}s</span>
                </div>
              </div>
            </div>

            <!-- 錯誤統計 -->
            <div class="stat-card">
              <h3 class="stat-title">錯誤日誌</h3>
              <div class="stat-items">
                <div class="stat-item">
                  <span class="stat-label">總錯誤數</span>
                  <span class="stat-value stat-danger">{{ statistics.errors?.total || 0 }}</span>
                </div>
              </div>
            </div>

            <!-- 安全統計 -->
            <div class="stat-card">
              <h3 class="stat-title">安全事件</h3>
              <div class="stat-items">
                <div class="stat-item">
                  <span class="stat-label">總事件數</span>
                  <span class="stat-value">{{ statistics.security?.total || 0 }}</span>
                </div>
                <div class="stat-item">
                  <span class="stat-label">驗證失敗</span>
                  <span class="stat-value stat-warning">{{ statistics.security?.authentication_failures || 0 }}</span>
                </div>
                <div class="stat-item">
                  <span class="stat-label">授權失敗</span>
                  <span class="stat-value stat-warning">{{ statistics.security?.authorization_failures || 0 }}</span>
                </div>
              </div>
            </div>

            <!-- 審計統計 -->
            <div class="stat-card">
              <h3 class="stat-title">審計日誌</h3>
              <div class="stat-items">
                <div class="stat-item">
                  <span class="stat-label">總操作數</span>
                  <span class="stat-value">{{ statistics.audit?.total || 0 }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- 無資料提示 -->
          <div v-else class="empty-state">
            <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <p>無統計資訊</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LogViewer',
  data() {
    return {
      currentTab: 'api-requests',
      logs: [],
      loading: false,
      error: null,
      filters: {
        start_date: '',
        end_date: '',
        http_status: '',
        type: '',
        search: '',
        event_type: '',
        action: '',
        resource_type: '',
      },
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
        from: 0,
        to: 0,
      },
      searchTimeout: null,
      showDetailModal: false,
      selectedLog: null,
      showStatisticsModal: false,
      loadingStatistics: false,
      statistics: null,
      logTabs: [
        {
          value: 'api-requests',
          label: 'API 請求',
          icon: 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
          count: null,
        },
        {
          value: 'errors',
          label: '錯誤日誌',
          icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
          count: null,
        },
        {
          value: 'security',
          label: '安全日誌',
          icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
          count: null,
        },
        {
          value: 'audit',
          label: '審計日誌',
          icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
          count: null,
        },
      ],
    };
  },
  computed: {
    currentColumns() {
      const columns = {
        'api-requests': [
          { key: 'function', label: 'Function' },
          { key: 'client', label: '客戶端' },
          { key: 'status', label: '狀態碼' },
          { key: 'execution_time', label: '執行時間' },
          { key: 'ip_address', label: 'IP 地址' },
          { key: 'created_at', label: '時間' },
        ],
        'errors': [
          { key: 'type', label: '錯誤類型' },
          { key: 'message', label: '錯誤訊息' },
          { key: 'created_at', label: '時間' },
        ],
        'security': [
          { key: 'event_type', label: '事件類型' },
          { key: 'client', label: '客戶端' },
          { key: 'ip_address', label: 'IP 地址' },
          { key: 'created_at', label: '時間' },
        ],
        'audit': [
          { key: 'user', label: '使用者' },
          { key: 'action', label: '操作' },
          { key: 'resource_type', label: '資源類型' },
          { key: 'created_at', label: '時間' },
        ],
      };
      return columns[this.currentTab] || [];
    },
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
    this.loadLogs();
  },
  methods: {
    /**
     * 選擇標籤
     */
    selectTab(tab) {
      this.currentTab = tab;
      this.resetFilters();
      this.pagination.current_page = 1;
      this.loadLogs();
    },

    /**
     * 載入日誌列表
     */
    async loadLogs() {
      this.loading = true;
      this.error = null;

      try {
        const params = {
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
        };

        // 添加篩選參數
        if (this.filters.start_date) {
          params.start_date = this.filters.start_date;
        }
        if (this.filters.end_date) {
          params.end_date = this.filters.end_date;
        }

        // 根據不同的日誌類型添加特定篩選
        if (this.currentTab === 'api-requests') {
          if (this.filters.http_status) {
            params.http_status = this.filters.http_status;
          }
        } else if (this.currentTab === 'errors') {
          if (this.filters.type) {
            params.type = this.filters.type;
          }
          if (this.filters.search) {
            params.search = this.filters.search;
          }
        } else if (this.currentTab === 'security') {
          if (this.filters.event_type) {
            params.event_type = this.filters.event_type;
          }
        } else if (this.currentTab === 'audit') {
          if (this.filters.action) {
            params.action = this.filters.action;
          }
          if (this.filters.resource_type) {
            params.resource_type = this.filters.resource_type;
          }
        }

        const endpoint = this.getEndpoint();
        const response = await this.$axios.get(endpoint, { params });

        if (response.data.success) {
          this.logs = response.data.data;
          this.pagination = {
            current_page: response.data.meta.current_page,
            per_page: response.data.meta.per_page,
            total: response.data.meta.total,
            last_page: response.data.meta.last_page,
            from: (response.data.meta.current_page - 1) * response.data.meta.per_page + 1,
            to: Math.min(response.data.meta.current_page * response.data.meta.per_page, response.data.meta.total),
          };
        } else {
          this.error = '載入日誌失敗';
        }
      } catch (err) {
        console.error('載入日誌失敗:', err);
        this.error = err.response?.data?.error?.message || '載入日誌失敗，請稍後再試';
      } finally {
        this.loading = false;
      }
    },

    /**
     * 取得 API 端點
     */
    getEndpoint() {
      const endpoints = {
        'api-requests': '/api/admin/logs/api-requests',
        'errors': '/api/admin/logs/errors',
        'security': '/api/admin/logs/security',
        'audit': '/api/admin/logs/audit',
      };
      return endpoints[this.currentTab];
    },

    /**
     * 防抖搜尋
     */
    debouncedSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.pagination.current_page = 1;
        this.loadLogs();
      }, 500);
    },

    /**
     * 重置篩選條件
     */
    resetFilters() {
      this.filters = {
        start_date: '',
        end_date: '',
        http_status: '',
        type: '',
        search: '',
        event_type: '',
        action: '',
        resource_type: '',
      };
      this.pagination.current_page = 1;
      this.loadLogs();
    },

    /**
     * 切換頁面
     */
    goToPage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.pagination.current_page = page;
        this.loadLogs();
      }
    },

    /**
     * 查看詳情
     */
    async viewDetail(log) {
      try {
        const endpoint = this.getDetailEndpoint(log.id);
        const response = await this.$axios.get(endpoint);

        if (response.data.success) {
          this.selectedLog = response.data.data;
          this.showDetailModal = true;
        }
      } catch (err) {
        console.error('載入日誌詳情失敗:', err);
        alert(err.response?.data?.error?.message || '載入日誌詳情失敗');
      }
    },

    /**
     * 取得詳情 API 端點
     */
    getDetailEndpoint(id) {
      const endpoints = {
        'api-requests': `/api/admin/logs/api-requests/${id}`,
        'errors': `/api/admin/logs/errors/${id}`,
        'security': `/api/admin/logs/security/${id}`,
        'audit': `/api/admin/logs/audit/${id}`,
      };
      return endpoints[this.currentTab];
    },

    /**
     * 關閉詳情 Modal
     */
    closeDetailModal() {
      this.showDetailModal = false;
      this.selectedLog = null;
    },

    /**
     * 載入統計資訊
     */
    async loadStatistics() {
      this.loadingStatistics = true;
      this.showStatisticsModal = true;
      this.statistics = null;

      try {
        const params = {};
        if (this.filters.start_date) {
          params.start_date = this.filters.start_date;
        }
        if (this.filters.end_date) {
          params.end_date = this.filters.end_date;
        }

        console.log('載入統計資訊，參數:', params);
        const response = await this.$axios.get('/api/admin/logs/statistics', { params });
        console.log('統計資訊回應:', response.data);

        if (response.data.success) {
          this.statistics = response.data.data;
          console.log('統計資訊已設定:', this.statistics);
        } else {
          console.error('載入統計資訊失敗:', response.data);
          alert('載入統計資訊失敗');
        }
      } catch (err) {
        console.error('載入統計資訊錯誤:', err);
        console.error('錯誤詳情:', err.response?.data);
        alert(err.response?.data?.error?.message || err.response?.data?.message || '載入統計資訊失敗，請稍後再試');
      } finally {
        this.loadingStatistics = false;
        console.log('載入完成，loadingStatistics:', this.loadingStatistics, 'statistics:', this.statistics);
      }
    },

    /**
     * 關閉統計 Modal
     */
    closeStatisticsModal() {
      this.showStatisticsModal = false;
    },

    /**
     * 取得狀態碼樣式類別
     */
    getStatusClass(status) {
      if (status >= 200 && status < 300) return 'status-success';
      if (status >= 400 && status < 500) return 'status-warning';
      if (status >= 500) return 'status-danger';
      return '';
    },

    /**
     * 取得事件類型樣式類別
     */
    getEventClass(eventType) {
      const classes = {
        'authentication_failure': 'event-warning',
        'authorization_failure': 'event-warning',
        'rate_limit_exceeded': 'event-danger',
        'suspicious_activity': 'event-danger',
      };
      return classes[eventType] || '';
    },

    /**
     * 取得操作類型樣式類別
     */
    getActionClass(action) {
      const classes = {
        'create': 'action-success',
        'update': 'action-info',
        'delete': 'action-danger',
      };
      return classes[action] || '';
    },

    /**
     * 格式化事件類型
     */
    formatEventType(eventType) {
      const types = {
        'authentication_failure': '驗證失敗',
        'authorization_failure': '授權失敗',
        'rate_limit_exceeded': '超過速率限制',
        'suspicious_activity': '可疑活動',
      };
      return types[eventType] || eventType;
    },

    /**
     * 格式化操作類型
     */
    formatAction(action) {
      const actions = {
        'create': '建立',
        'update': '更新',
        'delete': '刪除',
      };
      return actions[action] || action;
    },

    /**
     * 格式化資源類型
     */
    formatResourceType(resourceType) {
      const types = {
        'api_function': 'API Function',
        'api_client': 'API 客戶端',
        'role': '角色',
        'permission': '權限',
      };
      return types[resourceType] || resourceType;
    },

    /**
     * 截斷文字
     */
    truncate(text, length) {
      if (!text) return '-';
      if (text.length <= length) return text;
      return text.substring(0, length) + '...';
    },

    /**
     * 格式化執行時間
     */
    formatExecutionTime(time) {
      if (time === null || time === undefined) return '0.000';
      const num = Number(time);
      if (isNaN(num)) return '0.000';
      return num.toFixed(3);
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
        second: '2-digit',
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

/* 日誌類型標籤 */
.log-tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  border-bottom: 2px solid #e5e7eb;
}

.tab-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
}

.tab-button:hover {
  color: #3b82f6;
}

.tab-button.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.tab-icon {
  width: 18px;
  height: 18px;
}

.tab-count {
  display: inline-block;
  background-color: #e5e7eb;
  color: #374151;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
}

.tab-button.active .tab-count {
  background-color: #dbeafe;
  color: #1e40af;
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
  gap: 15px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.filter-item {
  flex: 1;
  min-width: 200px;
}

.filter-item:last-child {
  flex: 0;
}

.filter-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.filter-input,
.filter-select {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
}

.filter-input:focus,
.filter-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-select {
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
  width: 140px;
  text-align: right;
  white-space: nowrap;
}

/* 程式碼樣式 */
.identifier-code,
.error-type {
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

.status-success {
  background-color: #d1fae5;
  color: #065f46;
}

.status-warning {
  background-color: #fef3c7;
  color: #92400e;
}

.status-danger {
  background-color: #fee2e2;
  color: #991b1b;
}

/* 事件標籤 */
.event-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.event-warning {
  background-color: #fef3c7;
  color: #92400e;
}

.event-danger {
  background-color: #fee2e2;
  color: #991b1b;
}

/* 操作標籤 */
.action-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.action-success {
  background-color: #d1fae5;
  color: #065f46;
}

.action-info {
  background-color: #dbeafe;
  color: #1e40af;
}

.action-danger {
  background-color: #fee2e2;
  color: #991b1b;
}

/* 其他樣式 */
.client-name,
.user-name,
.resource-type {
  color: #374151;
  font-weight: 500;
}

.execution-time {
  font-family: 'Courier New', monospace;
  color: #059669;
  font-weight: 600;
}

.ip-address {
  font-family: 'Courier New', monospace;
  color: #6b7280;
  font-size: 13px;
}

.error-message-cell {
  max-width: 400px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

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

/* 響應式：在極小螢幕上隱藏按鈕文字 */
@media (max-width: 1024px) {
  .btn-action .btn-text {
    display: none;
  }
  
  .btn-action {
    padding: 6px;
  }
  
  .actions-column {
    width: 60px;
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
  margin: 0;
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
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  max-width: 600px;
  width: 90%;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-content.modal-large {
  max-width: 900px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.modal-close {
  padding: 6px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: #6b7280;
  border-radius: 6px;
  transition: all 0.2s;
}

.modal-close:hover {
  background-color: #f3f4f6;
  color: #111827;
}

.modal-close .icon {
  width: 20px;
  height: 20px;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
  min-height: 200px;
}

/* 日誌詳情 */
.log-detail {
  font-size: 14px;
}

.json-display {
  background-color: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 16px;
  overflow-x: auto;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  line-height: 1.6;
  color: #1f2937;
  margin: 0;
}

/* 統計資訊 */
.statistics-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  width: 100%;
}

.stat-card {
  background-color: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 20px;
  min-height: 150px;
}

.stat-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 16px 0;
}

.stat-items {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-label {
  font-size: 14px;
  color: #6b7280;
}

.stat-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.stat-value.stat-success {
  color: #059669;
}

.stat-value.stat-warning {
  color: #d97706;
}

.stat-value.stat-danger {
  color: #dc2626;
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

  .filter-row {
    flex-direction: column;
  }

  .filter-item {
    min-width: 100%;
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

  /* 標籤優化 */
  .log-tabs {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    border-bottom: none;
    margin-bottom: 15px;
  }

  .tab-button {
    padding: 12px 10px;
    font-size: 14px;
    white-space: normal;
    text-align: center;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 0;
    flex-direction: column;
    gap: 6px;
  }

  .tab-button.active {
    border-color: #3b82f6;
    background-color: #eff6ff;
  }

  .tab-icon {
    width: 20px;
    height: 20px;
  }

  .tab-count {
    font-size: 12px;
    margin-top: 2px;
  }

  /* 篩選區優化 */
  .filter-section {
    padding: 15px;
  }

  .filter-label {
    font-size: 12px;
  }

  .filter-input,
  .filter-select {
    padding: 8px 12px;
    font-size: 13px;
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
  }

  .btn-action {
    flex: 1;
    justify-content: center;
  }

  .btn-action .btn-text {
    display: inline-block !important;
  }

  .error-message-cell {
    max-width: 100%;
    white-space: normal;
    word-break: break-word;
    text-align: right;
  }

  .identifier-code,
  .error-type {
    font-size: 11px;
    word-break: break-all;
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

  .modal-title {
    font-size: 16px;
  }

  .modal-body {
    padding: 15px;
  }

  .json-display {
    font-size: 11px;
    padding: 12px;
  }

  .statistics-grid {
    grid-template-columns: 1fr;
  }

  .stat-card {
    padding: 15px;
  }

  .stat-title {
    font-size: 15px;
  }

  .stat-label {
    font-size: 13px;
  }

  .stat-value {
    font-size: 15px;
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

  .log-tabs {
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;
  }

  .tab-button {
    padding: 10px 8px;
    font-size: 13px;
  }

  .tab-icon {
    width: 18px;
    height: 18px;
  }

  .tab-count {
    font-size: 11px;
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

  .error-message-cell {
    text-align: left;
  }

  .action-buttons {
    gap: 8px;
  }

  .btn-action {
    padding: 10px;
  }

  .page-btn {
    padding: 8px 12px;
    font-size: 13px;
  }

  .modal-header {
    padding: 12px;
  }

  .modal-title {
    font-size: 15px;
  }

  .modal-body {
    padding: 12px;
  }

  .json-display {
    font-size: 10px;
    padding: 10px;
  }

  .stat-card {
    padding: 12px;
  }

  .stat-title {
    font-size: 14px;
    margin-bottom: 12px;
  }

  .stat-items {
    gap: 10px;
  }

  .stat-label {
    font-size: 12px;
  }

  .stat-value {
    font-size: 14px;
  }
}
</style>

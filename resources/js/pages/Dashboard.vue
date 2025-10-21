<template>
  <div class="dashboard">
    <!-- 載入狀態 -->
    <div v-if="loading" class="loading-container">
      <div class="loading"></div>
      <p>載入中...</p>
    </div>

    <!-- 錯誤訊息 -->
    <div v-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <!-- 儀表板內容 -->
    <div v-if="!loading && !error">
      <!-- 統計卡片 -->
      <div class="grid grid-cols-4 stats-grid">
        <div class="stat-card">
          <div class="stat-value">{{ stats.functions.total }}</div>
          <div class="stat-label">API Functions 總數</div>
          <div class="stat-detail">
            <span class="stat-success">{{ stats.functions.active }} 啟用</span>
            <span class="stat-muted">{{ stats.functions.inactive }} 停用</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-value">{{ stats.clients.total }}</div>
          <div class="stat-label">API 客戶端總數</div>
          <div class="stat-detail">
            <span class="stat-success">{{ stats.clients.active }} 啟用</span>
            <span class="stat-muted">{{ stats.clients.inactive }} 停用</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-value">{{ stats.requests.today }}</div>
          <div class="stat-label">今日請求總數</div>
          <div class="stat-detail">
            <span class="stat-success">{{ stats.requests.today_success }} 成功</span>
            <span class="stat-error">{{ stats.requests.today_error }} 失敗</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-value">{{ stats.requests.avg_response_time }}s</div>
          <div class="stat-label">平均回應時間</div>
          <div class="stat-detail">
            <span class="stat-muted">今日統計</span>
          </div>
        </div>
      </div>

      <!-- 請求趨勢圖表 -->
      <div class="card">
        <div class="card-header">最近 7 天請求趨勢</div>
        <div class="card-body">
          <div v-if="stats.trends.requests.length > 0" class="trend-chart">
            <div v-for="day in stats.trends.requests" :key="day.date" class="trend-item">
              <div class="trend-date">{{ formatDate(day.date) }}</div>
              <div class="trend-bars">
                <div class="trend-bar trend-bar-success" :style="{ width: getBarWidth(day.success) }">
                  {{ day.success }}
                </div>
                <div class="trend-bar trend-bar-error" :style="{ width: getBarWidth(day.error) }">
                  {{ day.error }}
                </div>
              </div>
              <div class="trend-total">總計: {{ day.total }}</div>
            </div>
          </div>
          <div v-else class="empty-state">
            <p>暫無請求資料</p>
          </div>
        </div>
      </div>

      <!-- 最常使用的 API Functions -->
      <div class="card">
        <div class="card-header">最常使用的 API Functions (最近 7 天)</div>
        <div class="card-body">
          <table v-if="stats.trends.top_functions.length > 0" class="table">
            <thead>
              <tr>
                <th>排名</th>
                <th>Function 名稱</th>
                <th>識別碼</th>
                <th>請求次數</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(func, index) in stats.trends.top_functions" :key="func.function_id">
                <td>{{ index + 1 }}</td>
                <td>{{ func.function_name }}</td>
                <td><code>{{ func.function_identifier }}</code></td>
                <td>{{ func.request_count }}</td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty-state">
            <p>暫無使用資料</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Dashboard',
  data() {
    return {
      loading: true,
      error: null,
      stats: {
        functions: {
          total: 0,
          active: 0,
          inactive: 0,
        },
        clients: {
          total: 0,
          active: 0,
          inactive: 0,
        },
        requests: {
          today: 0,
          today_success: 0,
          today_error: 0,
          avg_response_time: 0,
        },
        trends: {
          requests: [],
          top_functions: [],
        },
      },
    };
  },
  mounted() {
    this.loadStats();
  },
  methods: {
    async loadStats() {
      try {
        this.loading = true;
        this.error = null;

        const response = await this.$axios.get('/admin/dashboard', {
          headers: {
            'Accept': 'application/json',
          },
        });

        if (response.data.success) {
          this.stats = response.data.data;
        } else {
          this.error = '載入統計資料失敗';
        }
      } catch (error) {
        console.error('載入統計資料錯誤:', error);
        this.error = error.response?.data?.error?.message || '載入統計資料時發生錯誤';
      } finally {
        this.loading = false;
      }
    },
    formatDate(dateString) {
      const date = new Date(dateString);
      return `${date.getMonth() + 1}/${date.getDate()}`;
    },
    getBarWidth(value) {
      const maxValue = Math.max(
        ...this.stats.trends.requests.map(d => Math.max(d.success, d.error))
      );
      return maxValue > 0 ? `${(value / maxValue) * 100}%` : '0%';
    },
  },
};
</script>

<style scoped>
.dashboard {
  max-width: 1400px;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
}

.loading-container p {
  margin-top: 15px;
  color: #6b7280;
}

.stats-grid {
  margin-bottom: 30px;
}

.stat-detail {
  margin-top: 10px;
  font-size: 12px;
  display: flex;
  gap: 10px;
  justify-content: center;
}

.stat-success {
  color: #10b981;
}

.stat-error {
  color: #ef4444;
}

.stat-muted {
  color: #6b7280;
}

/* 趨勢圖表樣式 */
.trend-chart {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.trend-item {
  display: grid;
  grid-template-columns: 80px 1fr 100px;
  align-items: center;
  gap: 15px;
}

.trend-date {
  font-size: 13px;
  color: #6b7280;
  text-align: right;
}

.trend-bars {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.trend-bar {
  height: 24px;
  display: flex;
  align-items: center;
  padding: 0 10px;
  font-size: 12px;
  color: white;
  border-radius: 4px;
  min-width: 40px;
  transition: width 0.3s;
}

.trend-bar-success {
  background-color: #10b981;
}

.trend-bar-error {
  background-color: #ef4444;
}

.trend-total {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

/* 空狀態樣式 */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #6b7280;
}

/* 程式碼樣式 */
code {
  background-color: #f3f4f6;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  color: #374151;
}

/* 響應式設計 */
@media (max-width: 1024px) {
  .trend-item {
    grid-template-columns: 60px 1fr 80px;
  }
}

@media (max-width: 768px) {
  .trend-item {
    grid-template-columns: 1fr;
    gap: 5px;
  }

  .trend-date {
    text-align: left;
  }
}
</style>

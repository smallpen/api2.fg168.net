<template>
  <div class="sp-selector">
    <div class="form-group">
      <label class="form-label">Stored Procedure *</label>
      <div class="selector-container">
        <select
          v-model="selectedProcedure"
          class="form-select"
          @change="handleProcedureChange"
        >
          <option value="">請選擇 Stored Procedure</option>
          <option
            v-for="sp in procedures"
            :key="sp.name"
            :value="sp.name"
          >
            {{ sp.name }}
          </option>
        </select>
        
        <button
          @click="loadProcedures"
          class="btn btn-secondary btn-icon-only"
          title="重新載入"
          :disabled="loading"
        >
          <svg
            class="icon"
            :class="{ spinning: loading }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
      </div>
      
      <p v-if="error" class="error-text">{{ error }}</p>
      <p v-else-if="selectedProcedure" class="help-text">
        已選擇: <code>{{ selectedProcedure }}</code>
      </p>
    </div>

    <!-- SP 參數資訊 -->
    <div v-if="selectedProcedure && procedureParams.length > 0" class="sp-params-info">
      <div class="info-header">
        <h4 class="info-title">Stored Procedure 參數</h4>
        <button
          @click="autoMapParameters"
          class="btn btn-primary btn-sm"
          title="自動映射參數"
        >
          <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          自動映射
        </button>
      </div>
      
      <div class="params-table">
        <table>
          <thead>
            <tr>
              <th>參數名稱</th>
              <th>資料類型</th>
              <th>方向</th>
              <th>長度</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="param in procedureParams" :key="param.name">
              <td><code>{{ param.name }}</code></td>
              <td>{{ param.data_type }}</td>
              <td>
                <span :class="['param-direction', `direction-${param.direction.toLowerCase()}`]">
                  {{ param.direction }}
                </span>
              </td>
              <td>{{ param.length || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <p class="help-text">
        提示：點擊「自動映射」按鈕可以根據 SP 參數自動建立 API 參數配置
      </p>
    </div>
  </div>
</template>

<script>
import { info, toast } from '../utils/sweetalert';

export default {
  name: 'StoredProcedureSelector',
  props: {
    modelValue: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      selectedProcedure: '',
      procedures: [],
      procedureParams: [],
      loading: false,
      error: null,
    };
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(newValue) {
        this.selectedProcedure = newValue || '';
        if (this.selectedProcedure) {
          this.loadProcedureParams();
        }
      },
    },
  },
  mounted() {
    this.loadProcedures();
  },
  methods: {
    /**
     * 載入 Stored Procedures 列表
     */
    async loadProcedures() {
      this.loading = true;
      this.error = null;

      try {
        const response = await this.$axios.get('/api/admin/stored-procedures');
        
        if (response.data.success) {
          this.procedures = response.data.data;
        } else {
          this.error = '載入 Stored Procedures 失敗';
        }
      } catch (err) {
        console.error('載入 Stored Procedures 失敗:', err);
        this.error = err.response?.data?.error?.message || '載入 Stored Procedures 失敗，請稍後再試';
      } finally {
        this.loading = false;
      }
    },

    /**
     * 處理 Procedure 選擇變更
     */
    handleProcedureChange() {
      this.$emit('update:modelValue', this.selectedProcedure);
      
      if (this.selectedProcedure) {
        this.loadProcedureParams();
      } else {
        this.procedureParams = [];
      }
    },

    /**
     * 載入 Stored Procedure 參數
     */
    async loadProcedureParams() {
      if (!this.selectedProcedure) return;

      this.loading = true;
      this.error = null;

      try {
        const response = await this.$axios.get(`/api/admin/stored-procedures/${this.selectedProcedure}/parameters`);
        
        if (response.data.success) {
          this.procedureParams = response.data.data;
        } else {
          this.error = '載入 SP 參數失敗';
          this.procedureParams = [];
        }
      } catch (err) {
        console.error('載入 SP 參數失敗:', err);
        this.error = err.response?.data?.error?.message || '載入 SP 參數失敗';
        this.procedureParams = [];
      } finally {
        this.loading = false;
      }
    },

    /**
     * 自動映射參數
     */
    autoMapParameters() {
      if (this.procedureParams.length === 0) {
        info('無法映射', '沒有可映射的參數');
        return;
      }

      // 只映射 IN 和 INOUT 參數
      const inputParams = this.procedureParams.filter(
        param => param.direction === 'IN' || param.direction === 'INOUT'
      );

      if (inputParams.length === 0) {
        info('無法映射', '沒有輸入參數可映射');
        return;
      }

      // 將 SP 參數轉換為 API 參數格式
      const apiParams = inputParams.map((param, index) => {
        // 移除參數名稱前的 @ 符號
        const cleanName = param.name.replace(/^@/, '');
        
        // 映射資料類型
        let dataType = 'string';
        const spType = param.data_type.toUpperCase();
        
        if (spType.includes('INT') || spType.includes('BIGINT') || spType.includes('SMALLINT')) {
          dataType = 'integer';
        } else if (spType.includes('DECIMAL') || spType.includes('FLOAT') || spType.includes('DOUBLE')) {
          dataType = 'float';
        } else if (spType.includes('BIT') || spType.includes('BOOL')) {
          dataType = 'boolean';
        } else if (spType.includes('DATE') && !spType.includes('TIME')) {
          dataType = 'date';
        } else if (spType.includes('DATETIME') || spType.includes('TIMESTAMP')) {
          dataType = 'datetime';
        } else if (spType.includes('JSON')) {
          dataType = 'json';
        }

        return {
          name: cleanName,
          data_type: dataType,
          sp_parameter_name: param.name,
          is_required: true,
          default_value: null,
          validation_rules: [],
          position: index,
        };
      });

      // 發送自動映射事件
      this.$emit('auto-map', apiParams);
      
      toast(`已自動映射 ${apiParams.length} 個參數`, 'success');
    },
  },
};
</script>

<style scoped>
.sp-selector {
  background: white;
  border-radius: 8px;
  padding: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

.selector-container {
  display: flex;
  gap: 10px;
}

.form-select {
  flex: 1;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background-color: white;
}

.form-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-icon-only {
  padding: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon-only .icon {
  width: 20px;
  height: 20px;
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.help-text {
  font-size: 13px;
  color: #6b7280;
  margin-top: 6px;
}

.help-text code {
  font-family: 'Courier New', monospace;
  background-color: #f3f4f6;
  padding: 2px 6px;
  border-radius: 3px;
  color: #1f2937;
}

.error-text {
  font-size: 13px;
  color: #ef4444;
  margin-top: 6px;
}

/* SP 參數資訊 */
.sp-params-info {
  margin-top: 20px;
  padding: 15px;
  background-color: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.info-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.info-title {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
  color: #111827;
}

.params-table {
  overflow-x: auto;
  margin-bottom: 10px;
}

.params-table table {
  width: 100%;
  border-collapse: collapse;
  background-color: white;
  border-radius: 6px;
  overflow: hidden;
}

.params-table th {
  background-color: #f3f4f6;
  padding: 10px 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.params-table td {
  padding: 10px 12px;
  border-top: 1px solid #f3f4f6;
  font-size: 14px;
  color: #374151;
}

.params-table code {
  font-family: 'Courier New', monospace;
  background-color: #f3f4f6;
  padding: 2px 6px;
  border-radius: 3px;
  color: #1f2937;
  font-size: 13px;
}

.param-direction {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.direction-in {
  background-color: #dbeafe;
  color: #1e40af;
}

.direction-out {
  background-color: #fef3c7;
  color: #92400e;
}

.direction-inout {
  background-color: #e0e7ff;
  color: #3730a3;
}

/* 按鈕樣式 */
.btn {
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
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

/* 響應式設計 - 手機 */
@media (max-width: 768px) {
  .sp-selector {
    padding: 15px;
  }

  .selector-container {
    flex-direction: column;
    gap: 8px;
  }

  .btn-icon-only {
    width: 100%;
    justify-content: center;
  }

  .sp-params-info {
    padding: 12px;
  }

  .info-header {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }

  .info-title {
    font-size: 15px;
  }

  .info-header .btn {
    width: 100%;
    justify-content: center;
  }

  .params-table {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .params-table table {
    min-width: 500px;
  }

  .params-table th,
  .params-table td {
    padding: 8px 10px;
    font-size: 12px;
  }

  .params-table code {
    font-size: 11px;
  }

  .help-text {
    font-size: 12px;
  }
}

/* 響應式設計 - 小型手機 */
@media (max-width: 640px) {
  .sp-selector {
    padding: 12px;
  }

  .form-label {
    font-size: 13px;
  }

  .form-select {
    padding: 8px 10px;
    font-size: 13px;
  }

  .sp-params-info {
    padding: 10px;
  }

  .info-title {
    font-size: 14px;
  }

  .params-table th,
  .params-table td {
    padding: 6px 8px;
    font-size: 11px;
  }

  .param-direction {
    font-size: 10px;
    padding: 2px 6px;
  }
}
</style>

<template>
  <div class="response-mapper">
    <div class="mapper-header">
      <h3 class="mapper-title">回應映射配置</h3>
    </div>

    <!-- 標籤頁 -->
    <div class="tabs">
      <button
        :class="['tab-btn', { active: activeTab === 'fields' }]"
        @click="activeTab = 'fields'"
      >
        欄位映射
      </button>
      <button
        :class="['tab-btn', { active: activeTab === 'errors' }]"
        @click="activeTab = 'errors'"
      >
        錯誤映射
      </button>
    </div>

    <!-- 欄位映射標籤 -->
    <div v-show="activeTab === 'fields'" class="tab-content">
      <div class="section-header">
        <p class="section-description">
          配置 Stored Procedure 回傳欄位到 API 回應的映射關係
        </p>
        <button @click="addField" class="btn btn-primary btn-sm">
          <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          新增欄位
        </button>
      </div>

      <div v-if="responses.length === 0" class="empty-state">
        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p>尚未配置回應欄位</p>
        <button @click="addField" class="btn btn-primary btn-sm">
          新增第一個欄位
        </button>
      </div>

      <div v-else class="fields-list">
        <div
          v-for="(field, index) in responses"
          :key="field._id"
          class="field-item"
        >
          <div class="field-header">
            <span class="field-number">{{ index + 1 }}</span>
            <button
              @click="removeField(index)"
              class="btn-icon-sm btn-danger"
              title="刪除"
            >
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>

          <div class="field-form">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">API 欄位名稱 *</label>
                <input
                  v-model="field.field_name"
                  type="text"
                  class="form-input"
                  placeholder="例如: user_id"
                  @input="emitResponseChange"
                />
              </div>

              <div class="form-group">
                <label class="form-label">SP 欄位名稱 *</label>
                <input
                  v-model="field.sp_column_name"
                  type="text"
                  class="form-input"
                  placeholder="例如: UserID"
                  @input="emitResponseChange"
                />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">資料類型 *</label>
                <select v-model="field.data_type" class="form-select" @change="emitResponseChange">
                  <option value="">請選擇</option>
                  <option value="string">字串 (string)</option>
                  <option value="integer">整數 (integer)</option>
                  <option value="float">浮點數 (float)</option>
                  <option value="boolean">布林值 (boolean)</option>
                  <option value="date">日期 (date)</option>
                  <option value="datetime">日期時間 (datetime)</option>
                  <option value="json">JSON 物件 (json)</option>
                  <option value="array">陣列 (array)</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">轉換規則</label>
                <input
                  v-model="field.transform_rule"
                  type="text"
                  class="form-input"
                  placeholder="選填，例如: uppercase, lowercase"
                  @input="emitResponseChange"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 錯誤映射標籤 -->
    <div v-show="activeTab === 'errors'" class="tab-content">
      <div class="section-header">
        <p class="section-description">
          配置 Stored Procedure 錯誤碼到 HTTP 狀態碼和錯誤訊息的映射
        </p>
        <button @click="addError" class="btn btn-primary btn-sm">
          <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          新增錯誤映射
        </button>
      </div>

      <div v-if="errorMappings.length === 0" class="empty-state">
        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>尚未配置錯誤映射</p>
        <button @click="addError" class="btn btn-primary btn-sm">
          新增第一個錯誤映射
        </button>
      </div>

      <div v-else class="errors-list">
        <div
          v-for="(error, index) in errorMappings"
          :key="error._id"
          class="error-item"
        >
          <div class="error-header">
            <span class="error-number">{{ index + 1 }}</span>
            <button
              @click="removeError(index)"
              class="btn-icon-sm btn-danger"
              title="刪除"
            >
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>

          <div class="error-form">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">錯誤碼 *</label>
                <input
                  v-model="error.error_code"
                  type="text"
                  class="form-input"
                  placeholder="例如: USER_NOT_FOUND"
                  @input="emitErrorChange"
                />
              </div>

              <div class="form-group">
                <label class="form-label">HTTP 狀態碼 *</label>
                <select v-model.number="error.http_status" class="form-select" @change="emitErrorChange">
                  <option value="">請選擇</option>
                  <option value="400">400 - Bad Request</option>
                  <option value="401">401 - Unauthorized</option>
                  <option value="403">403 - Forbidden</option>
                  <option value="404">404 - Not Found</option>
                  <option value="409">409 - Conflict</option>
                  <option value="422">422 - Unprocessable Entity</option>
                  <option value="429">429 - Too Many Requests</option>
                  <option value="500">500 - Internal Server Error</option>
                  <option value="503">503 - Service Unavailable</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">錯誤訊息 *</label>
              <textarea
                v-model="error.error_message"
                class="form-textarea"
                rows="2"
                placeholder="例如: 找不到指定的使用者"
                @input="emitErrorChange"
              ></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ResponseMapper',
  props: {
    responses: {
      type: Array,
      default: () => [],
    },
    errorMappings: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      activeTab: 'fields',
      localResponses: [],
      localErrorMappings: [],
      nextResponseId: 1,
      nextErrorId: 1,
    };
  },
  watch: {
    responses: {
      immediate: true,
      handler(newValue) {
        if (newValue && newValue.length > 0) {
          this.localResponses = newValue.map(resp => ({
            ...resp,
            _id: resp._id || this.nextResponseId++,
          }));
        } else {
          this.localResponses = [];
        }
      },
    },
    errorMappings: {
      immediate: true,
      handler(newValue) {
        if (newValue && newValue.length > 0) {
          this.localErrorMappings = newValue.map(err => ({
            ...err,
            _id: err._id || this.nextErrorId++,
          }));
        } else {
          this.localErrorMappings = [];
        }
      },
    },
  },
  computed: {
    responses: {
      get() {
        return this.localResponses;
      },
      set(value) {
        this.localResponses = value;
      },
    },
    errorMappings: {
      get() {
        return this.localErrorMappings;
      },
      set(value) {
        this.localErrorMappings = value;
      },
    },
  },
  methods: {
    /**
     * 新增欄位映射
     */
    addField() {
      this.localResponses.push({
        _id: this.nextResponseId++,
        field_name: '',
        sp_column_name: '',
        data_type: '',
        transform_rule: null,
      });
      this.emitResponseChange();
    },

    /**
     * 移除欄位映射
     */
    removeField(index) {
      if (confirm('確定要刪除此欄位映射嗎？')) {
        this.localResponses.splice(index, 1);
        this.emitResponseChange();
      }
    },

    /**
     * 新增錯誤映射
     */
    addError() {
      this.localErrorMappings.push({
        _id: this.nextErrorId++,
        error_code: '',
        http_status: '',
        error_message: '',
      });
      this.emitErrorChange();
    },

    /**
     * 移除錯誤映射
     */
    removeError(index) {
      if (confirm('確定要刪除此錯誤映射嗎？')) {
        this.localErrorMappings.splice(index, 1);
        this.emitErrorChange();
      }
    },

    /**
     * 發送回應變更事件
     */
    emitResponseChange() {
      const cleaned = this.localResponses.map(({ _id, ...rest }) => rest);
      this.$emit('update:responses', cleaned);
    },

    /**
     * 發送錯誤映射變更事件
     */
    emitErrorChange() {
      const cleaned = this.localErrorMappings.map(({ _id, ...rest }) => rest);
      this.$emit('update:errorMappings', cleaned);
    },
  },
};
</script>

<style scoped>
.response-mapper {
  background: white;
  border-radius: 8px;
  padding: 20px;
}

.mapper-header {
  margin-bottom: 20px;
}

.mapper-title {
  font-size: 18px;
  font-weight: 600;
  margin: 0;
  color: #111827;
}

/* 標籤頁 */
.tabs {
  display: flex;
  border-bottom: 2px solid #e5e7eb;
  margin-bottom: 20px;
}

.tab-btn {
  padding: 12px 24px;
  border: none;
  background: none;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: #374151;
}

.tab-btn.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

/* 標籤內容 */
.tab-content {
  animation: fadeIn 0.3s;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.section-description {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
  flex: 1;
}

/* 空狀態 */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  background-color: #f9fafb;
  border-radius: 8px;
  border: 2px dashed #d1d5db;
}

.empty-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 15px;
  color: #9ca3af;
}

.empty-state p {
  color: #6b7280;
  margin-bottom: 15px;
}

/* 欄位列表 */
.fields-list,
.errors-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.field-item,
.error-item {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 15px;
  background-color: #fafafa;
}

.field-header,
.error-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.field-number,
.error-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background-color: #10b981;
  color: white;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 600;
}

.error-number {
  background-color: #ef4444;
}

/* 表單樣式 */
.field-form,
.error-form {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.form-input,
.form-select,
.form-textarea {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background-color: white;
  font-family: inherit;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
  resize: vertical;
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

.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
}

.btn-icon {
  width: 16px;
  height: 16px;
}

.btn-icon-sm {
  padding: 4px;
  border: none;
  background-color: transparent;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s;
}

.btn-icon-sm:hover {
  background-color: #e5e7eb;
}

.btn-icon-sm.btn-danger {
  color: #ef4444;
}

.btn-icon-sm.btn-danger:hover {
  background-color: #fee2e2;
}

.btn-icon-sm .icon {
  width: 16px;
  height: 16px;
}
</style>

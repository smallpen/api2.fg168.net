<template>
  <div class="function-editor">
    <!-- 頁面標題 -->
    <div class="page-header">
      <div class="header-left">
        <button @click="goBack" class="btn-back">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          返回
        </button>
        <div>
          <h1 class="page-title">{{ isNew ? '新增' : '編輯' }} API Function</h1>
          <p class="page-description">
            {{ isNew ? '建立新的 API Function 配置' : '修改現有的 API Function 配置' }}
          </p>
        </div>
      </div>
      <div class="header-right">
        <button @click="goBack" class="btn btn-secondary">取消</button>
        <button @click="saveFunction" class="btn btn-primary" :disabled="saving">
          <svg v-if="saving" class="btn-icon spinning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <svg v-else class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          {{ saving ? '儲存中...' : '儲存' }}
        </button>
      </div>
    </div>

    <!-- 載入中 -->
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

    <!-- 編輯表單 -->
    <div v-if="!loading && !error" class="editor-content">
      <!-- 基本資訊 -->
      <div class="editor-section">
        <h2 class="section-title">基本資訊</h2>
        <div class="section-content">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Function 名稱 *</label>
              <input
                v-model="formData.name"
                type="text"
                class="form-input"
                placeholder="例如: 取得使用者資訊"
              />
            </div>

            <div class="form-group">
              <label class="form-label">Function 識別碼 *</label>
              <input
                v-model="formData.identifier"
                type="text"
                class="form-input"
                placeholder="例如: user.get"
              />
              <p class="form-hint">
                只能包含字母、數字、點、底線和連字號
              </p>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">描述</label>
            <textarea
              v-model="formData.description"
              class="form-textarea"
              rows="3"
              placeholder="選填，描述此 API Function 的用途"
            ></textarea>
          </div>

          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input
                v-model="formData.is_active"
                type="checkbox"
                class="form-checkbox"
              />
              <span>啟用此 Function</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Stored Procedure 選擇 -->
      <div class="editor-section">
        <h2 class="section-title">Stored Procedure</h2>
        <div class="section-content">
          <StoredProcedureSelector
            v-model="formData.stored_procedure"
            @auto-map="handleAutoMap"
          />
        </div>
      </div>

      <!-- 參數配置 -->
      <div class="editor-section">
        <h2 class="section-title">參數配置</h2>
        <div class="section-content">
          <ParameterBuilder v-model="formData.parameters" />
        </div>
      </div>

      <!-- 回應映射 -->
      <div class="editor-section">
        <h2 class="section-title">回應映射</h2>
        <div class="section-content">
          <ResponseMapper
            :responses="formData.responses"
            :error-mappings="formData.error_mappings"
            @update:responses="formData.responses = $event"
            @update:errorMappings="formData.error_mappings = $event"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import StoredProcedureSelector from '../components/StoredProcedureSelector.vue';
import ParameterBuilder from '../components/ParameterBuilder.vue';
import ResponseMapper from '../components/ResponseMapper.vue';
import { confirmWarning, success, error as showError } from '../utils/sweetalert';

export default {
  name: 'FunctionEditor',
  components: {
    StoredProcedureSelector,
    ParameterBuilder,
    ResponseMapper,
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      formData: {
        name: '',
        identifier: '',
        description: '',
        stored_procedure: '',
        is_active: true,
        parameters: [],
        responses: [],
        error_mappings: [],
      },
    };
  },
  computed: {
    isNew() {
      return this.$route.params.id === 'new';
    },
    functionId() {
      return this.$route.params.id;
    },
  },
  mounted() {
    if (!this.isNew) {
      this.loadFunction();
    }
  },
  methods: {
    /**
     * 載入 Function 資料
     */
    async loadFunction() {
      this.loading = true;
      this.error = null;

      try {
        const response = await this.$axios.get(`/api/admin/functions/${this.functionId}`);

        if (response.data.success) {
          const func = response.data.data;
          this.formData = {
            name: func.name,
            identifier: func.identifier,
            description: func.description || '',
            stored_procedure: func.stored_procedure,
            is_active: func.is_active,
            parameters: func.parameters || [],
            responses: func.responses || [],
            error_mappings: func.error_mappings || [],
          };
        } else {
          this.error = '載入 Function 失敗';
        }
      } catch (err) {
        console.error('載入 Function 失敗:', err);
        this.error = err.response?.data?.error?.message || '載入 Function 失敗，請稍後再試';
      } finally {
        this.loading = false;
      }
    },

    /**
     * 儲存 Function
     */
    async saveFunction() {
      // 驗證必填欄位
      if (!this.formData.name) {
        showError('驗證失敗', '請輸入 Function 名稱');
        return;
      }

      if (!this.formData.identifier) {
        showError('驗證失敗', '請輸入 Function 識別碼');
        return;
      }

      if (!this.formData.stored_procedure) {
        showError('驗證失敗', '請選擇 Stored Procedure');
        return;
      }

      this.saving = true;

      try {
        let response;
        
        if (this.isNew) {
          // 建立新 Function
          response = await this.$axios.post('/api/admin/functions', this.formData);
        } else {
          // 更新現有 Function
          response = await this.$axios.put(`/api/admin/functions/${this.functionId}`, this.formData);
        }

        if (response.data.success) {
          await success('儲存成功', response.data.message || '已成功儲存 Function 配置');
          this.$router.push({ name: 'functions' });
        }
      } catch (err) {
        console.error('儲存失敗:', err);
        
        if (err.response?.data?.error?.code === 'VALIDATION_ERROR') {
          const errors = err.response.data.error.details;
          const errorMessages = Object.values(errors).flat().join('\n');
          showError('驗證失敗', errorMessages);
        } else {
          showError('儲存失敗', err.response?.data?.error?.message || '儲存失敗，請稍後再試');
        }
      } finally {
        this.saving = false;
      }
    },

    /**
     * 處理自動映射參數
     */
    handleAutoMap(params) {
      this.formData.parameters = params;
    },

    /**
     * 返回列表頁
     */
    async goBack() {
      const confirmed = await confirmWarning(
        '確定要離開？',
        '未儲存的變更將會遺失',
        '離開',
        '取消'
      );
      
      if (confirmed) {
        this.$router.push({ name: 'functions' });
      }
    },
  },
};
</script>

<style scoped>
.function-editor {
  max-width: 1200px;
  margin: 0 auto;
}

/* 頁面標題 */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e5e7eb;
}

.header-left {
  display: flex;
  align-items: flex-start;
  gap: 15px;
}

.btn-back {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  background-color: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  color: #374151;
}

.btn-back:hover {
  background-color: #f3f4f6;
}

.btn-back .icon {
  width: 18px;
  height: 18px;
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

.spinning {
  animation: spin 1s linear infinite;
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
  margin-bottom: 20px;
}

.error-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

/* 編輯器內容 */
.editor-content {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.editor-section {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  margin: 0;
  padding: 20px;
  background-color: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  color: #111827;
}

.section-content {
  padding: 20px;
}

/* 表單樣式 */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
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

.form-input,
.form-textarea {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background-color: white;
  font-family: inherit;
}

.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
  resize: vertical;
}

.form-hint {
  font-size: 12px;
  color: #6b7280;
  margin-top: 4px;
}

/* Checkbox */
.checkbox-group {
  flex-direction: row;
  align-items: center;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 14px;
  color: #374151;
}

.form-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
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

.btn-primary:hover:not(:disabled) {
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

.btn-icon {
  width: 16px;
  height: 16px;
}

/* 響應式設計 - 平板 */
@media (max-width: 1024px) {
  .form-row {
    grid-template-columns: 1fr;
    gap: 15px;
  }

  .section-title {
    font-size: 16px;
  }
}

/* 響應式設計 - 手機 */
@media (max-width: 768px) {
  .function-editor {
    margin: 0;
  }

  .page-header {
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
  }

  .header-left {
    flex-direction: column;
    gap: 12px;
    width: 100%;
  }

  .btn-back {
    width: fit-content;
  }

  .page-title {
    font-size: 20px;
  }

  .page-description {
    font-size: 13px;
  }

  .header-right {
    width: 100%;
    flex-direction: row;
  }

  .header-right .btn {
    flex: 1;
    justify-content: center;
  }

  .editor-content {
    gap: 20px;
  }

  .editor-section {
    border-radius: 6px;
  }

  .section-title {
    font-size: 16px;
    padding: 15px;
  }

  .section-content {
    padding: 15px;
  }

  .form-row {
    grid-template-columns: 1fr;
    gap: 15px;
    margin-bottom: 15px;
  }

  .form-label {
    font-size: 13px;
  }

  .form-input,
  .form-textarea {
    padding: 8px 10px;
    font-size: 13px;
  }

  .form-hint {
    font-size: 11px;
  }
}

/* 響應式設計 - 小型手機 */
@media (max-width: 640px) {
  .page-header {
    margin-bottom: 15px;
    padding-bottom: 12px;
  }

  .page-title {
    font-size: 18px;
  }

  .page-description {
    font-size: 12px;
  }

  .btn-back {
    padding: 6px 10px;
    font-size: 13px;
  }

  .btn-back .icon {
    width: 16px;
    height: 16px;
  }

  .header-right .btn {
    padding: 8px 12px;
    font-size: 13px;
  }

  .btn-icon {
    width: 14px;
    height: 14px;
  }

  .editor-content {
    gap: 15px;
  }

  .section-title {
    font-size: 15px;
    padding: 12px;
  }

  .section-content {
    padding: 12px;
  }

  .form-row {
    gap: 12px;
    margin-bottom: 12px;
  }

  .checkbox-label {
    font-size: 13px;
  }

  .form-checkbox {
    width: 16px;
    height: 16px;
  }
}
</style>

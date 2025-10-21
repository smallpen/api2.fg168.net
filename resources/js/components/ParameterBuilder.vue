<template>
  <div class="parameter-builder">
    <div class="builder-header">
      <h3 class="builder-title">參數配置</h3>
      <button @click="addParameter" class="btn btn-primary btn-sm">
        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        新增參數
      </button>
    </div>

    <div v-if="parameters.length === 0" class="empty-state">
      <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p>尚未配置參數</p>
      <button @click="addParameter" class="btn btn-primary btn-sm">
        新增第一個參數
      </button>
    </div>

    <div v-else class="parameters-list">
      <div
        v-for="(param, index) in parameters"
        :key="param._id"
        class="parameter-item"
      >
        <div class="parameter-header">
          <div class="parameter-order">
            <span class="order-badge">{{ index + 1 }}</span>
          </div>
          <div class="parameter-actions">
            <button
              @click="moveUp(index)"
              :disabled="index === 0"
              class="btn-icon-sm"
              title="上移"
            >
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <button
              @click="moveDown(index)"
              :disabled="index === parameters.length - 1"
              class="btn-icon-sm"
              title="下移"
            >
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <button
              @click="removeParameter(index)"
              class="btn-icon-sm btn-danger"
              title="刪除"
            >
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <div class="parameter-form">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">參數名稱 *</label>
              <input
                v-model="param.name"
                type="text"
                class="form-input"
                placeholder="例如: user_id"
                @input="emitChange"
              />
            </div>

            <div class="form-group">
              <label class="form-label">資料類型 *</label>
              <select v-model="param.data_type" class="form-select" @change="emitChange">
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
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">SP 參數名稱 *</label>
              <input
                v-model="param.sp_parameter_name"
                type="text"
                class="form-input"
                placeholder="例如: @user_id"
                @input="emitChange"
              />
            </div>

            <div class="form-group">
              <label class="form-label">預設值</label>
              <input
                v-model="param.default_value"
                type="text"
                class="form-input"
                placeholder="選填"
                @input="emitChange"
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group checkbox-group">
              <label class="checkbox-label">
                <input
                  v-model="param.is_required"
                  type="checkbox"
                  class="form-checkbox"
                  @change="emitChange"
                />
                <span>必填參數</span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">驗證規則</label>
            <div class="validation-rules">
              <div
                v-for="(rule, ruleIndex) in param.validation_rules"
                :key="ruleIndex"
                class="rule-item"
              >
                <input
                  v-model="param.validation_rules[ruleIndex]"
                  type="text"
                  class="form-input form-input-sm"
                  placeholder="例如: min:1, max:100"
                  @input="emitChange"
                />
                <button
                  @click="removeRule(index, ruleIndex)"
                  class="btn-icon-sm btn-danger"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <button @click="addRule(index)" class="btn btn-secondary btn-sm">
                <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                新增規則
              </button>
            </div>
            <p class="form-hint">
              支援 Laravel 驗證規則，例如: required, email, min:3, max:100, regex:pattern
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ParameterBuilder',
  props: {
    modelValue: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      parameters: [],
      nextId: 1,
    };
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(newValue) {
        if (newValue && newValue.length > 0) {
          this.parameters = newValue.map((param, index) => ({
            ...param,
            _id: param._id || this.nextId++,
            position: index,
            validation_rules: param.validation_rules || [],
          }));
        } else {
          this.parameters = [];
        }
      },
    },
  },
  methods: {
    /**
     * 新增參數
     */
    addParameter() {
      this.parameters.push({
        _id: this.nextId++,
        name: '',
        data_type: '',
        sp_parameter_name: '',
        is_required: true,
        default_value: null,
        validation_rules: [],
        position: this.parameters.length,
      });
      this.emitChange();
    },

    /**
     * 移除參數
     */
    removeParameter(index) {
      if (confirm('確定要刪除此參數嗎？')) {
        this.parameters.splice(index, 1);
        this.updatePositions();
        this.emitChange();
      }
    },

    /**
     * 上移參數
     */
    moveUp(index) {
      if (index > 0) {
        const temp = this.parameters[index];
        this.parameters[index] = this.parameters[index - 1];
        this.parameters[index - 1] = temp;
        this.updatePositions();
        this.emitChange();
      }
    },

    /**
     * 下移參數
     */
    moveDown(index) {
      if (index < this.parameters.length - 1) {
        const temp = this.parameters[index];
        this.parameters[index] = this.parameters[index + 1];
        this.parameters[index + 1] = temp;
        this.updatePositions();
        this.emitChange();
      }
    },

    /**
     * 新增驗證規則
     */
    addRule(paramIndex) {
      if (!this.parameters[paramIndex].validation_rules) {
        this.parameters[paramIndex].validation_rules = [];
      }
      this.parameters[paramIndex].validation_rules.push('');
      this.emitChange();
    },

    /**
     * 移除驗證規則
     */
    removeRule(paramIndex, ruleIndex) {
      this.parameters[paramIndex].validation_rules.splice(ruleIndex, 1);
      this.emitChange();
    },

    /**
     * 更新參數位置
     */
    updatePositions() {
      this.parameters.forEach((param, index) => {
        param.position = index;
      });
    },

    /**
     * 發送變更事件
     */
    emitChange() {
      const cleanedParameters = this.parameters.map(param => {
        const { _id, ...rest } = param;
        return rest;
      });
      this.$emit('update:modelValue', cleanedParameters);
    },
  },
};
</script>

<style scoped>
.parameter-builder {
  background: white;
  border-radius: 8px;
  padding: 20px;
}

.builder-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.builder-title {
  font-size: 18px;
  font-weight: 600;
  margin: 0;
  color: #111827;
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

/* 參數列表 */
.parameters-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.parameter-item {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 15px;
  background-color: #fafafa;
}

.parameter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.parameter-order {
  display: flex;
  align-items: center;
}

.order-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background-color: #3b82f6;
  color: white;
  border-radius: 50%;
  font-size: 14px;
  font-weight: 600;
}

.parameter-actions {
  display: flex;
  gap: 5px;
}

/* 表單樣式 */
.parameter-form {
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
.form-select {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background-color: white;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input-sm {
  padding: 8px 10px;
  font-size: 13px;
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

/* 驗證規則 */
.validation-rules {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rule-item {
  display: flex;
  gap: 8px;
  align-items: center;
}

.rule-item .form-input {
  flex: 1;
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

.btn-icon-sm:disabled {
  opacity: 0.3;
  cursor: not-allowed;
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

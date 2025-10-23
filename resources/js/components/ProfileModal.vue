<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="modal">
      <div class="modal-header">
        <h2>編輯個人資料</h2>
        <button @click="close" class="btn-close">&times;</button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="saveProfile">
          <div class="form-group">
            <label>名稱 <span class="required">*</span></label>
            <input v-model="formData.name" type="text" class="form-control" required />
          </div>

          <div class="form-group">
            <label>電子郵件 <span class="required">*</span></label>
            <input v-model="formData.email" type="email" class="form-control" required />
          </div>

          <div class="form-divider">
            <span>修改密碼（選填）</span>
          </div>

          <div class="form-group">
            <label>目前密碼</label>
            <input
              v-model="formData.current_password"
              type="password"
              class="form-control"
              placeholder="如需修改密碼請輸入目前密碼"
            />
          </div>

          <div class="form-group">
            <label>新密碼</label>
            <input
              v-model="formData.password"
              type="password"
              class="form-control"
              placeholder="留空表示不修改密碼"
            />
          </div>

          <div class="form-group">
            <label>確認新密碼</label>
            <input
              v-model="formData.password_confirmation"
              type="password"
              class="form-control"
              placeholder="請再次輸入新密碼"
            />
          </div>

          <div v-if="errorMessage" class="alert alert-error">
            {{ errorMessage }}
          </div>

          <div class="form-actions">
            <button type="button" @click="close" class="btn btn-secondary">取消</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? '儲存中...' : '儲存' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { showSuccess, showError } from '../utils/sweetalert';

export default {
  name: 'ProfileModal',
  props: {
    show: {
      type: Boolean,
      default: false,
    },
    user: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      saving: false,
      errorMessage: '',
      formData: {
        name: '',
        email: '',
        current_password: '',
        password: '',
        password_confirmation: '',
      },
    };
  },
  watch: {
    show(newVal) {
      if (newVal && this.user) {
        this.formData.name = this.user.name;
        this.formData.email = this.user.email;
        this.formData.current_password = '';
        this.formData.password = '';
        this.formData.password_confirmation = '';
        this.errorMessage = '';
      }
    },
  },
  methods: {
    close() {
      this.$emit('close');
    },

    async saveProfile() {
      this.errorMessage = '';

      // 驗證密碼
      if (this.formData.password || this.formData.password_confirmation) {
        if (!this.formData.current_password) {
          this.errorMessage = '請輸入目前的密碼';
          return;
        }
        if (this.formData.password !== this.formData.password_confirmation) {
          this.errorMessage = '新密碼與確認密碼不一致';
          return;
        }
      }

      this.saving = true;
      try {
        const data = {
          name: this.formData.name,
          email: this.formData.email,
        };

        if (this.formData.password) {
          data.current_password = this.formData.current_password;
          data.password = this.formData.password;
          data.password_confirmation = this.formData.password_confirmation;
        }

        const response = await this.$axios.put('/api/admin/profile', data);

        if (response.data.success) {
          showSuccess('個人資料更新成功');
          this.$emit('updated', response.data.data);
          this.close();
        }
      } catch (error) {
        console.error('更新個人資料失敗:', error);
        if (error.response?.data?.errors) {
          const errors = error.response.data.errors;
          this.errorMessage = Object.values(errors).flat().join(', ');
        } else {
          this.errorMessage = error.response?.data?.message || '更新失敗';
        }
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
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

.modal {
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
  border-bottom: 1px solid #eee;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.btn-close {
  background: none;
  border: none;
  font-size: 28px;
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

.modal-body {
  padding: 20px;
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

.form-control {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-divider {
  margin: 25px 0 20px;
  padding-top: 20px;
  border-top: 1px solid #eee;
  text-align: center;
}

.form-divider span {
  display: inline-block;
  padding: 0 15px;
  background: white;
  color: #6b7280;
  font-size: 13px;
  font-weight: 500;
  position: relative;
  top: -30px;
}

.alert {
  padding: 12px 15px;
  border-radius: 4px;
  margin-bottom: 20px;
  font-size: 14px;
}

.alert-error {
  background-color: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

/* 按鈕 */
.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background-color: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background-color: #2563eb;
}

.btn-secondary {
  background-color: #6b7280;
  color: white;
}

.btn-secondary:hover {
  background-color: #4b5563;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>

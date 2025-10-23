<template>
  <div class="permission-manager">
    <!-- 頁面標題 -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">權限配置</h1>
        <p class="page-description">管理客戶端和角色的 API Function 存取權限</p>
      </div>
    </div>

    <!-- 標籤頁切換 -->
    <div class="tabs">
      <button
        :class="['tab-btn', { active: activeTab === 'client' }]"
        @click="activeTab = 'client'"
      >
        客戶端權限
      </button>
      <button
        :class="['tab-btn', { active: activeTab === 'client-role' }]"
        @click="activeTab = 'client-role'"
      >
        客戶端角色
      </button>
      <button
        :class="['tab-btn', { active: activeTab === 'admin-role' }]"
        @click="activeTab = 'admin-role'"
      >
        後台角色
      </button>
    </div>

    <!-- 客戶端權限標籤頁 -->
    <div v-if="activeTab === 'client'" class="tab-content">
      <!-- 選擇客戶端 -->
      <div class="selector-section">
        <label>選擇客戶端</label>
        <select v-model="selectedClientId" @change="loadClientPermissions" class="selector">
          <option value="">請選擇客戶端...</option>
          <option v-for="client in clients" :key="client.id" :value="client.id">
            {{ client.name }} ({{ client.api_key }})
          </option>
        </select>
      </div>

      <!-- 權限矩陣 -->
      <div v-if="selectedClientId" class="permission-matrix">
        <div class="matrix-header">
          <h3>Function 權限矩陣</h3>
          <div class="matrix-actions">
            <button @click="selectAllFunctions" class="btn btn-sm btn-secondary">
              全選
            </button>
            <button @click="deselectAllFunctions" class="btn btn-sm btn-secondary">
              全不選
            </button>
            <button @click="saveClientPermissions" class="btn btn-sm btn-primary" :disabled="saving">
              {{ saving ? '儲存中...' : '儲存變更' }}
            </button>
          </div>
        </div>

        <!-- 搜尋 Functions -->
        <div class="search-box">
          <input
            v-model="functionSearch"
            type="text"
            placeholder="搜尋 Function 名稱或識別碼..."
            class="search-input"
          />
        </div>

        <!-- 載入中 -->
        <div v-if="loadingPermissions" class="loading-container">
          <div class="spinner"></div>
          <p>載入權限資料中...</p>
        </div>

        <!-- Functions 列表 -->
        <div v-else class="functions-list">
          <div
            v-for="func in filteredFunctions"
            :key="func.id"
            class="function-item"
          >
            <div class="function-info">
              <label class="checkbox-label">
                <input
                  type="checkbox"
                  v-model="clientPermissions[func.id]"
                  class="checkbox"
                />
                <div class="function-details">
                  <strong>{{ func.name }}</strong>
                  <code class="identifier">{{ func.identifier }}</code>
                  <span v-if="func.description" class="description">{{ func.description }}</span>
                </div>
              </label>
            </div>
            <div class="function-meta">
              <span :class="['status-badge', func.is_active ? 'status-active' : 'status-inactive']">
                {{ func.is_active ? '已啟用' : '已停用' }}
              </span>
            </div>
          </div>

          <div v-if="filteredFunctions.length === 0" class="empty-state">
            <p>找不到符合條件的 Function</p>
          </div>
        </div>
      </div>

      <div v-else class="empty-selection">
        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <p>請選擇一個客戶端以配置權限</p>
      </div>
    </div>

    <!-- 客戶端角色管理標籤頁 -->
    <div v-if="activeTab === 'client-role'" class="tab-content">
      <!-- 角色列表 -->
      <div class="roles-section">
        <div class="section-header">
          <h3>系統角色</h3>
          <button @click="showCreateRoleModal = true" class="btn btn-sm btn-primary">
            新增角色
          </button>
        </div>

        <!-- 載入中 -->
        <div v-if="loadingRoles" class="loading-container">
          <div class="spinner"></div>
          <p>載入角色資料中...</p>
        </div>

        <!-- 角色卡片 -->
        <div v-else class="roles-grid">
          <div
            v-for="role in roles"
            :key="role.id"
            :class="['role-card', { selected: selectedRoleId === role.id }]"
            @click="selectRole(role.id)"
          >
            <div class="role-header">
              <h4>{{ role.display_name || role.name }}</h4>
              <div class="role-actions">
                <button
                  @click.stop="showEditRoleModal(role)"
                  class="btn-icon-action btn-edit"
                  title="編輯角色"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  v-if="!isSystemRole(role.name)"
                  @click.stop="deleteRole(role)"
                  class="btn-icon-action btn-danger"
                  title="刪除角色"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
            <p class="role-description">{{ role.description }}</p>
            <p class="role-name-hint">識別碼：{{ role.name }}</p>
            <div class="role-stats">
              <span class="stat">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                {{ role.clients_count || 0 }} 客戶端
              </span>
              <span class="stat">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                {{ role.permissions_count || 0 }} 權限
              </span>
            </div>
          </div>

          <div v-if="roles.length === 0" class="empty-state">
            <p>尚無角色</p>
          </div>
        </div>

        <!-- 角色權限配置 -->
        <div v-if="selectedRoleId" class="role-permissions-section">
          <div class="section-header">
            <h3>角色權限配置</h3>
            <button @click="saveRolePermissions" class="btn btn-sm btn-primary" :disabled="saving">
              {{ saving ? '儲存中...' : '儲存變更' }}
            </button>
          </div>

          <!-- 搜尋 Functions -->
          <div class="search-box">
            <input
              v-model="roleFunctionSearch"
              type="text"
              placeholder="搜尋 Function 名稱或識別碼..."
              class="search-input"
            />
          </div>

          <!-- Functions 列表 -->
          <div class="functions-list">
            <div
              v-for="func in filteredRoleFunctions"
              :key="func.id"
              class="function-item"
            >
              <div class="function-info">
                <label class="checkbox-label">
                  <input
                    type="checkbox"
                    v-model="rolePermissions[func.id]"
                    class="checkbox"
                  />
                  <div class="function-details">
                    <strong>{{ func.name }}</strong>
                    <code class="identifier">{{ func.identifier }}</code>
                    <span v-if="func.description" class="description">{{ func.description }}</span>
                  </div>
                </label>
              </div>
              <div class="function-meta">
                <span :class="['status-badge', func.is_active ? 'status-active' : 'status-inactive']">
                  {{ func.is_active ? '已啟用' : '已停用' }}
                </span>
              </div>
            </div>

            <div v-if="filteredRoleFunctions.length === 0" class="empty-state">
              <p>找不到符合條件的 Function</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 後台角色管理標籤頁 -->
    <div v-if="activeTab === 'admin-role'" class="tab-content">
      <!-- 後台角色列表 -->
      <div class="roles-section">
        <div class="section-header">
          <h3>後台管理角色</h3>
          <p class="section-description">管理後台管理員可以使用哪些後台功能</p>
        </div>

        <!-- 載入中 -->
        <div v-if="loadingRoles" class="loading-container">
          <div class="spinner"></div>
          <p>載入角色資料中...</p>
        </div>

        <!-- 後台角色卡片 -->
        <div v-else class="roles-grid">
          <div
            v-for="role in adminRoles"
            :key="role.id"
            :class="['role-card', { selected: selectedAdminRoleId === role.id }]"
            @click="selectAdminRole(role.id)"
          >
            <div class="role-header">
              <h4>{{ role.display_name || role.name }}</h4>
              <div class="role-actions">
                <button
                  @click.stop="showEditAdminRoleModal(role)"
                  class="btn-icon-action btn-edit"
                  title="編輯角色"
                >
                  <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <span v-if="role.name === 'super_admin'" class="system-badge">系統角色</span>
              </div>
            </div>
            <p class="role-description">{{ role.description }}</p>
            <p class="role-name-hint">識別碼：{{ role.name }}</p>
            <div class="role-stats">
              <span class="stat">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                {{ role.users_count || 0 }} 使用者
              </span>
              <span class="stat">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                {{ role.permissions_count || 0 }} 權限
              </span>
            </div>
          </div>

          <div v-if="adminRoles.length === 0" class="empty-state">
            <p>尚無後台角色</p>
          </div>
        </div>

        <!-- 後台角色權限配置 -->
        <div v-if="selectedAdminRoleId" class="role-permissions-section">
          <div class="section-header">
            <h3>後台功能權限配置</h3>
            <button @click="saveAdminRolePermissions" class="btn btn-sm btn-primary" :disabled="saving">
              {{ saving ? '儲存中...' : '儲存變更' }}
            </button>
          </div>

          <!-- 權限列表 -->
          <div class="permissions-list">
            <div
              v-for="permission in adminPermissions"
              :key="permission.id"
              class="permission-item"
            >
              <label class="checkbox-label">
                <input
                  type="checkbox"
                  :value="permission.id"
                  v-model="adminRolePermissions[permission.id]"
                  class="checkbox"
                  :disabled="isAdminRoleSuperAdmin"
                />
                <div class="permission-details">
                  <strong>{{ permission.display_name }}</strong>
                  <span class="description">{{ permission.description }}</span>
                  <code class="identifier">{{ permission.name }}</code>
                </div>
              </label>
            </div>

            <div v-if="adminPermissions.length === 0" class="empty-state">
              <p>找不到後台權限</p>
            </div>
          </div>

          <div v-if="isAdminRoleSuperAdmin" class="alert alert-info">
            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>超級管理員擁有所有權限，無法修改</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 創建角色 Modal -->
    <div v-if="showCreateRoleModal" class="modal-overlay" @click.self="showCreateRoleModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>新增客戶端角色</h2>
          <button @click="showCreateRoleModal = false" class="btn-close">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="createRole">
            <div class="form-group">
              <label>角色名稱（英文識別碼）*</label>
              <input v-model="newRole.name" type="text" class="form-input" required placeholder="例如：premium_partner" />
              <small class="form-hint">只能使用小寫英文字母、數字和底線</small>
            </div>

            <div class="form-group">
              <label>顯示名稱 *</label>
              <input v-model="newRole.display_name" type="text" class="form-input" required placeholder="例如：高級合作夥伴" />
            </div>

            <div class="form-group">
              <label>角色描述</label>
              <textarea v-model="newRole.description" class="form-input" rows="3" placeholder="描述此角色的用途和權限範圍"></textarea>
            </div>

            <div class="modal-footer">
              <button type="button" @click="showCreateRoleModal = false" class="btn btn-secondary">
                取消
              </button>
              <button type="submit" class="btn btn-primary" :disabled="creating">
                {{ creating ? '建立中...' : '建立角色' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- 編輯客戶端角色 Modal -->
    <div v-if="showEditRoleModalFlag" class="modal-overlay" @click.self="closeEditRoleModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>編輯客戶端角色</h2>
          <button @click="closeEditRoleModal" class="btn-close">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="updateRole">
            <div class="form-group">
              <label>角色名稱（英文識別碼）*</label>
              <input 
                v-model="editingRole.name" 
                type="text" 
                class="form-input" 
                required 
                :disabled="isSystemRole(editingRole.name)"
                placeholder="例如：premium_partner" 
              />
              <small v-if="isSystemRole(editingRole.name)" class="form-hint">系統角色的識別碼不可修改</small>
              <small v-else class="form-hint">只能使用小寫英文字母、數字和底線</small>
            </div>

            <div class="form-group">
              <label>顯示名稱 *</label>
              <input v-model="editingRole.display_name" type="text" class="form-input" required placeholder="例如：高級合作夥伴" />
            </div>

            <div class="form-group">
              <label>角色描述</label>
              <textarea v-model="editingRole.description" class="form-input" rows="3" placeholder="描述此角色的用途和權限範圍"></textarea>
            </div>

            <div class="modal-footer">
              <button type="button" @click="closeEditRoleModal" class="btn btn-secondary">
                取消
              </button>
              <button type="submit" class="btn btn-primary" :disabled="updating">
                {{ updating ? '更新中...' : '更新角色' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- 編輯後台角色 Modal -->
    <div v-if="showEditAdminRoleModalFlag" class="modal-overlay" @click.self="closeEditAdminRoleModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>編輯後台角色</h2>
          <button @click="closeEditAdminRoleModal" class="btn-close">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="updateAdminRole">
            <div class="form-group">
              <label>角色名稱（英文識別碼）*</label>
              <input 
                v-model="editingAdminRole.name" 
                type="text" 
                class="form-input" 
                required 
                :disabled="isAdminSystemRole(editingAdminRole.name)"
                placeholder="例如：content_manager" 
              />
              <small v-if="isAdminSystemRole(editingAdminRole.name)" class="form-hint">系統角色的識別碼不可修改</small>
              <small v-else class="form-hint">只能使用小寫英文字母、數字和底線</small>
            </div>

            <div class="form-group">
              <label>顯示名稱 *</label>
              <input v-model="editingAdminRole.display_name" type="text" class="form-input" required placeholder="例如：內容管理員" />
            </div>

            <div class="form-group">
              <label>角色描述</label>
              <textarea v-model="editingAdminRole.description" class="form-input" rows="3" placeholder="描述此角色的用途和權限範圍"></textarea>
            </div>

            <div class="modal-footer">
              <button type="button" @click="closeEditAdminRoleModal" class="btn btn-secondary">
                取消
              </button>
              <button type="submit" class="btn btn-primary" :disabled="updatingAdmin">
                {{ updatingAdmin ? '更新中...' : '更新角色' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { confirmWarning, error as showError, toast } from '../utils/sweetalert';

export default {
  name: 'PermissionManager',
  data() {
    return {
      activeTab: 'client',
      
      // 客戶端相關
      clients: [],
      selectedClientId: '',
      clientPermissions: {},
      
      // 客戶端角色相關
      roles: [],
      selectedRoleId: null,
      rolePermissions: {},
      
      // 後台角色相關
      adminRoles: [],
      selectedAdminRoleId: null,
      adminRolePermissions: {},
      
      // Functions
      functions: [],
      functionSearch: '',
      roleFunctionSearch: '',
      
      // 後台權限
      adminPermissions: [],
      
      // 狀態
      loadingPermissions: false,
      loadingRoles: false,
      saving: false,
      
      // Modal
      showCreateRoleModal: false,
      showEditRoleModalFlag: false,
      showEditAdminRoleModalFlag: false,
      creating: false,
      updating: false,
      updatingAdmin: false,
      newRole: {
        name: '',
        display_name: '',
        description: '',
      },
      editingRole: {
        id: null,
        name: '',
        display_name: '',
        description: '',
      },
      editingAdminRole: {
        id: null,
        name: '',
        display_name: '',
        description: '',
      },
    };
  },
  computed: {
    filteredFunctions() {
      if (!this.functionSearch) return this.functions;
      
      const search = this.functionSearch.toLowerCase();
      return this.functions.filter(func =>
        func.name.toLowerCase().includes(search) ||
        func.identifier.toLowerCase().includes(search) ||
        (func.description && func.description.toLowerCase().includes(search))
      );
    },
    
    filteredRoleFunctions() {
      if (!this.roleFunctionSearch) return this.functions;
      
      const search = this.roleFunctionSearch.toLowerCase();
      return this.functions.filter(func =>
        func.name.toLowerCase().includes(search) ||
        func.identifier.toLowerCase().includes(search) ||
        (func.description && func.description.toLowerCase().includes(search))
      );
    },
    
    isAdminRoleSuperAdmin() {
      if (!this.selectedAdminRoleId) return false;
      const role = this.adminRoles.find(r => r.id === this.selectedAdminRoleId);
      return role && role.name === 'super_admin';
    },
  },
  mounted() {
    this.loadClients();
    this.loadFunctions();
    this.loadRoles();
    this.loadAdminRoles();
    this.loadAdminPermissions();
  },
  methods: {
    /**
     * 載入客戶端列表
     */
    async loadClients() {
      try {
        const response = await this.$axios.get('/api/admin/clients', {
          params: { per_page: 1000, is_active: 1 }
        });
        
        if (response.data.success) {
          this.clients = response.data.data;
        }
      } catch (err) {
        console.error('載入客戶端列表失敗:', err);
      }
    },

    /**
     * 載入 Functions 列表
     */
    async loadFunctions() {
      try {
        const response = await this.$axios.get('/api/admin/functions', {
          params: { per_page: 1000 }
        });
        
        if (response.data.success) {
          this.functions = response.data.data;
        }
      } catch (err) {
        console.error('載入 Functions 列表失敗:', err);
      }
    },

    /**
     * 載入角色列表
     */
    async loadRoles() {
      this.loadingRoles = true;
      
      try {
        const response = await this.$axios.get('/api/admin/roles');
        
        if (response.data.success) {
          this.roles = response.data.data;
        }
      } catch (err) {
        console.error('載入角色列表失敗:', err);
      } finally {
        this.loadingRoles = false;
      }
    },

    /**
     * 載入客戶端權限
     */
    async loadClientPermissions() {
      if (!this.selectedClientId) return;
      
      this.loadingPermissions = true;
      
      try {
        const response = await this.$axios.get(`/api/admin/clients/${this.selectedClientId}/permissions`);
        
        if (response.data.success) {
          // 初始化所有 Function 為 false
          this.clientPermissions = {};
          this.functions.forEach(func => {
            this.clientPermissions[func.id] = false;
          });
          
          // 設定已授權的 Function
          response.data.data.forEach(permission => {
            if (permission.allowed) {
              this.clientPermissions[permission.function_id] = true;
            }
          });
        }
      } catch (err) {
        console.error('載入客戶端權限失敗:', err);
        showError('載入失敗', '載入客戶端權限失敗，請稍後再試');
      } finally {
        this.loadingPermissions = false;
      }
    },

    /**
     * 儲存客戶端權限
     */
    async saveClientPermissions() {
      if (!this.selectedClientId) return;
      
      this.saving = true;
      
      try {
        const permissions = Object.keys(this.clientPermissions)
          .filter(functionId => this.clientPermissions[functionId])
          .map(functionId => parseInt(functionId));
        
        const response = await this.$axios.post(
          `/api/admin/clients/${this.selectedClientId}/permissions`,
          { function_ids: permissions }
        );
        
        if (response.data.success) {
          toast('客戶端權限已更新', 'success');
        }
      } catch (err) {
        console.error('儲存客戶端權限失敗:', err);
        showError('儲存失敗', err.response?.data?.error?.message || '儲存客戶端權限失敗，請稍後再試');
      } finally {
        this.saving = false;
      }
    },

    /**
     * 全選 Functions
     */
    selectAllFunctions() {
      this.functions.forEach(func => {
        this.clientPermissions[func.id] = true;
      });
    },

    /**
     * 全不選 Functions
     */
    deselectAllFunctions() {
      this.functions.forEach(func => {
        this.clientPermissions[func.id] = false;
      });
    },

    /**
     * 選擇角色
     */
    async selectRole(roleId) {
      this.selectedRoleId = roleId;
      await this.loadRolePermissions();
    },

    /**
     * 載入角色權限
     */
    async loadRolePermissions() {
      if (!this.selectedRoleId) return;
      
      try {
        const response = await this.$axios.get(`/api/admin/roles/${this.selectedRoleId}/permissions`);
        
        if (response.data.success) {
          // 初始化所有 Function 為 false
          this.rolePermissions = {};
          this.functions.forEach(func => {
            this.rolePermissions[func.id] = false;
          });
          
          // 設定已授權的 Function
          response.data.data.forEach(permission => {
            if (permission.resource_type === 'function' && permission.action === 'execute') {
              if (permission.resource_id) {
                this.rolePermissions[permission.resource_id] = true;
              }
            }
          });
        }
      } catch (err) {
        console.error('載入角色權限失敗:', err);
        showError('載入失敗', '載入角色權限失敗，請稍後再試');
      }
    },

    /**
     * 儲存角色權限
     */
    async saveRolePermissions() {
      if (!this.selectedRoleId) return;
      
      this.saving = true;
      
      try {
        const permissions = Object.keys(this.rolePermissions)
          .filter(functionId => this.rolePermissions[functionId])
          .map(functionId => parseInt(functionId));
        
        const response = await this.$axios.post(
          `/api/admin/roles/${this.selectedRoleId}/permissions`,
          { function_ids: permissions }
        );
        
        if (response.data.success) {
          toast('角色權限已更新', 'success');
        }
      } catch (err) {
        console.error('儲存角色權限失敗:', err);
        showError('儲存失敗', err.response?.data?.error?.message || '儲存角色權限失敗，請稍後再試');
      } finally {
        this.saving = false;
      }
    },

    /**
     * 創建角色
     */
    async createRole() {
      this.creating = true;
      
      try {
        const response = await this.$axios.post('/api/admin/roles', this.newRole);
        
        if (response.data.success) {
          this.showCreateRoleModal = false;
          this.newRole = { name: '', display_name: '', description: '' };
          this.loadRoles();
          toast('角色創建成功', 'success');
        }
      } catch (err) {
        console.error('創建角色失敗:', err);
        showError('創建失敗', err.response?.data?.error?.message || '創建角色失敗，請稍後再試');
      } finally {
        this.creating = false;
      }
    },

    /**
     * 顯示編輯角色 Modal
     */
    showEditRoleModal(role) {
      this.editingRole = {
        id: role.id,
        name: role.name,
        display_name: role.display_name,
        description: role.description || '',
      };
      this.showEditRoleModalFlag = true;
    },

    /**
     * 關閉編輯角色 Modal
     */
    closeEditRoleModal() {
      this.showEditRoleModalFlag = false;
      this.editingRole = {
        id: null,
        name: '',
        display_name: '',
        description: '',
      };
    },

    /**
     * 更新角色
     */
    async updateRole() {
      this.updating = true;
      
      try {
        const response = await this.$axios.put(`/api/admin/roles/${this.editingRole.id}`, {
          name: this.editingRole.name,
          display_name: this.editingRole.display_name,
          description: this.editingRole.description,
        });
        
        if (response.data.success) {
          this.closeEditRoleModal();
          this.loadRoles();
          toast('角色更新成功', 'success');
        }
      } catch (err) {
        console.error('更新角色失敗:', err);
        showError('更新失敗', err.response?.data?.error?.message || '更新角色失敗，請稍後再試');
      } finally {
        this.updating = false;
      }
    },

    /**
     * 刪除角色
     */
    async deleteRole(role) {
      const confirmed = await confirmWarning(
        '刪除角色',
        `確定要刪除角色 "${role.name}" 嗎？`,
        '刪除',
        '取消'
      );
      
      if (!confirmed) {
        return;
      }
      
      try {
        const response = await this.$axios.delete(`/api/admin/roles/${role.id}`);
        
        if (response.data.success) {
          if (this.selectedRoleId === role.id) {
            this.selectedRoleId = null;
            this.rolePermissions = {};
          }
          
          this.loadRoles();
          toast('角色已刪除', 'success');
        }
      } catch (err) {
        console.error('刪除角色失敗:', err);
        showError('刪除失敗', err.response?.data?.error?.message || '刪除角色失敗，請稍後再試');
      }
    },

    /**
     * 檢查是否為系統角色（客戶端角色）
     */
    isSystemRole(name) {
      return ['internal', 'partner', 'test'].includes(name.toLowerCase());
    },

    /**
     * 檢查是否為系統角色（後台角色）
     */
    isAdminSystemRole(name) {
      return ['super_admin'].includes(name.toLowerCase());
    },

    /**
     * 載入後台角色列表
     */
    async loadAdminRoles() {
      this.loadingRoles = true;
      
      try {
        const response = await this.$axios.get('/api/admin/admin-roles');
        
        if (response.data.success) {
          this.adminRoles = response.data.data;
        }
      } catch (err) {
        console.error('載入後台角色列表失敗:', err);
        showError('載入失敗', '載入後台角色列表失敗');
      } finally {
        this.loadingRoles = false;
      }
    },

    /**
     * 載入後台權限列表
     */
    async loadAdminPermissions() {
      try {
        const response = await this.$axios.get('/api/admin/admin-permissions');
        
        if (response.data.success) {
          this.adminPermissions = response.data.data;
        }
      } catch (err) {
        console.error('載入後台權限列表失敗:', err);
      }
    },

    /**
     * 選擇後台角色
     */
    async selectAdminRole(roleId) {
      this.selectedAdminRoleId = roleId;
      await this.loadAdminRolePermissions();
    },

    /**
     * 載入後台角色權限
     */
    async loadAdminRolePermissions() {
      if (!this.selectedAdminRoleId) return;
      
      try {
        const response = await this.$axios.get(`/api/admin/admin-roles/${this.selectedAdminRoleId}/permissions`);
        
        if (response.data.success) {
          // 初始化所有權限為 false
          this.adminRolePermissions = {};
          this.adminPermissions.forEach(perm => {
            this.adminRolePermissions[perm.id] = false;
          });
          
          // 設定已授權的權限
          response.data.data.forEach(permission => {
            this.adminRolePermissions[permission.id] = true;
          });
        }
      } catch (err) {
        console.error('載入後台角色權限失敗:', err);
        showError('載入失敗', '載入後台角色權限失敗');
      }
    },

    /**
     * 儲存後台角色權限
     */
    async saveAdminRolePermissions() {
      if (!this.selectedAdminRoleId) return;
      
      // 超級管理員的權限不可修改
      if (this.isAdminRoleSuperAdmin) {
        showError('無法修改', '超級管理員的權限不可修改');
        return;
      }
      
      this.saving = true;
      
      try {
        const permissions = Object.keys(this.adminRolePermissions)
          .filter(permId => this.adminRolePermissions[permId])
          .map(permId => parseInt(permId));
        
        const response = await this.$axios.post(
          `/api/admin/admin-roles/${this.selectedAdminRoleId}/permissions`,
          { permission_ids: permissions }
        );
        
        if (response.data.success) {
          toast('後台角色權限已更新', 'success');
          this.loadAdminRoles(); // 重新載入以更新權限數量
        }
      } catch (err) {
        console.error('儲存後台角色權限失敗:', err);
        showError('儲存失敗', err.response?.data?.message || '儲存後台角色權限失敗');
      } finally {
        this.saving = false;
      }
    },

    /**
     * 顯示編輯後台角色 Modal
     */
    showEditAdminRoleModal(role) {
      this.editingAdminRole = {
        id: role.id,
        name: role.name,
        display_name: role.display_name,
        description: role.description || '',
      };
      this.showEditAdminRoleModalFlag = true;
    },

    /**
     * 關閉編輯後台角色 Modal
     */
    closeEditAdminRoleModal() {
      this.showEditAdminRoleModalFlag = false;
      this.editingAdminRole = {
        id: null,
        name: '',
        display_name: '',
        description: '',
      };
    },

    /**
     * 更新後台角色
     */
    async updateAdminRole() {
      this.updatingAdmin = true;
      
      try {
        const response = await this.$axios.put(`/api/admin/admin-roles/${this.editingAdminRole.id}`, {
          name: this.editingAdminRole.name,
          display_name: this.editingAdminRole.display_name,
          description: this.editingAdminRole.description,
        });
        
        if (response.data.success) {
          this.closeEditAdminRoleModal();
          this.loadAdminRoles();
          toast('後台角色更新成功', 'success');
        }
      } catch (err) {
        console.error('更新後台角色失敗:', err);
        showError('更新失敗', err.response?.data?.message || '更新後台角色失敗，請稍後再試');
      } finally {
        this.updatingAdmin = false;
      }
    },
  },
};
</script>

<style scoped>
.page-header {
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

/* 標籤頁 */
.tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  border-bottom: 2px solid #e5e7eb;
}

.tab-btn {
  padding: 12px 24px;
  border: none;
  background: transparent;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: #111827;
}

.tab-btn.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.tab-content {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* 選擇器 */
.selector-section {
  margin-bottom: 30px;
}

.selector-section label {
  display: block;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.selector {
  width: 100%;
  max-width: 500px;
  padding: 10px 15px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background-color: white;
}

.selector:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* 權限矩陣 */
.permission-matrix {
  margin-top: 20px;
}

.matrix-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.matrix-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.matrix-actions {
  display: flex;
  gap: 10px;
}

/* 搜尋框 */
.search-box {
  margin-bottom: 20px;
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

/* Functions 列表 */
.functions-list {
  max-height: 600px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
}

.function-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px;
  border-bottom: 1px solid #f3f4f6;
  transition: background-color 0.2s;
}

.function-item:last-child {
  border-bottom: none;
}

.function-item:hover {
  background-color: #f9fafb;
}

.function-info {
  flex: 1;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
}

.checkbox {
  margin-top: 4px;
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.function-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.function-details strong {
  font-size: 14px;
  color: #111827;
}

.identifier {
  font-family: 'Courier New', monospace;
  font-size: 12px;
  background-color: #f3f4f6;
  padding: 2px 6px;
  border-radius: 4px;
  color: #1f2937;
  display: inline-block;
}

.description {
  font-size: 12px;
  color: #6b7280;
}

.function-meta {
  margin-left: 15px;
}

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

/* 空狀態 */
.empty-selection {
  text-align: center;
  padding: 60px 20px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 15px;
  color: #9ca3af;
}

.empty-selection p,
.empty-state p {
  color: #6b7280;
  margin: 0;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
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

/* 角色區塊 */
.roles-section {
  margin-bottom: 30px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 15px;
  margin-bottom: 30px;
}

.role-card {
  padding: 20px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.role-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.role-card.selected {
  border-color: #3b82f6;
  background-color: #eff6ff;
}

.role-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.role-header h4 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  flex: 1;
}

.role-actions {
  display: flex;
  gap: 6px;
  align-items: center;
}

.role-description {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 10px 0;
}

.role-name-hint {
  font-size: 11px;
  color: #9ca3af;
  margin: 0 0 15px 0;
  font-family: 'Courier New', monospace;
}

.form-hint {
  display: block;
  margin-top: 5px;
  font-size: 12px;
  color: #6b7280;
}

.section-description {
  font-size: 13px;
  color: #6b7280;
  margin: 5px 0 0 0;
}

.system-badge {
  display: inline-block;
  padding: 3px 8px;
  background-color: #fef3c7;
  color: #92400e;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

.permissions-list {
  display: flex;
  flex-direction: column;
  gap: 0;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
}

.permission-item {
  padding: 15px;
  border-bottom: 1px solid #f3f4f6;
  transition: background-color 0.2s;
}

.permission-item:last-child {
  border-bottom: none;
}

.permission-item:hover {
  background-color: #f9fafb;
}

.permission-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.permission-details strong {
  font-size: 14px;
  color: #111827;
}

.alert {
  padding: 12px 15px;
  border-radius: 6px;
  margin-top: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
}

.alert-info {
  background-color: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
}

.alert-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.role-stats {
  display: flex;
  gap: 15px;
}

.stat {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #6b7280;
}

.stat-icon {
  width: 16px;
  height: 16px;
}

.role-permissions-section {
  margin-top: 30px;
  padding-top: 30px;
  border-top: 2px solid #e5e7eb;
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

.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
}

.btn-icon-action {
  padding: 6px;
  border: none;
  background: transparent;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon-action .icon {
  width: 16px;
  height: 16px;
}

.btn-icon-action.btn-edit {
  color: #3b82f6;
}

.btn-icon-action.btn-edit:hover {
  background-color: #eff6ff;
}

.btn-icon-action.btn-danger {
  color: #ef4444;
}

.btn-icon-action.btn-danger:hover {
  background-color: #fef2f2;
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

.form-input:disabled {
  background-color: #f3f4f6;
  color: #9ca3af;
  cursor: not-allowed;
}

.btn-icon-action-old {
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

.btn-icon-action.btn-danger {
  color: #ef4444;
}

.btn-icon-action.btn-danger:hover {
  background-color: #fee2e2;
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

textarea.form-input {
  resize: vertical;
  font-family: inherit;
}
</style>

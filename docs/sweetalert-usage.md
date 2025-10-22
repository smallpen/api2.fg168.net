# SweetAlert2 使用指南

本專案已將所有原生的 `alert()` 和 `confirm()` 替換為 SweetAlert2，提供更美觀的提示視窗。

## 可用的函數

### 1. 確認對話框 (confirm)
用於一般的確認操作。

```javascript
import { confirm } from '../utils/sweetalert';

const confirmed = await confirm('確認操作', '您確定要執行此操作嗎？', '確定', '取消');
if (confirmed) {
  // 使用者點擊確定
}
```

### 2. 警告確認對話框 (confirmWarning)
用於需要警告的確認操作（如刪除）。

```javascript
import { confirmWarning } from '../utils/sweetalert';

const confirmed = await confirmWarning('刪除項目', '確定要刪除嗎？此操作無法復原。', '刪除', '取消');
if (confirmed) {
  // 執行刪除
}
```

### 3. 成功訊息 (success)
顯示操作成功的訊息。

```javascript
import { success } from '../utils/sweetalert';

await success('操作成功', '資料已成功儲存');
```

### 4. 錯誤訊息 (error)
顯示錯誤訊息。

```javascript
import { error } from '../utils/sweetalert';

error('操作失敗', '無法連接到伺服器，請稍後再試');
```

### 5. 資訊訊息 (info)
顯示一般資訊。

```javascript
import { info } from '../utils/sweetalert';

info('提示', '此功能尚未開放');
```

### 6. Toast 通知 (toast)
右上角的小型通知，3 秒後自動消失。

```javascript
import { toast } from '../utils/sweetalert';

toast('已複製到剪貼簿', 'success');
toast('操作失敗', 'error');
toast('請注意', 'warning');
toast('資訊提示', 'info');
```

### 7. 選擇對話框 (select)
提供多個選項供使用者選擇。

```javascript
import { select } from '../utils/sweetalert';

const choice = await select(
  '選擇操作',
  '請選擇要執行的操作',
  [
    { value: 'option1', text: '選項 1' },
    { value: 'option2', text: '選項 2' }
  ]
);

if (choice) {
  // choice 包含選擇的值
}
```

## 已更新的檔案

所有使用原生 `alert()` 和 `confirm()` 的檔案都已更新：

- `resources/js/pages/FunctionList.vue`
- `resources/js/pages/ClientManager.vue`
- `resources/js/pages/FunctionEditor.vue`
- `resources/js/components/ParameterBuilder.vue`
- `resources/js/components/ResponseMapper.vue`
- `resources/js/components/PermissionManager.vue`
- `resources/js/components/StoredProcedureSelector.vue`

## 自訂樣式

SweetAlert2 的樣式可以在 `resources/js/utils/sweetalert.js` 中自訂。

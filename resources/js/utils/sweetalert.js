/**
 * SweetAlert2 輔助工具
 * 提供統一的提示視窗介面
 */
import Swal from 'sweetalert2';

/**
 * 確認對話框
 * @param {string} title - 標題
 * @param {string} text - 內容文字
 * @param {string} confirmButtonText - 確認按鈕文字
 * @param {string} cancelButtonText - 取消按鈕文字
 * @returns {Promise<boolean>} 使用者是否確認
 */
export const confirm = async (title, text = '', confirmButtonText = '確定', cancelButtonText = '取消') => {
    const result = await Swal.fire({
        title,
        text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
    });

    return result.isConfirmed;
};

/**
 * 警告確認對話框
 * @param {string} title - 標題
 * @param {string} text - 內容文字
 * @param {string} confirmButtonText - 確認按鈕文字
 * @param {string} cancelButtonText - 取消按鈕文字
 * @returns {Promise<boolean>} 使用者是否確認
 */
export const confirmWarning = async (title, text = '', confirmButtonText = '確定', cancelButtonText = '取消') => {
    const result = await Swal.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
    });

    return result.isConfirmed;
};

/**
 * 成功訊息
 * @param {string} title - 標題
 * @param {string} text - 內容文字
 */
export const success = (title, text = '') => {
    return Swal.fire({
        title,
        text,
        icon: 'success',
        confirmButtonText: '確定',
        confirmButtonColor: '#3b82f6',
    });
};

/**
 * 錯誤訊息
 * @param {string} title - 標題
 * @param {string} text - 內容文字
 */
export const error = (title, text = '') => {
    return Swal.fire({
        title,
        text,
        icon: 'error',
        confirmButtonText: '確定',
        confirmButtonColor: '#3b82f6',
    });
};

/**
 * 資訊訊息
 * @param {string} title - 標題
 * @param {string} text - 內容文字
 */
export const info = (title, text = '') => {
    return Swal.fire({
        title,
        text,
        icon: 'info',
        confirmButtonText: '確定',
        confirmButtonColor: '#3b82f6',
    });
};

/**
 * Toast 通知（右上角小提示）
 * @param {string} title - 標題
 * @param {string} icon - 圖示類型 (success, error, warning, info)
 */
export const toast = (title, icon = 'success') => {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });

    return Toast.fire({
        icon,
        title,
    });
};

/**
 * 選擇對話框（用於多選項）
 * @param {string} title - 標題
 * @param {string} text - 內容文字
 * @param {Array} options - 選項陣列 [{value, text}]
 * @returns {Promise<string|null>} 選擇的值
 */
export const select = async (title, text, options) => {
    const inputOptions = {};
    options.forEach(opt => {
        inputOptions[opt.value] = opt.text;
    });

    const result = await Swal.fire({
        title,
        text,
        input: 'select',
        inputOptions,
        showCancelButton: true,
        confirmButtonText: '確定',
        cancelButtonText: '取消',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
    });

    return result.isConfirmed ? result.value : null;
};

export default {
    confirm,
    confirmWarning,
    success,
    error,
    info,
    toast,
    select,
};

import { ref } from 'vue';

export type ToastType = 'success' | 'error';

export type AppToast = {
    id: number;
    type: ToastType;
    title: string;
    message: string;
};

const toasts = ref<AppToast[]>([]);
let nextId = 0;

function removeToast(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function addToast(type: ToastType, title: string, message: string): void {
    const id = ++nextId;

    toasts.value.push({ id, type, title, message });

    window.setTimeout(() => {
        removeToast(id);
    }, 5000);
}

export function useAppToastStore() {
    return {
        toasts,
        removeToast,
    };
}

export function useAppToast() {
    return {
        showMessage: (title: string, message: string) => {
            addToast('success', title, message);
        },
        showError: (title: string, message: string) => {
            addToast('error', title, message);
        },
    };
}

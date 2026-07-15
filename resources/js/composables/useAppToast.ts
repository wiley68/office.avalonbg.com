import { ref } from 'vue';

export type ToastType = 'success' | 'error';

export type AppToast = {
    id: number;
    type: ToastType;
    title: string;
    message: string;
};

type ToastTimerState = {
    timeoutId: number | null;
    dismissAt: number;
    paused: boolean;
};

const AUTO_DISMISS_MS = 5000;

const toasts = ref<AppToast[]>([]);
const toastTimers = new Map<number, ToastTimerState>();
let nextId = 0;

function clearToastTimer(id: number): void {
    const state = toastTimers.get(id);

    if (state?.timeoutId !== null && state?.timeoutId !== undefined) {
        window.clearTimeout(state.timeoutId);
    }

    toastTimers.delete(id);
}

function scheduleDismiss(id: number, delayMs: number): void {
    const existing = toastTimers.get(id);

    if (existing?.timeoutId !== null && existing?.timeoutId !== undefined) {
        window.clearTimeout(existing.timeoutId);
    }

    const timeoutId = window.setTimeout(() => {
        removeToast(id);
    }, delayMs);

    toastTimers.set(id, {
        timeoutId,
        dismissAt: Date.now() + delayMs,
        paused: false,
    });
}

function removeToast(id: number): void {
    clearToastTimer(id);
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function pauseToast(id: number): void {
    const state = toastTimers.get(id);

    if (state === undefined || state.paused) {
        return;
    }

    if (state.timeoutId !== null) {
        window.clearTimeout(state.timeoutId);
    }

    const remainingMs = Math.max(0, state.dismissAt - Date.now());

    toastTimers.set(id, {
        timeoutId: null,
        dismissAt: Date.now() + remainingMs,
        paused: true,
    });
}

function resumeToast(id: number): void {
    const state = toastTimers.get(id);

    if (state === undefined || !state.paused) {
        return;
    }

    const remainingMs = Math.max(0, state.dismissAt - Date.now());

    if (remainingMs === 0) {
        removeToast(id);

        return;
    }

    scheduleDismiss(id, remainingMs);
}

function addToast(type: ToastType, title: string, message: string): void {
    const id = ++nextId;

    toasts.value.push({ id, type, title, message });
    scheduleDismiss(id, AUTO_DISMISS_MS);
}

export function useAppToastStore() {
    return {
        toasts,
        removeToast,
        pauseToast,
        resumeToast,
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

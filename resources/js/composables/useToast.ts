import { ref } from 'vue';

export type ToastLevel = 'success' | 'warning' | 'error';
export interface Toast {
    id: number;
    message: string;
    level: ToastLevel;
}

const toasts = ref<Toast[]>([]);
let seq = 0;

export function useToast() {
    const dismiss = (id: number) => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    };
    const push = (message: string, level: ToastLevel = 'success', timeout = 4500) => {
        const id = ++seq;
        toasts.value.push({ id, message, level });
        window.setTimeout(() => dismiss(id), timeout);
    };
    return {
        toasts,
        push,
        dismiss,
        success: (m: string) => push(m, 'success'),
        warning: (m: string) => push(m, 'warning'),
        error: (m: string) => push(m, 'error', 7000),
    };
}

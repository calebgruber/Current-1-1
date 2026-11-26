import { ref, watch, type Ref } from 'vue';

export function useDebounced<T>(source: Ref<T>, delay = 250): Ref<T> {
    const out = ref(source.value) as Ref<T>;
    let timer: number | undefined;
    watch(source, (value) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => (out.value = value), delay);
    });
    return out;
}

export function debounce<A extends unknown[]>(fn: (...args: A) => void, delay = 250) {
    let timer: number | undefined;
    return (...args: A) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), delay);
    };
}

import { onBeforeUnmount, onMounted, ref } from 'vue';
import { isTypingTarget } from './useHotkeys';

/**
 * Captures input from USB / Bluetooth barcode scanners operating as keyboards.
 * Scanners "type" very quickly and finish with Enter; we buffer keystrokes that
 * arrive faster than `maxInterval` ms and emit the buffer on Enter/Tab.
 * When a scan input field has focus, the field handles it instead (pass `ignoreInputs: false` to capture everywhere).
 */
export function useScanner(onScan: (code: string) => void, options: { minLength?: number; maxInterval?: number; ignoreInputs?: boolean } = {}) {
    const minLength = options.minLength ?? 3;
    const maxInterval = options.maxInterval ?? 45;
    const buffer = ref('');
    let last = 0;

    const onKeydown = (e: KeyboardEvent) => {
        if ((options.ignoreInputs ?? true) && isTypingTarget(e.target)) return;
        const now = performance.now();
        if (now - last > maxInterval * 4) buffer.value = '';
        last = now;

        if (e.key === 'Enter' || e.key === 'Tab') {
            const code = buffer.value.trim();
            buffer.value = '';
            if (code.length >= minLength) {
                e.preventDefault();
                onScan(code);
            }
            return;
        }
        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            buffer.value += e.key;
        }
    };

    onMounted(() => window.addEventListener('keydown', onKeydown, true));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown, true));

    return { buffer };
}

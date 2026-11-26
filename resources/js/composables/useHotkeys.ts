import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Keyboard shortcuts. Combos: "mod+k" (⌘ on macOS, Ctrl elsewhere), "shift+?",
 * "escape", or two-key sequences such as "g i" (press g then i).
 * Shortcuts are ignored while typing in inputs unless `allowInInputs` is set.
 */
export type HotkeyHandler = (event: KeyboardEvent) => void;

export const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

export function isTypingTarget(target: EventTarget | null): boolean {
    const el = target as HTMLElement | null;
    if (!el) return false;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
}

function matches(combo: string, e: KeyboardEvent): boolean {
    const parts = combo.toLowerCase().split('+');
    const key = parts.pop()!;
    const mod = parts.includes('mod');
    const wantShift = parts.includes('shift');
    const wantAlt = parts.includes('alt');
    const modPressed = isMac ? e.metaKey : e.ctrlKey;
    if (mod !== modPressed) return false;
    if (wantAlt !== e.altKey) return false;
    if (wantShift && !e.shiftKey) return false;
    const pressed = e.key.toLowerCase();
    return pressed === key || (key === 'slash' && pressed === '/') || (key === 'esc' && pressed === 'escape');
}

export function useHotkeys(bindings: Record<string, HotkeyHandler>, options: { allowInInputs?: string[] } = {}) {
    let pending: string | null = null;
    let timer: number | undefined;

    const onKeydown = (e: KeyboardEvent) => {
        const typing = isTypingTarget(e.target);
        for (const [combo, handler] of Object.entries(bindings)) {
            if (typing && !(options.allowInInputs ?? []).includes(combo) && !combo.includes('mod+') && combo !== 'escape') continue;
            if (combo.includes(' ')) {
                const [first, second] = combo.split(' ');
                if (pending === first && e.key.toLowerCase() === second && !e.metaKey && !e.ctrlKey) {
                    e.preventDefault();
                    pending = null;
                    handler(e);
                    return;
                }
                continue;
            }
            if (matches(combo, e)) {
                e.preventDefault();
                handler(e);
                return;
            }
        }
        if (!typing && !e.metaKey && !e.ctrlKey && !e.altKey && e.key.length === 1) {
            pending = e.key.toLowerCase();
            window.clearTimeout(timer);
            timer = window.setTimeout(() => (pending = null), 900);
        }
    };

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
}

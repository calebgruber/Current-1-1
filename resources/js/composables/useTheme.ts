import { ref, watch } from 'vue';

export type ThemePreference = 'light' | 'dark' | 'system';

const KEY = 'stagetrack.theme';
const preference = ref<ThemePreference>((typeof localStorage !== 'undefined' && (localStorage.getItem(KEY) as ThemePreference)) || 'system');
const isDark = ref(false);

function apply() {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    isDark.value = preference.value === 'dark' || (preference.value === 'system' && media.matches);
    document.documentElement.classList.toggle('dark', isDark.value);
}

if (typeof window !== 'undefined') {
    apply();
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', apply);
    watch(preference, (value) => {
        localStorage.setItem(KEY, value);
        apply();
    });
}

export function useTheme() {
    const cycle = () => {
        preference.value = preference.value === 'light' ? 'dark' : preference.value === 'dark' ? 'system' : 'light';
    };
    const toggle = () => {
        preference.value = isDark.value ? 'light' : 'dark';
    };
    return { preference, isDark, cycle, toggle };
}

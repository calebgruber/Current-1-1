<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, Keyboard, LogOut, Menu, PanelLeftClose, PanelLeftOpen, Search, ShieldCheck, UserRound, X } from 'lucide-vue-next';
import CommandPalette from '@/Components/app/CommandPalette.vue';
import ShortcutsDialog from '@/Components/app/ShortcutsDialog.vue';
import ThemeToggle from '@/Components/app/ThemeToggle.vue';
import Toaster from '@/Components/app/Toaster.vue';
import Dropdown from '@/Components/ui/Dropdown.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import { navigation, type NavItem } from '@/lib/navigation';
import { usePermissions } from '@/composables/usePermissions';
import { useHotkeys, isMac } from '@/composables/useHotkeys';
import { useTheme } from '@/composables/useTheme';
import { useScanner } from '@/composables/useScanner';
import { http } from '@/lib/http';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const { can } = usePermissions();
const { toggle } = useTheme();

const paletteOpen = ref(false);
const shortcutsOpen = ref(false);
const mobileOpen = ref(false);
const collapsed = ref(localStorage.getItem('stagetrack.sidebar') === 'collapsed');

const user = computed(() => page.props.auth.user);
const initials = computed(() => (user.value?.name ?? '?').split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase());

const visible = (item: NavItem) => route().has(item.route) && (!item.permissions || item.permissions.some(can));
const sections = computed(() => navigation.map((s) => ({ ...s, items: s.items.filter(visible) })).filter((s) => s.items.length));
const isActive = (item: NavItem) => (item.match ? route().current(`${item.match}.*`) || route().current(item.match) : route().current(item.route));

const toggleSidebar = () => {
    collapsed.value = !collapsed.value;
    localStorage.setItem('stagetrack.sidebar', collapsed.value ? 'collapsed' : 'expanded');
};

const shortcuts: Record<string, () => void> = {
    'mod+k': () => (paletteOpen.value = !paletteOpen.value),
    '/': () => (paletteOpen.value = true),
    'shift+?': () => (shortcutsOpen.value = true),
    'mod+j': toggle,
    'mod+b': toggleSidebar,
};
for (const item of navigation.flatMap((s) => s.items)) {
    if (item.shortcut) shortcuts[item.shortcut] = () => visible(item) && router.visit(route(item.route));
}
useHotkeys(shortcuts);

// A barcode scanned anywhere outside an input jumps to the matching record.
useScanner(async (code) => {
    if (!route().has('search.query')) return;
    const { data } = await http.get(route('search.query'), { params: { q: code, limit: 5 } });
    const exact = (data.results as { url: string; exact: boolean }[]).filter((r) => r.exact);
    if (exact.length === 1) router.visit(exact[0].url);
    else router.visit(route('search.index', { q: code }));
});

router.on('navigate', () => (mobileOpen.value = false));
</script>

<template>
    <div class="flex min-h-screen">
        <a href="#main" class="sr-only z-50 rounded bg-accent px-3 py-2 text-white focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Skip to content</a>

        <!-- Mobile overlay -->
        <div v-if="mobileOpen" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="mobileOpen = false" />

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 flex flex-col border-r border-line bg-surface transition-[width,transform] duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0',
                collapsed ? 'lg:w-16' : 'lg:w-60',
                mobileOpen ? 'w-64 translate-x-0' : 'w-64 -translate-x-full',
            ]"
            aria-label="Main navigation"
        >
            <div class="flex h-14 items-center gap-2 px-4">
                <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2 font-semibold">
                    <span class="grid size-7 shrink-0 place-items-center rounded-md bg-accent text-xs font-bold text-white dark:text-zinc-950">ST</span>
                    <span v-if="!collapsed" class="truncate">{{ page.props.app.organization || page.props.app.name }}</span>
                </Link>
                <button type="button" class="ml-auto rounded p-1 text-muted hover:bg-hover lg:hidden" aria-label="Close navigation" @click="mobileOpen = false"><X class="size-4" /></button>
            </div>

            <button
                type="button"
                class="mx-3 mb-2 flex items-center gap-2 rounded-md border border-line bg-canvas px-2.5 py-1.5 text-sm text-faint hover:border-line-strong"
                @click="paletteOpen = true"
            >
                <Search class="size-4 shrink-0" />
                <template v-if="!collapsed">
                    <span class="flex-1 text-left">Search…</span>
                    <kbd>{{ isMac ? '⌘' : 'Ctrl' }}</kbd><kbd>K</kbd>
                </template>
            </button>

            <nav class="flex-1 overflow-y-auto px-3 pb-4">
                <div v-for="section in sections" :key="section.label" class="mt-4 first:mt-1">
                    <div v-if="!collapsed" class="mb-1 px-2 text-[11px] font-medium tracking-wide text-faint uppercase">{{ section.label }}</div>
                    <ul class="space-y-0.5">
                        <li v-for="item in section.items" :key="item.route">
                            <Link
                                :href="route(item.route)"
                                :title="collapsed ? item.label : undefined"
                                :aria-current="isActive(item) ? 'page' : undefined"
                                :class="[
                                    'flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition',
                                    isActive(item) ? 'bg-accent-soft font-medium text-accent-ink' : 'text-muted hover:bg-hover hover:text-ink',
                                    collapsed ? 'justify-center' : '',
                                ]"
                            >
                                <component :is="item.icon" class="size-4 shrink-0" aria-hidden="true" />
                                <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="hidden border-t border-line p-2 lg:block">
                <button type="button" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs text-muted hover:bg-hover" @click="toggleSidebar">
                    <PanelLeftOpen v-if="collapsed" class="size-4" /><PanelLeftClose v-else class="size-4" />
                    <span v-if="!collapsed">Collapse</span>
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-line bg-canvas/80 px-4 backdrop-blur lg:px-8">
                <button type="button" class="rounded p-1.5 text-muted hover:bg-hover lg:hidden" aria-label="Open navigation" @click="mobileOpen = true"><Menu class="size-5" /></button>
                <div class="flex-1"><slot name="header" /></div>
                <button type="button" class="hidden rounded-md p-1.5 text-muted hover:bg-hover hover:text-ink sm:block" aria-label="Keyboard shortcuts" title="Keyboard shortcuts (?)" @click="shortcutsOpen = true"><Keyboard class="size-4" /></button>
                <ThemeToggle />
                <Dropdown v-if="user">
                    <template #trigger>
                        <button type="button" class="flex items-center gap-1.5 rounded-md p-1 hover:bg-hover" aria-label="Account menu">
                            <span class="grid size-7 place-items-center rounded-full bg-subtle text-xs font-semibold">{{ initials }}</span>
                            <ChevronDown class="size-3.5 text-muted" />
                        </button>
                    </template>
                    <div class="border-b border-line px-2 pt-1 pb-2">
                        <div class="truncate text-sm font-medium">{{ user.name }}</div>
                        <div class="truncate text-xs text-muted">{{ user.email }}</div>
                    </div>
                    <div class="pt-1">
                        <DropdownItem v-if="route().has('account.profile')" :href="route('account.profile')"><UserRound class="size-4" /> Profile</DropdownItem>
                        <DropdownItem v-if="route().has('account.security')" :href="route('account.security')"><ShieldCheck class="size-4" /> Security &amp; passkeys</DropdownItem>
                        <DropdownItem v-if="route().has('logout')" :href="route('logout')" method="post"><LogOut class="size-4" /> Sign out</DropdownItem>
                    </div>
                </Dropdown>
            </header>

            <main id="main" class="mx-auto w-full max-w-[1600px] flex-1 px-4 py-6 lg:px-8">
                <slot />
            </main>
        </div>

        <CommandPalette v-model:open="paletteOpen" />
        <ShortcutsDialog v-model:open="shortcutsOpen" />
        <Toaster />
    </div>
</template>

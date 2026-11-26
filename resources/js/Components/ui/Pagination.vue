<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginated } from '@/types';

defineProps<{ meta: Pick<Paginated<unknown>, 'links' | 'from' | 'to' | 'total' | 'last_page'> }>();

const label = (raw: string) => raw.replace('&laquo;', '‹').replace('&raquo;', '›').replace(/ ?(Previous|Next) ?/, '');
</script>

<template>
    <nav v-if="meta.last_page > 1 || meta.total" class="flex flex-wrap items-center justify-between gap-3 px-1 py-3 text-xs text-muted" aria-label="Pagination">
        <span class="st-num">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total }}</span>
        <div v-if="meta.last_page > 1" class="flex gap-1">
            <template v-for="(link, i) in meta.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    :aria-current="link.active ? 'page' : undefined"
                    :class="['st-num min-w-7 rounded-md px-2 py-1 text-center', link.active ? 'bg-accent text-white dark:text-zinc-950' : 'hover:bg-hover']"
                >{{ label(link.label) }}</Link>
                <span v-else class="min-w-7 px-2 py-1 text-center text-faint">{{ label(link.label) }}</span>
            </template>
        </div>
    </nav>
</template>

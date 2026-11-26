<script setup lang="ts">
/** Chrome-less layout for warehouse wall displays, kiosks and tablets. */
import Toaster from '@/Components/app/Toaster.vue';
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-50">
        <slot />
        <Toaster />
    </div>
</template>

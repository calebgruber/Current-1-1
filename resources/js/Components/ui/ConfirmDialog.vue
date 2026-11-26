<script setup lang="ts">
import Modal from './Modal.vue';
import Button from './Button.vue';

const open = defineModel<boolean>('open', { default: false });
withDefaults(defineProps<{ title?: string; message?: string; confirmLabel?: string; danger?: boolean; loading?: boolean }>(), {
    title: 'Are you sure?',
    confirmLabel: 'Confirm',
});
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <Modal v-model:open="open" :title="title" size="sm">
        <p class="text-sm text-muted"><slot>{{ message }}</slot></p>
        <template #footer>
            <Button @click="open = false">Cancel</Button>
            <Button :variant="danger ? 'danger' : 'primary'" :loading="loading" @click="emit('confirm')">{{ confirmLabel }}</Button>
        </template>
    </Modal>
</template>

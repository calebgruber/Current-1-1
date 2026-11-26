<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';

defineProps<{ expired: boolean; policy: { min_length: number; mixed: boolean } }>();
const form = useForm({ current_password: '', password: '', password_confirmation: '' });
const submit = () => form.put(route('account.password.update'), { onFinish: () => form.reset() });
</script>
<template>
    <Head title="Change password" />
    <h1 class="mb-6 text-2xl font-semibold">Change password</h1>
    <Card>
        <p v-if="expired" class="mb-4 text-sm text-red-600">Your password has expired.</p>
        <p class="mb-4 text-sm text-muted">Use at least {{ policy.min_length }} characters<span v-if="policy.mixed">, including upper and lower case letters and numbers</span>.</p>
        <form class="max-w-md space-y-4" @submit.prevent="submit">
            <FormField label="Current password" for="current-password" :error="form.errors.current_password" required><TextInput id="current-password" v-model="form.current_password" type="password" autocomplete="current-password" required /></FormField>
            <FormField label="New password" for="new-password" :error="form.errors.password" required><TextInput id="new-password" v-model="form.password" type="password" autocomplete="new-password" required /></FormField>
            <FormField label="Confirm new password" for="new-password-confirmation" required><TextInput id="new-password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required /></FormField>
            <div class="flex gap-2"><Button type="submit" variant="primary" :loading="form.processing">Update password</Button><Button :href="route('account.security')">Cancel</Button></div>
        </form>
    </Card>
</template>

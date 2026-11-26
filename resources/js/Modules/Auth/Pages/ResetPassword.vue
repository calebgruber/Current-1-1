<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';

defineOptions({ layout: AuthLayout });
const props = defineProps<{ token: string; email: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>
<template>
    <Head title="Choose a new password" />
    <h1 class="text-2xl font-semibold">Choose a new password</h1>
    <form class="mt-6 space-y-4" @submit.prevent="submit">
        <FormField label="Email" for="email" :error="form.errors.email" required><TextInput id="email" v-model="form.email" type="email" autocomplete="email" required /></FormField>
        <FormField label="New password" for="password" :error="form.errors.password" required><TextInput id="password" v-model="form.password" type="password" autocomplete="new-password" required /></FormField>
        <FormField label="Confirm password" for="password_confirmation" required><TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required /></FormField>
        <Button type="submit" variant="primary" block :loading="form.processing">Reset password</Button>
    </form>
</template>

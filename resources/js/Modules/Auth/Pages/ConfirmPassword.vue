<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';

defineOptions({ layout: AuthLayout });
const form = useForm({ password: '' });
const submit = () => form.post(route('password.confirm.store'), { onFinish: () => form.reset('password') });
</script>
<template>
    <Head title="Confirm password" />
    <h1 class="text-2xl font-semibold">Confirm your password</h1>
    <p class="mt-2 text-sm text-muted">Please confirm your password before continuing.</p>
    <form class="mt-6 space-y-4" @submit.prevent="submit">
        <FormField label="Password" for="password" :error="form.errors.password" required><TextInput id="password" v-model="form.password" type="password" autocomplete="current-password" required autofocus /></FormField>
        <Button type="submit" variant="primary" block :loading="form.processing">Confirm</Button>
    </form>
</template>

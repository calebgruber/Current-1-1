<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';

defineOptions({ layout: AuthLayout });
defineProps<{ status?: string }>();
const form = useForm({ email: '' });
</script>
<template>
    <Head title="Reset password" />
    <h1 class="text-2xl font-semibold">Forgot your password?</h1>
    <p class="mt-2 text-sm text-muted">Enter your email address and we'll send a reset link if an account exists.</p>
    <p v-if="status" class="mt-4 text-sm text-emerald-600" role="status">{{ status }}</p>
    <form class="mt-6 space-y-4" @submit.prevent="form.post(route('password.email'))">
        <FormField label="Email" for="email" :error="form.errors.email" required><TextInput id="email" v-model="form.email" type="email" autocomplete="email" required autofocus /></FormField>
        <Button type="submit" variant="primary" block :loading="form.processing">Send reset link</Button>
    </form>
    <Link :href="route('login')" class="st-link mt-4 text-sm">Back to sign in</Link>
</template>

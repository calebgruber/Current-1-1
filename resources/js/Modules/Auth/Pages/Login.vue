<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';

defineOptions({ layout: AuthLayout });
defineProps<{ passkeysEnabled: boolean; canResetPassword: boolean; status?: string }>();
const form = useForm({ login: '', password: '', remember: false });
const { verify, isLoading, isSupported, error } = usePasskeyVerify({
    remember: () => form.remember,
    onSuccess: (response) => router.visit(response.redirect || '/'),
});
const submit = () => form.post(route('login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Sign in" />
    <h1 class="text-2xl font-semibold">Sign in</h1>
    <p v-if="status" class="mt-4 text-sm text-emerald-600" role="status">{{ status }}</p>
    <form class="mt-6 space-y-4" @submit.prevent="submit">
        <FormField label="Email or username" for="login" :error="form.errors.login" required>
            <TextInput id="login" v-model="form.login" autocomplete="username webauthn" required autofocus />
        </FormField>
        <FormField label="Password" for="password" :error="form.errors.password" required>
            <TextInput id="password" v-model="form.password" type="password" autocomplete="current-password" required />
        </FormField>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.remember" type="checkbox" /> Remember me</label>
        <Button type="submit" variant="primary" block :loading="form.processing">Sign in</Button>
    </form>
    <div v-if="passkeysEnabled" class="mt-4">
        <Button block :disabled="!isSupported || isLoading" @click="verify">Sign in with a passkey</Button>
        <p v-if="error" class="mt-2 text-sm text-red-600" role="alert">{{ error }}</p>
        <p v-if="!isSupported" class="mt-2 text-xs text-muted">Passkeys are unavailable in this browser or on this connection.</p>
    </div>
    <Link v-if="canResetPassword" :href="route('password.request')" class="st-link mt-4 text-sm">Forgot your password?</Link>
</template>

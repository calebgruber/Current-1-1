<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/Button.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';

defineOptions({ layout: AuthLayout });
const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
const submit = () => form.post(route('two-factor.verify'), { onFinish: () => form.reset() });
const toggle = () => { recovery.value = !recovery.value; form.reset(); form.clearErrors(); };
</script>

<template>
    <Head title="Two-factor verification" />
    <h1 class="text-2xl font-semibold">Two-factor verification</h1>
    <p class="mt-2 text-sm text-muted">Enter a code from your authenticator app or use a recovery code.</p>
    <form class="mt-6 space-y-4" @submit.prevent="submit">
        <FormField v-if="!recovery" label="Authenticator code" for="code" :error="form.errors.code" required>
            <TextInput id="code" v-model="form.code" inputmode="numeric" autocomplete="one-time-code" required autofocus />
        </FormField>
        <FormField v-else label="Recovery code" for="recovery" :error="form.errors.recovery_code" required>
            <TextInput id="recovery" v-model="form.recovery_code" autocomplete="off" required />
        </FormField>
        <Button type="submit" variant="primary" block :loading="form.processing">Verify</Button>
    </form>
    <button class="mt-4 text-sm text-accent-ink" type="button" @click="toggle">{{ recovery ? 'Use an authenticator code' : 'Use a recovery code' }}</button>
    <div class="mt-3"><Link :href="route('login')" class="text-sm text-accent-ink">Back to sign in</Link></div>
</template>

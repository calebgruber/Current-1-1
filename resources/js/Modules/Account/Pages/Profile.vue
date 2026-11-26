<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';

const props = defineProps<{
    profile: { name: string; username: string | null; email: string; timezone: string | null; theme: string; reduce_motion: boolean; warehouse_sounds: boolean };
    timezones: string[];
}>();
const form = useForm({ ...props.profile });
</script>
<template>
    <Head title="Profile" />
    <h1 class="mb-6 text-2xl font-semibold">Profile</h1>
    <Card>
        <form class="max-w-md space-y-4" @submit.prevent="form.put(route('account.profile.update'))">
            <FormField label="Name" for="name" :error="form.errors.name" required><TextInput id="name" v-model="form.name" required /></FormField>
            <FormField label="Username" for="username" :error="form.errors.username"><TextInput id="username" v-model="form.username" /></FormField>
            <FormField label="Email" for="email" :error="form.errors.email" required><TextInput id="email" v-model="form.email" type="email" required /></FormField>
            <FormField label="Timezone" for="timezone" :error="form.errors.timezone"><select id="timezone" v-model="form.timezone" class="st-input"><option :value="null">Organization default</option><option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option></select></FormField>
            <FormField label="Theme" for="theme" :error="form.errors.theme"><select id="theme" v-model="form.theme" class="st-input"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></FormField>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.reduce_motion" type="checkbox" /> Reduce motion</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.warehouse_sounds" type="checkbox" /> Warehouse sounds</label>
            <Button type="submit" variant="primary" :loading="form.processing">Save profile</Button>
        </form>
    </Card>
</template>

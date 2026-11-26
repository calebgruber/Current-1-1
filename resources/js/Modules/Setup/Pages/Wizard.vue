<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import SetupLayout from '@/Layouts/SetupLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';
import type { SharedProps } from '@/types';

defineOptions({ layout: null });
const props = defineProps<{
    steps: { key: string; label: string }[];
    current: string;
    completed: string[];
    requirements: { key: string; label: string; passed: boolean; required: boolean; detail: string }[];
    requirementsPass: boolean;
    database: { driver: string; host: string; port: string; database: string; username: string };
    application: { name: string; url: string; organization_name: string; timezone: string; currency: string };
    installLog: string | null;
    hasAdministrator: boolean;
    timezones: string[];
    currencies: string[];
}>();
const page = usePage<SharedProps>();
const db = useForm({ ...props.database, password: '' });
const application = useForm({ ...props.application });
const admin = useForm({ name: '', username: '', email: '', password: '', password_confirmation: '' });
const submitAdmin = () => admin.post(route('setup.admin'), { onFinish: () => admin.reset('password', 'password_confirmation') });
</script>
<template>
    <Head title="Setup" />
    <SetupLayout :steps="steps" :current="current">
        <h1 class="mb-5 text-2xl font-semibold">Setup · {{ steps.find((step) => step.key === current)?.label }}</h1>
        <p v-if="page.props.flash?.error" class="mb-4 text-sm text-red-600" role="alert">{{ page.props.flash.error }}</p>
        <p v-if="page.props.flash?.success" class="mb-4 text-sm text-emerald-600" role="status">{{ page.props.flash.success }}</p>
        <Card v-if="current === 'requirements'" title="Server requirements">
            <ul class="space-y-2 text-sm"><li v-for="check in requirements" :key="check.key">
                <strong :class="check.passed ? 'text-emerald-600' : 'text-red-600'">{{ check.passed ? 'Pass' : (check.required ? 'Required' : 'Optional') }}</strong>
                · {{ check.label }} <span class="text-muted">— {{ check.detail }}</span>
            </li></ul>
            <Button class="mt-4" variant="primary" :disabled="!requirementsPass" @click="router.post(route('setup.requirements'))">Continue</Button>
        </Card>
        <Card v-else-if="current === 'database'" title="Database connection">
            <form class="space-y-4" @submit.prevent="db.post(route('setup.database'))">
                <FormField label="Driver" for="db-driver" :error="db.errors.driver" required><select id="db-driver" v-model="db.driver" class="st-input"><option value="mysql">MySQL / MariaDB</option><option value="sqlite">SQLite</option></select></FormField>
                <template v-if="db.driver === 'mysql'">
                    <FormField label="Host" for="db-host" :error="db.errors.host" required><TextInput id="db-host" v-model="db.host" required /></FormField>
                    <FormField label="Port" for="db-port" :error="db.errors.port" required><TextInput id="db-port" v-model="db.port" type="number" required /></FormField>
                    <FormField label="Username" for="db-user" :error="db.errors.username" required><TextInput id="db-user" v-model="db.username" required /></FormField>
                    <FormField label="Password" for="db-password" :error="db.errors.password"><TextInput id="db-password" v-model="db.password" type="password" autocomplete="new-password" /></FormField>
                </template>
                <FormField :label="db.driver === 'sqlite' ? 'SQLite file name or absolute path' : 'Database name'" for="db-name" :error="db.errors.database" required><TextInput id="db-name" v-model="db.database" required /></FormField>
                <div class="flex gap-2"><Button type="submit" variant="primary" :loading="db.processing">Save and continue</Button><Button :disabled="db.processing" @click="db.post(route('setup.database.test'), { preserveState: true })">Test connection</Button></div>
            </form>
        </Card>
        <Card v-else-if="current === 'application'" title="Application settings">
            <form class="space-y-4" @submit.prevent="application.post(route('setup.application'))">
                <FormField label="Application name" for="app-name" :error="application.errors.name" required><TextInput id="app-name" v-model="application.name" required /></FormField>
                <FormField label="Application URL" for="app-url" :error="application.errors.url" required><TextInput id="app-url" v-model="application.url" type="url" required /></FormField>
                <FormField label="Organization name" for="organization" :error="application.errors.organization_name" required><TextInput id="organization" v-model="application.organization_name" required /></FormField>
                <FormField label="Timezone" for="timezone" :error="application.errors.timezone"><select id="timezone" v-model="application.timezone" class="st-input"><option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option></select></FormField>
                <FormField label="Currency" for="currency" :error="application.errors.currency"><select id="currency" v-model="application.currency" class="st-input"><option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option></select></FormField>
                <Button type="submit" variant="primary" :loading="application.processing">Save and continue</Button>
            </form>
        </Card>
        <Card v-else-if="current === 'install'" title="Install database">
            <p class="text-sm text-muted">Run database migrations and install base data. This can take a few minutes.</p>
            <pre v-if="installLog" class="mt-3 overflow-auto whitespace-pre-wrap text-xs">{{ installLog }}</pre>
            <Button class="mt-4" variant="primary" @click="router.post(route('setup.install'))">Run installation</Button>
        </Card>
        <Card v-else-if="current === 'admin'" title="Administrator account">
            <p v-if="hasAdministrator" class="mb-3 text-sm text-muted">An administrator already exists. Enter the details for the initial administrator.</p>
            <form class="space-y-4" @submit.prevent="submitAdmin">
                <FormField label="Name" for="admin-name" :error="admin.errors.name" required><TextInput id="admin-name" v-model="admin.name" required /></FormField>
                <FormField label="Username" for="admin-username" :error="admin.errors.username"><TextInput id="admin-username" v-model="admin.username" /></FormField>
                <FormField label="Email" for="admin-email" :error="admin.errors.email" required><TextInput id="admin-email" v-model="admin.email" type="email" required /></FormField>
                <FormField label="Password" for="admin-password" :error="admin.errors.password" hint="At least 12 characters with upper and lower case letters and numbers." required><TextInput id="admin-password" v-model="admin.password" type="password" autocomplete="new-password" required /></FormField>
                <FormField label="Confirm password" for="admin-confirm" required><TextInput id="admin-confirm" v-model="admin.password_confirmation" type="password" autocomplete="new-password" required /></FormField>
                <Button type="submit" variant="primary" :loading="admin.processing">Create administrator</Button>
            </form>
        </Card>
        <Card v-else-if="current === 'finish'" title="Finish setup">
            <p class="text-sm text-muted">Complete the installation and sign in with your administrator account.</p>
            <Button class="mt-4" variant="primary" @click="router.post(route('setup.finish'))">Finish installation</Button>
        </Card>
        <nav class="mt-5 flex flex-wrap gap-3 text-sm" aria-label="Completed setup steps">
            <Link v-for="step in steps.filter((s) => completed.includes(s.key))" :key="step.key" :href="route('setup.index', { step: step.key })" class="text-accent-ink">{{ step.label }}</Link>
        </nav>
    </SetupLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { ref } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';
import { errorMessage, http } from '@/lib/http';

const props = defineProps<{
    twoFactor: { enabled: boolean; pending: boolean; recovery_codes_remaining: number };
    passkeys: { id: number; name: string; created_at: string | null }[];
    sessions: { id: string; browser: string; platform: string; is_current: boolean; last_active_at: string }[];
    sessionsSupported: boolean;
    policy: { require_two_factor: boolean; password_expired: boolean; passkeys_allowed: boolean };
}>();
const password = ref('');
const code = ref('');
const name = ref('');
const enrollment = ref<{ secret: string; qr_svg: string } | null>(null);
const recoveryCodes = ref<string[]>([]);
const message = ref('');
const busy = ref(false);
const { register, isLoading: registering, isSupported, error: passkeyError } = usePasskeyRegister({
    onSuccess: () => { name.value = ''; router.reload(); },
});

async function confirmed() {
    await http.post(route('password.confirm.store'), { password: password.value });
    password.value = '';
}
async function startEnrollment() {
    busy.value = true; message.value = '';
    try {
        await confirmed();
        const { data } = await http.post(route('account.security.two-factor.enable'));
        enrollment.value = data;
    } catch (error) { message.value = errorMessage(error); }
    finally { busy.value = false; }
}
async function confirmEnrollment() {
    busy.value = true; message.value = '';
    try {
        const { data } = await http.post(route('account.security.two-factor.confirm'), { code: code.value });
        recoveryCodes.value = data.recovery_codes;
        enrollment.value = null; code.value = '';
        router.reload({ only: ['twoFactor'] });
    } catch (error) { message.value = errorMessage(error); }
    finally { busy.value = false; }
}
async function registerPasskey() {
    busy.value = true; message.value = '';
    try { await confirmed(); await register(name.value); }
    catch (error) { message.value = errorMessage(error); }
    finally { busy.value = false; }
}
async function regenerateCodes() {
    busy.value = true; message.value = '';
    try {
        await confirmed();
        const { data } = await http.post(route('account.security.two-factor.recovery'));
        recoveryCodes.value = data.recovery_codes;
        router.reload({ only: ['twoFactor'] });
    } catch (error) { message.value = errorMessage(error); }
    finally { busy.value = false; }
}
async function disableTwoFactor() {
    if (!window.confirm('Disable your authenticator app?')) return;
    busy.value = true; message.value = '';
    try { await confirmed(); router.delete(route('account.security.two-factor.disable')); }
    catch (error) { message.value = errorMessage(error); }
    finally { busy.value = false; }
}
</script>
<template>
    <Head title="Security" />
    <h1 class="mb-6 text-2xl font-semibold">Account security</h1>
    <div class="space-y-5">
        <p v-if="policy.require_two_factor" class="text-sm text-muted">Your organization requires an authenticator app or passkey. If you already have one, sign out and use it to sign in before making security changes. After registering a passkey, sign out and sign in with it to continue.</p>
        <p v-if="policy.password_expired" class="text-sm text-red-600">Your password has expired. <Link class="underline" :href="route('account.password')">Change it now</Link>.</p>
        <p v-if="message" class="text-sm text-red-600" role="alert">{{ message }}</p>
        <Card title="Confirm your identity" description="Enter your password before making security changes.">
            <FormField label="Current password" for="security-password"><TextInput id="security-password" v-model="password" type="password" autocomplete="current-password" /></FormField>
        </Card>
        <Card title="Authenticator app">
            <p class="text-sm">{{ twoFactor.enabled ? 'Enabled' : 'Not enabled' }}</p>
            <template v-if="!twoFactor.enabled">
                <p v-if="twoFactor.pending && !enrollment" class="mt-2 text-sm text-muted">Restart setup to display a new secret.</p>
                <Button class="mt-3" :disabled="!password || busy" @click="startEnrollment">Set up authenticator</Button>
                <div v-if="enrollment" class="mt-4 space-y-3">
                    <p class="text-sm">Scan the QR code or enter this secret: <strong>{{ enrollment.secret }}</strong></p>
                    <img :src="enrollment.qr_svg" alt="Authenticator setup QR code" class="size-48" />
                    <form class="space-y-3" @submit.prevent="confirmEnrollment">
                        <FormField label="Authenticator code" for="setup-code"><TextInput id="setup-code" v-model="code" inputmode="numeric" autocomplete="one-time-code" required /></FormField>
                        <Button type="submit" variant="primary" :loading="busy">Confirm setup</Button>
                    </form>
                </div>
            </template>
            <div v-else class="mt-3 space-y-3">
                <p class="text-sm text-muted">{{ twoFactor.recovery_codes_remaining }} recovery codes remaining.</p>
                <div class="flex flex-wrap gap-2">
                    <Button :disabled="!password || busy" @click="regenerateCodes">Generate new recovery codes</Button>
                    <Button variant="danger" :disabled="!password || busy" @click="disableTwoFactor">Disable authenticator</Button>
                </div>
            </div>
            <div v-if="recoveryCodes.length" class="mt-4 rounded border border-line p-3">
                <p class="text-sm font-semibold">Save these recovery codes now. They will not be shown again.</p>
                <ul class="mt-2 font-mono text-sm"><li v-for="item in recoveryCodes" :key="item">{{ item }}</li></ul>
            </div>
        </Card>
        <Card title="Passkeys">
            <ul v-if="passkeys.length" class="mb-3 space-y-1 text-sm"><li v-for="key in passkeys" :key="key.id">{{ key.name }}</li></ul>
            <p v-else class="mb-3 text-sm text-muted">No passkeys registered.</p>
            <form v-if="policy.passkeys_allowed" class="space-y-3" @submit.prevent="registerPasskey">
                <FormField label="New passkey name" for="passkey-name"><TextInput id="passkey-name" v-model="name" maxlength="100" required /></FormField>
                <Button type="submit" :disabled="!password || !name || !isSupported || busy || registering">Add passkey</Button>
                <p v-if="passkeyError" class="text-sm text-red-600" role="alert">{{ passkeyError }}</p>
                <p v-if="!isSupported" class="text-sm text-muted">Passkeys are unavailable in this browser or on this connection.</p>
            </form>
            <p v-else class="text-sm text-muted">Passkeys are disabled by your organization.</p>
        </Card>
        <Card title="Password"><Link :href="route('account.password')" class="text-sm text-accent-ink">Change password</Link></Card>
        <Card title="Sessions">
            <p v-if="!sessionsSupported" class="text-sm text-muted">Session management is unavailable with the current session driver.</p>
            <ul v-else class="space-y-2 text-sm"><li v-for="session in sessions" :key="session.id">{{ session.browser }} on {{ session.platform }} <span v-if="session.is_current">(current session)</span></li></ul>
        </Card>
    </div>
</template>

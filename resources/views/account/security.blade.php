@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5" x-data="accountSecurity(@js($twoFactor['enabled']), @js($twoFactor['recovery_codes_remaining']))">
    <h1 class="text-2xl font-bold">Account security</h1>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('warning'))<x-alert color="yellow">{{ session('warning') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    @if($policy['require_two_factor'])<p class="text-sm text-gray-600">Your organization requires an authenticator app or passkey. If you already have one, sign out and use it to sign in before making security changes. After registering a passkey, sign out and sign in with it to continue.</p>@endif
    @if($policy['password_expired'])<p class="text-sm text-red-600">Your password has expired. <a class="underline" href="{{ route('account.password') }}">Change it now</a>.</p>@endif
    <p x-show="message" x-text="message" role="alert" class="text-sm text-red-600" x-cloak></p>
    <x-card bordered shadowless>
        <h2 class="mb-2 text-lg font-semibold">Confirm your identity</h2><p class="mb-3 text-sm text-gray-500">Enter your password before making security changes.</p>
        <label class="block max-w-md text-sm">Current password <x-input x-model="password" id="security-password" type="password" autocomplete="current-password" /></label>
    </x-card>
    <x-card bordered shadowless>
        <h2 class="mb-2 text-lg font-semibold">Authenticator app</h2><p x-text="twoFactorEnabled ? 'Enabled' : 'Not enabled'"></p>
        <div x-show="!twoFactorEnabled">
            @if($twoFactor['pending'])<p class="mt-2 text-sm text-gray-500">Restart setup to display a new secret.</p>@endif
            <x-button class="mt-3" color="secondary" x-bind:disabled="!password || busy" x-on:click="startEnrollment">Set up authenticator</x-button>
            <div x-show="enrollment" class="mt-4 space-y-3" x-cloak>
                <p class="text-sm">Scan the QR code or enter this secret: <strong x-text="enrollment?.secret"></strong></p>
                <img x-bind:src="enrollment?.qr_svg" alt="Authenticator setup QR code" class="size-48">
                <form class="space-y-3" x-on:submit.prevent="confirmEnrollment">
                    <label class="block text-sm">Authenticator code <x-input x-model="code" inputmode="numeric" autocomplete="one-time-code" required /></label>
                    <x-button type="submit" color="primary" x-bind:disabled="busy">Confirm setup</x-button>
                </form>
            </div>
        </div>
        <div x-show="twoFactorEnabled">
            <p class="mt-2 text-sm text-gray-500"><span x-text="recoveryRemaining"></span> recovery codes remaining.</p>
            <div class="mt-3 flex flex-wrap gap-2"><x-button color="secondary" x-bind:disabled="!password || busy" x-on:click="regenerateCodes">Generate new recovery codes</x-button><x-button color="red" x-bind:disabled="!password || busy" x-on:click="disableTwoFactor">Disable authenticator</x-button></div>
        </div>
        <div x-show="recoveryCodes.length" class="mt-4 rounded border p-3" x-cloak><p class="text-sm font-semibold">Save these recovery codes now. They will not be shown again.</p><ul class="mt-2 font-mono text-sm"><template x-for="item in recoveryCodes" :key="item"><li x-text="item"></li></template></ul></div>
    </x-card>
    <x-card bordered shadowless>
        <h2 class="mb-2 text-lg font-semibold">Passkeys</h2>
        @forelse($passkeys as $key)<p class="text-sm">{{ $key['name'] }} · added {{ $key['created_at'] ? \Illuminate\Support\Carbon::parse($key['created_at'])->format('M j, Y') : '—' }}@if($key['last_used_at']) · last used {{ \Illuminate\Support\Carbon::parse($key['last_used_at'])->diffForHumans() }}@endif</p>@empty<p class="mb-3 text-sm text-gray-500">No passkeys registered.</p>@endforelse
        @if($policy['passkeys_allowed'])
            <form class="mt-3 max-w-md space-y-3" x-on:submit.prevent="registerPasskey"><label class="block text-sm">New passkey name <x-input x-model="name" maxlength="100" required /></label><x-button type="submit" color="secondary" x-bind:disabled="!password || !name || busy || !passkeysSupported">Add passkey</x-button><p x-show="!passkeysSupported" class="text-sm text-gray-500">Passkeys are unavailable in this browser or on this connection.</p></form>
        @else<p class="text-sm text-gray-500">Passkeys are disabled by your organization.</p>@endif
    </x-card>
    <x-card bordered shadowless><h2 class="mb-2 text-lg font-semibold">Password</h2><a href="{{ route('account.password') }}" class="text-primary-700 underline">Change password</a></x-card>
    <x-card bordered shadowless>
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg font-semibold">Sessions</h2>@if($sessionsSupported && count($sessions) > 1)<form method="POST" action="{{ route('account.security.sessions.destroy-others') }}" onsubmit="return confirm('Sign out all other sessions?')">@csrf @method('DELETE')<x-button type="submit" color="secondary">Sign out other sessions</x-button></form>@endif</div>
        @if(!$sessionsSupported)<p class="text-sm text-gray-500">Session management is unavailable with the current session driver.</p>
        @else<ul class="mt-3 space-y-2 text-sm">@forelse($sessions as $session)<li class="flex flex-wrap items-center justify-between gap-2">{{ $session['browser'] }} on {{ $session['platform'] }} <span>{{ $session['is_current'] ? '(current session)' : '' }}</span>@unless($session['is_current'])<form method="POST" action="{{ route('account.security.sessions.destroy', $session['id']) }}" onsubmit="return confirm('Sign out this session?')">@csrf @method('DELETE')<x-button type="submit" color="secondary">Sign out</x-button></form>@endunless</li>@empty<li>No other active sessions.</li>@endforelse</ul>@endif
    </x-card>
</div>
<script>
function accountSecurity(twoFactorEnabled, recoveryRemaining) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const baseHeaders = {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf};
    const message = (error) => error?.message || 'The request could not be completed.';
    const decode = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
    const encode = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const serialise = (credential) => ({
        id: credential.id, rawId: encode(credential.rawId), type: credential.type,
        response: {clientDataJSON: encode(credential.response.clientDataJSON), attestationObject: encode(credential.response.attestationObject)},
        clientExtensionResults: credential.getClientExtensionResults()
    });
    return {
        password: '', code: '', name: '', enrollment: null, recoveryCodes: [], message: '', busy: false, twoFactorEnabled, recoveryRemaining,
        passkeysSupported: !!(window.PublicKeyCredential && navigator.credentials?.create),
        async request(url, method = 'POST', payload = {}) {
            const response = await fetch(url, {method, credentials: 'same-origin', headers: baseHeaders, body: method === 'GET' ? undefined : JSON.stringify(payload)});
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'The request could not be completed.');
            return data;
        },
        async confirmed() {
            await this.request(@js(route('password.confirm.store')), 'POST', {password: this.password});
            this.password = '';
        },
        async run(action) {
            this.busy = true; this.message = '';
            try { await action(); } catch (error) { this.message = message(error); } finally { this.busy = false; }
        },
        startEnrollment() { return this.run(async () => { await this.confirmed(); this.enrollment = await this.request(@js(route('account.security.two-factor.enable'))); }); },
        confirmEnrollment() { return this.run(async () => { const data = await this.request(@js(route('account.security.two-factor.confirm')), 'POST', {code: this.code}); this.recoveryCodes = data.recovery_codes; this.recoveryRemaining = data.recovery_codes.length; this.twoFactorEnabled = true; this.enrollment = null; this.code = ''; }); },
        regenerateCodes() { return this.run(async () => { await this.confirmed(); const data = await this.request(@js(route('account.security.two-factor.recovery'))); this.recoveryCodes = data.recovery_codes; this.recoveryRemaining = data.recovery_codes.length; }); },
        disableTwoFactor() { if (!confirm('Disable your authenticator app?')) return; return this.run(async () => { await this.confirmed(); await this.request(@js(route('account.security.two-factor.disable')), 'DELETE'); window.location.reload(); }); },
        registerPasskey() { return this.run(async () => {
            await this.confirmed();
            const {options} = await this.request('/user/passkeys/options', 'GET');
            options.challenge = decode(options.challenge);
            if (options.user?.id) options.user.id = decode(options.user.id);
            if (options.excludeCredentials) options.excludeCredentials = options.excludeCredentials.map(item => ({...item, id: decode(item.id)}));
            const credential = await navigator.credentials.create({publicKey: options});
            await this.request('/user/passkeys', 'POST', {name: this.name, credential: serialise(credential)});
            this.name = ''; window.location.reload();
        }); }
    };
}
</script>
@endcomponent

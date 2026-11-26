import { Passkeys, UserCancelledError } from '@laravel/passkeys';

const button = document.querySelector<HTMLButtonElement>('#passkey-login');
const error = document.querySelector<HTMLParagraphElement>('#passkey-error');
const unavailable = document.querySelector<HTMLParagraphElement>('#passkey-unavailable');
const remember = document.querySelector<HTMLInputElement>('#remember');

if (button && error && unavailable) {
    if (!Passkeys.isSupported()) {
        button.disabled = true;
        unavailable.classList.remove('hidden');
    }

    button.addEventListener('click', async () => {
        button.disabled = true;
        error.classList.add('hidden');

        try {
            const response = await Passkeys.verify({ remember: () => remember?.checked ?? false });
            window.location.assign(response.redirect || '/');
        } catch (reason) {
            if (!(reason instanceof UserCancelledError)) {
                error.textContent = reason instanceof Error ? reason.message : 'Passkey sign-in failed.';
                error.classList.remove('hidden');
            }
        } finally {
            button.disabled = !Passkeys.isSupported();
        }
    });
}

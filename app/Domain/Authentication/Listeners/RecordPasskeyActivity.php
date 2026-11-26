<?php

namespace App\Domain\Authentication\Listeners;

use App\Domain\Administration\Models\AuditLog;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

class RecordPasskeyActivity
{
    public function onRegistered(PasskeyRegistered $event): void
    {
        AuditLog::record('passkey.registered', $event->user, [], ['name' => $event->passkey->name], 'Passkey "'.$event->passkey->name.'" added', (int) $event->user->getAuthIdentifier());
    }

    public function onDeleted(PasskeyDeleted $event): void
    {
        AuditLog::record('passkey.removed', $event->user, ['name' => $event->passkey->name], [], 'Passkey "'.$event->passkey->name.'" removed', (int) $event->user->getAuthIdentifier());
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            PasskeyRegistered::class => 'onRegistered',
            PasskeyDeleted::class => 'onDeleted',
        ];
    }
}

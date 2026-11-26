<?php

namespace App\Domain\Authentication\Http\Controllers\Account;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Authentication\Services\ActiveSessions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;

class SessionController extends Controller
{
    public function destroy(Request $request, ActiveSessions $sessions, string $session): RedirectResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/', $session) === 1, 404);

        $revoked = $sessions->revoke($request->user(), $session, $request->session()->getId());

        return back()->with($revoked ? 'success' : 'error', $revoked ? 'Session signed out.' : 'That session no longer exists.');
    }

    public function destroyOthers(Request $request, ActiveSessions $sessions): RedirectResponse
    {
        $count = $sessions->revokeOthers($request->user(), $request->session()->getId());

        return back()->with('success', $count === 1 ? 'Signed out 1 other session.' : "Signed out {$count} other sessions.");
    }

    /** Rename a passkey (registration and deletion use the laravel/passkeys routes). */
    public function renamePasskey(Request $request, Passkey $passkey): RedirectResponse
    {
        abort_unless((int) $passkey->user_id === (int) $request->user()->id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $old = $passkey->name;
        $passkey->update(['name' => $data['name']]);
        AuditLog::record('passkey.renamed', $request->user(), ['name' => $old], ['name' => $data['name']], 'Passkey renamed');

        return back()->with('success', 'Passkey renamed.');
    }
}

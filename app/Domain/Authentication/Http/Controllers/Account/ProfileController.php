<?php

namespace App\Domain\Authentication\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $preferences = $user->preferences ?? [];

        return view('account.profile', [
            'profile' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'timezone' => $preferences['timezone'] ?? null,
                'theme' => $preferences['theme'] ?? 'system',
                'reduce_motion' => (bool) ($preferences['reduce_motion'] ?? false),
                'warehouse_sounds' => (bool) ($preferences['warehouse_sounds'] ?? true),
            ],
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'timezone' => ['nullable', 'timezone:all'],
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'reduce_motion' => ['boolean'],
            'warehouse_sounds' => ['boolean'],
        ]);

        $preferences = array_merge($user->preferences ?? [], [
            'timezone' => $data['timezone'] ?? null,
            'theme' => $data['theme'],
            'reduce_motion' => (bool) ($data['reduce_motion'] ?? false),
            'warehouse_sounds' => (bool) ($data['warehouse_sounds'] ?? true),
        ]);

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'] ?: null,
            'email' => $data['email'],
            'preferences' => $preferences,
        ]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return back()->with('success', 'Profile updated.');
    }
}

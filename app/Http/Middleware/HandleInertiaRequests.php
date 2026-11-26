<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Services\SettingsRepository;
use App\Support\Installation;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $installed = Installation::isInstalled();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'version' => config('stagetrack.version'),
                'organization' => $installed ? app(SettingsRepository::class)->get('general.organization_name', config('app.name')) : config('app.name'),
                'currency' => $installed ? app(SettingsRepository::class)->get('general.currency', 'USD') : 'USD',
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_super_admin' => (bool) $user->is_super_admin,
                    'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                    'preferences' => $user->preferences ?? (object) [],
                ] : null,
                'permissions' => $user ? ($user->is_super_admin ? ['*'] : $user->permissionKeys()) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}

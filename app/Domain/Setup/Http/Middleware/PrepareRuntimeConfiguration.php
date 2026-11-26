<?php

namespace App\Domain\Setup\Http\Middleware;

use App\Domain\Authentication\Services\SecurityPolicy;
use App\Domain\Setup\Services\EnvironmentWriter;
use App\Domain\Setup\Services\Installer;
use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Runs first in the web stack. Before installation (and on the wizard) the
 * database may not exist yet, so sessions and cache are switched to the
 * filesystem and an APP_KEY is generated when missing. After installation it
 * applies the organization's session lifetime policy.
 */
class PrepareRuntimeConfiguration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Installation::isInstalled()) {
            config(['session.driver' => 'file', 'cache.default' => 'file']);
            $this->ensureAppKey();
        } else {
            try {
                config(['session.lifetime' => app(SecurityPolicy::class)->sessionLifetimeMinutes()]);
            } catch (Throwable) {
                // Keep the configured lifetime when settings are unavailable.
            }
        }

        return $next($request);
    }

    private function ensureAppKey(): void
    {
        if (! empty(config('app.key'))) {
            return;
        }

        $writer = new EnvironmentWriter;
        $key = $writer->ensureAppKey(fn () => app(Installer::class)->generateKey());

        config(['app.key' => $key]);
        app()->forgetInstance('encrypter');
    }
}

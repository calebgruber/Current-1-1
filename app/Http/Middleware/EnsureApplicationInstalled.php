<?php

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Routes everything to the setup wizard until installation completes, and locks the wizard afterwards. */
class EnsureApplicationInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $isSetupRoute = $request->is('setup') || $request->is('setup/*');

        if (! Installation::isInstalled() && ! $isSetupRoute && ! $request->is('up')) {
            return redirect('/setup');
        }

        if (Installation::isInstalled() && $isSetupRoute) {
            abort(404);
        }

        return $next($request);
    }
}

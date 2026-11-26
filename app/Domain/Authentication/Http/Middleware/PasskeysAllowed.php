<?php

namespace App\Domain\Authentication\Http\Middleware;

use App\Domain\Authentication\Services\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks passkey registration when the organization has disabled passkeys (removal is always allowed). */
class PasskeysAllowed
{
    public function __construct(private readonly SecurityPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('DELETE') && ! $this->policy->passkeysAllowed()) {
            abort(403, 'Passkeys are disabled by your organization.');
        }

        return $next($request);
    }
}

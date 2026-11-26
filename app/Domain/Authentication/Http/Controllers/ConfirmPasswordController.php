<?php

namespace App\Domain\Authentication\Http\Controllers;

use App\Domain\Authentication\Services\SecurityPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmPasswordController extends Controller
{
    public function show(SecurityPolicy $policy): Response
    {
        return Inertia::render('Auth/ConfirmPassword', [
            'passkeysEnabled' => $policy->passkeysAllowed() && request()->user()->passkeys()->exists(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json(['confirmed' => $this->recentlyConfirmed($request)]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:1024']]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        $request->session()->passwordConfirmed();

        if ($request->expectsJson()) {
            return response()->json(['confirmed' => true]);
        }

        return redirect()->intended(route('account.security'));
    }

    private function recentlyConfirmed(Request $request): bool
    {
        $at = (int) $request->session()->get('auth.password_confirmed_at', 0);

        return $at > 0 && (time() - $at) < (int) config('auth.password_timeout', 10800);
    }
}

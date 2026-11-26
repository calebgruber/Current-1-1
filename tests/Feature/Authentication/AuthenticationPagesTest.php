<?php

namespace Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_authentication_pages_render(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->has('passkeysEnabled'));
        $this->get(route('password.request'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
        $this->get(route('password.reset', ['token' => 'example', 'email' => 'user@example.com']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword')
                ->where('token', 'example')->where('email', 'user@example.com'));
    }

    public function test_password_login_redirects_to_the_second_factor_page(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/TwoFactorChallenge'));
    }

    public function test_account_security_and_password_pages_render(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account.security'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Security')
                ->has('twoFactor')->has('passkeys')->has('policy'));
        $this->get(route('account.password'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Password')->has('policy'));
        $this->get(route('account.profile'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Profile')->has('profile'));
        $this->get(route('password.confirm'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ConfirmPassword'));
    }
}

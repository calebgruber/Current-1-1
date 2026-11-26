<?php

namespace Tests\Feature\Authentication;

use App\Domain\Authentication\Services\RecoveryCodes;
use App\Domain\Authentication\Services\Totp;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

class SessionAssuranceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function addPasskey(User $user): int
    {
        return DB::table('passkeys')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Test passkey',
            'credential_id' => 'credential-'.$user->id,
            'credential' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function passwordLogin(User $user): void
    {
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect();
    }

    public function test_password_login_with_an_enrolled_passkey_cannot_access_or_replace_factors(): void
    {
        $user = $this->admin();
        $this->addPasskey($user);
        $this->passwordLogin($user);

        $this->get('/')->assertRedirect(route('account.security'));
        $this->get(route('account.security'))->assertOk();
        $this->postJson(route('account.security.two-factor.enable'))->assertForbidden();
        $this->postJson(route('password.confirm.store'), ['password' => 'password'])->assertForbidden();
        $this->get(route('account.password'))->assertRedirect(route('account.security'));
    }

    public function test_password_only_login_is_unaffected_when_policy_is_disabled(): void
    {
        app(SettingsRepository::class)->set('security.require_two_factor_for_admins', false);
        $user = $this->admin();
        $this->addPasskey($user);
        $this->passwordLogin($user);

        $this->get('/')->assertOk();
    }

    public function test_verified_recovery_code_establishes_assurance_and_disabling_totp_revokes_it(): void
    {
        $code = 'ABCD-EFGH-JKLM';
        $user = $this->admin();
        $user->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [app(RecoveryCodes::class)->hash($code)],
        ])->save();
        $this->passwordLogin($user);
        $this->get('/')->assertRedirect(route('login'));
        $this->post(route('two-factor.verify'), ['recovery_code' => $code])->assertRedirect();
        $this->get('/')->assertOk();

        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->get('/')->assertRedirect(route('account.security'));
    }

    public function test_first_time_totp_enrollment_establishes_assurance(): void
    {
        $user = $this->admin();
        $this->passwordLogin($user);
        $this->get('/')->assertRedirect(route('account.security'));
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('account.security.two-factor.enable'))->assertOk();
        $secret = $user->fresh()->two_factor_secret;
        $this->postJson(route('account.security.two-factor.confirm'), ['code' => app(Totp::class)->code($secret)])
            ->assertOk()->assertJsonCount(10, 'recovery_codes');
        $this->get('/')->assertOk();
    }

    public function test_passkey_login_marks_only_the_verified_session_and_revocation_removes_assurance(): void
    {
        $user = $this->admin();
        $id = $this->addPasskey($user);

        // Stand in for the package's already-verified assertion, then use its real
        // authorization callback and the same guard login sequence as its controller.
        Route::middleware('web')->post('/test-verified-passkey', function (Request $request) use ($id) {
            $passkey = Passkeys::passkeyModel()::findOrFail($id);
            abort_unless(Passkeys::allowsLogin($request, $passkey), 403);
            Auth::guard('web')->login($passkey->user);
            $request->session()->regenerate();

            return response()->noContent();
        });

        $this->post('/test-verified-passkey')->assertNoContent();
        $this->get('/')->assertOk();
        DB::table('passkeys')->where('id', $id)->delete();
        $this->get('/')->assertRedirect(route('account.security'));
    }

    public function test_stale_or_other_users_assurance_never_authorizes_password_login(): void
    {
        $user = $this->admin();
        $id = $this->addPasskey($user);
        $this->withSession(['auth.second_factor' => [
            'user' => (string) $user->id,
            'method' => 'passkey',
            'passkey' => (string) $id,
        ]]);
        $this->passwordLogin($user);
        $this->get('/')->assertRedirect(route('account.security'));

        $other = $this->admin();
        $this->actingAs($other)->withSession(['auth.second_factor' => [
            'user' => (string) $user->id,
            'method' => 'passkey',
            'passkey' => (string) $id,
        ]])->get('/')->assertRedirect(route('account.security'));
    }

    public function test_remembered_session_without_proof_cannot_replace_an_existing_totp_factor(): void
    {
        $user = $this->admin();
        $user->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->get('/')->assertRedirect(route('account.security'));
        $this->postJson(route('account.security.two-factor.enable'))->assertForbidden();
        $this->postJson(route('account.security.two-factor.recovery'))->assertForbidden();
    }

    public function test_disabling_passkeys_invalidates_existing_passkey_assurance(): void
    {
        $user = $this->admin();
        $id = $this->addPasskey($user);
        $this->actingAs($user)->withSession(['auth.second_factor' => [
            'user' => (string) $user->id,
            'method' => 'passkey',
            'passkey' => (string) $id,
        ]])->get('/')->assertOk();

        app(SettingsRepository::class)->set('security.allow_passkeys', false);
        $this->get('/')->assertRedirect(route('account.security'));
    }
}

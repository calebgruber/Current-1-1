<?php

namespace App\Providers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Assets\Models\Asset;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Reporting\Models\Report;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Rules\Models\ValidationRule;
use App\Domain\Settings\Services\SettingsRepository;
use App\Domain\Authentication\Models\Role;
use App\Domain\Authentication\Services\SecurityPolicy;
use App\Domain\Authentication\Services\SessionAssurance;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'product' => Product::class,
            'location' => Location::class,
            'asset' => Asset::class,
            'contact' => Contact::class,
            'job' => ProductionJob::class,
            'reservation' => Reservation::class,
            'maintenance' => MaintenanceRecord::class,
            'document' => GeneratedDocument::class,
            'document_template' => DocumentTemplate::class,
            'report' => Report::class,
            'rule' => ValidationRule::class,
            'audit' => AuditLog::class,
            'custom_field' => \App\Domain\CustomFields\Models\CustomFieldDefinition::class,
        ]);

        // Database-driven authorization: any ability shaped like "module.resource.action"
        // is resolved against the user's role permissions. Super administrators bypass checks.
        Gate::before(function (User $user, string $ability) {
            if ($user->is_super_admin) {
                return true;
            }
            if (substr_count($ability, '.') === 2) {
                return $user->hasPermission($ability) ?: null;
            }

            return null;
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard !== 'web' || ! $event->user instanceof User) {
                return;
            }

            $request = request();
            if (! $request->hasSession()) {
                return;
            }
            $assurance = app(SessionAssurance::class);
            $assurance->clear($request);
            $passkey = $request->attributes->get('verified_login_passkey');
            if ($passkey instanceof Passkey && (string) $passkey->user_id === (string) $event->user->getKey()) {
                $assurance->markPasskey($request, $event->user, $passkey);
            }
        });

        Passkeys::authorizeLoginUsing(function (Request $request, PasskeyUser $user, Passkey $passkey): bool {
            /** @var User $user */
            if (! $user->is_active || ! app(SecurityPolicy::class)->passkeysAllowed()) {
                throw ValidationException::withMessages(['credential' => ['Passkey sign-in is not available for this account.']]);
            }
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip(), 'failed_login_attempts' => 0])->saveQuietly();
            AuditLog::record('login.passkey', $user, [], [], 'Signed in with passkey "'.$passkey->name.'"', $user->id);
            $request->attributes->set('verified_login_passkey', $passkey);

            return true;
        });
    }
}

<?php

namespace Tests\Feature\Administration;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Administration\Services\SystemStatus;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_status_requires_view_permission(): void
    {
        $this->get('/admin/system')->assertRedirect('/login');
        $this->actingAs($this->userWithPermissions(['admin.backups.view']))->get('/admin/system')->assertForbidden();
        $this->actingAs($this->userWithPermissions(['admin.system.admin']))->get('/admin/system')->assertForbidden();
        $this->actingAs($this->userWithPermissions(['admin.system.view']))->get('/admin/system')
            ->assertOk()->assertViewIs('administration.system.index')
            ->assertViewHas('status.database', 'ok')
            ->assertViewHas('status.migrations.status', 'available')
            ->assertSee('Health')->assertSee('Migrations');
    }

    public function test_pending_migrations_are_reported_without_running_them(): void
    {
        $migration = DB::table('migrations')->orderBy('id')->value('migration');
        DB::table('migrations')->where('migration', $migration)->delete();

        $this->actingAs($this->userWithPermissions(['admin.system.view']))->get('/admin/system')
            ->assertOk()->assertViewIs('administration.system.index')
            ->assertViewHas('status.migrations.status', 'available')
            ->assertViewHas('status.migrations.pending', fn ($pending) => in_array($migration, $pending, true));
        $this->assertDatabaseMissing('migrations', ['migration' => $migration]);
    }

    public function test_missing_migration_repository_is_reported(): void
    {
        $user = $this->userWithPermissions(['admin.system.view']);
        Schema::drop('migrations');

        $this->actingAs($user)->get('/admin/system')->assertOk()->assertViewIs('administration.system.index')
            ->assertViewHas('status.migrations.status', 'repository_missing')
            ->assertViewHas('status.migrations.pending', null)
            ->assertSee('Migration repository is missing.');
    }

    public function test_failed_health_checks_report_unavailable_without_exposing_errors(): void
    {
        $migrator = \Mockery::mock(Migrator::class);
        $migrator->shouldReceive('repositoryExists')->once()->andThrow(new \RuntimeException('private migration error'));
        DB::shouldReceive('connection')->once()->andThrow(new \RuntimeException('private database error'));

        $status = app(SystemStatus::class)->snapshot($migrator);

        $this->assertSame('unavailable', $status['database']);
        $this->assertSame(['status' => 'unavailable', 'applied' => null, 'pending' => null], $status['migrations']);
    }

    public function test_cache_clear_requires_admin_permission_and_valid_fixed_target(): void
    {
        $this->post('/admin/system/cache', ['cache' => 'view'])->assertRedirect('/login');
        Artisan::shouldReceive('call')->never();
        $this->actingAs($this->userWithPermissions(['admin.system.view']))
            ->post('/admin/system/cache', ['cache' => 'view'])->assertForbidden();
        $this->actingAs($this->userWithPermissions(['admin.system.admin']))
            ->post('/admin/system/cache', ['cache' => 'migrate'])->assertSessionHasErrors('cache');
        $this->assertDatabaseMissing('audit_logs', ['event' => 'system.cache_clear_requested']);
    }

    public function test_allowed_cache_clear_is_audited_and_reports_success(): void
    {
        $user = $this->userWithPermissions(['admin.system.admin']);
        Artisan::shouldReceive('call')->once()->with('view:clear')->andReturn(0);

        $this->actingAs($user)->from('/admin/system')->post('/admin/system/cache', ['cache' => 'view'])
            ->assertRedirect('/admin/system')->assertSessionHas('success', 'View cache cleared.');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'event' => 'system.cache_clear_requested']);
        $this->assertSame(['cache' => 'view'], AuditLog::where('event', 'system.cache_clear_requested')->firstOrFail()->new_values);
    }

    public function test_cache_clear_failure_is_not_reported_as_success(): void
    {
        Artisan::shouldReceive('call')->once()->with('config:clear')->andReturn(1);
        $this->actingAs($this->userWithPermissions(['admin.system.admin']))
            ->post('/admin/system/cache', ['cache' => 'config'])
            ->assertSessionHas('error')->assertSessionMissing('success');
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.cache_clear_requested']);
    }

    public function test_cache_clear_exception_is_not_exposed(): void
    {
        Artisan::shouldReceive('call')->once()->with('route:clear')->andThrow(new \RuntimeException('private error'));
        $this->actingAs($this->userWithPermissions(['admin.system.admin']))
            ->post('/admin/system/cache', ['cache' => 'route'])
            ->assertSessionHas('error', 'Cache clearing failed. Check the application logs.')
            ->assertSessionMissing('success');
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.cache_clear_requested']);
    }

    public function test_migration_form_is_only_visible_to_system_administrators(): void
    {
        $this->actingAs($this->userWithPermissions(['admin.system.view']))->get('/admin/system')
            ->assertOk()->assertDontSee('Run pending migrations');
        $this->actingAs($this->userWithPermissions(['admin.system.view', 'admin.system.admin']))->get('/admin/system')
            ->assertOk()->assertSee('Run pending migrations')->assertSee('Take and verify a database backup')
            ->assertSee('name="_token"', false)->assertSee('name="confirmation"', false);
    }

    public function test_migrations_require_admin_permission_and_recent_password_confirmation(): void
    {
        $this->post('/admin/system/migrations', ['confirmation' => 'MIGRATE'])->assertRedirect('/login');
        Artisan::shouldReceive('call')->never();
        $this->actingAs($this->userWithPermissions(['admin.system.view']))
            ->post('/admin/system/migrations', ['confirmation' => 'MIGRATE'])->assertForbidden();
        $this->actingAs($this->userWithPermissions(['admin.system.admin']))
            ->post('/admin/system/migrations', ['confirmation' => 'MIGRATE'])
            ->assertRedirect(route('password.confirm'));
        $this->assertDatabaseMissing('audit_logs', ['event' => 'system.migrations_requested']);
    }

    public function test_migrations_require_explicit_valid_confirmation(): void
    {
        $user = $this->userWithPermissions(['admin.system.admin']);
        Artisan::shouldReceive('call')->never();
        Cache::shouldReceive('lock')->never();

        foreach ([[], ['confirmation' => 'yes'], ['confirmation' => 'migrate'], ['confirmation' => ['MIGRATE']]] as $data) {
            $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
                ->post('/admin/system/migrations', $data)->assertSessionHasErrors('confirmation');
        }

        $this->assertDatabaseMissing('audit_logs', ['event' => 'system.migrations_requested']);
    }

    public function test_migration_submission_requires_a_valid_csrf_token(): void
    {
        $this->actingAs($this->userWithPermissions(['admin.system.admin']))
            ->withSession(['auth.password_confirmed_at' => time(), '_token' => 'valid-csrf-token']);
        Artisan::shouldReceive('call')->never();
        $environment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->post('/admin/system/migrations', ['confirmation' => 'MIGRATE'])->assertStatus(419);
            $this->post('/admin/system/migrations', ['confirmation' => 'MIGRATE', '_token' => 'wrong-token'])
                ->assertStatus(419);
            $this->assertDatabaseMissing('audit_logs', ['event' => 'system.migrations_requested']);
        } finally {
            $this->app['env'] = $environment;
        }
    }

    public function test_migrations_run_only_the_fixed_command_and_are_audited(): void
    {
        $user = $this->userWithPermissions(['admin.system.admin']);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(0);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->from('/admin/system')->post('/admin/system/migrations', [
                'confirmation' => 'MIGRATE',
                'command' => 'migrate:fresh',
                'path' => '/untrusted',
                'database' => 'other',
                'force' => false,
            ])->assertRedirect('/admin/system')
            ->assertSessionHas('success', 'Pending database migrations completed.')->assertSessionMissing('error');

        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'event' => 'system.migrations_requested']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'event' => 'system.migrations_succeeded']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'system.migrations_failed']);
        $lock = Cache::lock('system.migrations', 3600);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_nonzero_migration_exit_is_audited_and_releases_the_lock(): void
    {
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(1);

        $this->actingAs($this->userWithPermissions(['admin.system.admin']))
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/system/migrations', ['confirmation' => 'MIGRATE'])
            ->assertSessionHas('error', 'Database migrations failed. Check the application logs before retrying.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('audit_logs', ['event' => 'system.migrations_requested']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.migrations_failed']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'system.migrations_succeeded']);
        $lock = Cache::lock('system.migrations', 3600);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_migration_exception_is_not_exposed_and_releases_the_lock(): void
    {
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])
            ->andThrow(new \RuntimeException('private database credentials and command output'));

        $this->actingAs($this->userWithPermissions(['admin.system.admin']))
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/system/migrations', ['confirmation' => 'MIGRATE'])
            ->assertSessionHas('error', 'Database migrations failed. Check the application logs before retrying.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('audit_logs', ['event' => 'system.migrations_failed']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'system.migrations_succeeded']);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'private database credentials and command output']);
        $lock = Cache::lock('system.migrations', 3600);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_active_migration_lock_blocks_submissions_without_releasing_the_other_lock(): void
    {
        $lock = Cache::lock('system.migrations', 3600);
        $this->assertTrue($lock->get());
        Artisan::shouldReceive('call')->never();

        try {
            $this->actingAs($this->userWithPermissions(['admin.system.admin']))
                ->withSession(['auth.password_confirmed_at' => time()])
                ->post('/admin/system/migrations', ['confirmation' => 'MIGRATE'])
                ->assertSessionHas('error', 'Another migration request is already running. Wait for it to finish.')
                ->assertSessionMissing('success');

            $this->assertDatabaseHas('audit_logs', ['event' => 'system.migrations_requested']);
            $this->assertDatabaseHas('audit_logs', ['event' => 'system.migrations_failed']);
            $this->assertDatabaseMissing('audit_logs', ['event' => 'system.migrations_succeeded']);
            $this->assertFalse(Cache::lock('system.migrations', 3600)->get());
        } finally {
            $lock->release();
        }
    }

    public function test_unavailable_lock_store_prevents_migrations_and_is_not_exposed(): void
    {
        Cache::shouldReceive('lock')->once()->with('system.migrations', 3600)
            ->andThrow(new \RuntimeException('private cache connection error'));
        Artisan::shouldReceive('call')->never();

        $this->actingAs($this->userWithPermissions(['admin.system.admin']))
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/system/migrations', ['confirmation' => 'MIGRATE'])
            ->assertSessionHas('error', 'Database migrations failed. Check the application logs before retrying.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('audit_logs', ['event' => 'system.migrations_failed']);
    }
}

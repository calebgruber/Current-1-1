<?php

namespace Tests\Feature\Administration;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Administration\Services\SystemStatus;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
}

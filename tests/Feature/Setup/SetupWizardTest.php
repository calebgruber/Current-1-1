<?php

namespace Tests\Feature\Setup;

use App\Domain\Setup\Services\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_setup_step_renders_as_a_blade_page_with_the_existing_post_routes(): void
    {
        config(['stagetrack.assume_installed' => false]);

        $steps = [
            'requirements' => 'Server requirements',
            'database' => 'Database connection',
            'application' => 'Application settings',
            'install' => 'Install database',
            'admin' => 'Administrator account',
            'finish' => 'Finish setup',
        ];
        $completed = [];

        foreach ($steps as $key => $heading) {
            $response = $this->withSession(['setup.completed' => $completed])
                ->get(route('setup.index', ['step' => $key]));

            $response->assertOk()
                ->assertViewIs('setup.wizard')
                ->assertSee($heading);

            if ($key === 'requirements') {
                $response->assertSee('action="'.route('setup.requirements').'"', false);
            } elseif ($key === 'database') {
                $response->assertSee('action="'.route('setup.database').'"', false)
                    ->assertSee('formaction="'.route('setup.database.test').'"', false);
            } elseif ($key === 'application') {
                $response->assertSee('action="'.route('setup.application').'"', false);
            } elseif ($key === 'install') {
                $response->assertSee('action="'.route('setup.install').'"', false);
            } elseif ($key === 'admin') {
                $response->assertSee('action="'.route('setup.admin').'"', false);
            } else {
                $response->assertSee('action="'.route('setup.finish').'"', false);
            }

            $completed[] = $key;
        }
    }

    public function test_uncompleted_steps_cannot_be_submitted_directly(): void
    {
        config(['stagetrack.assume_installed' => false]);

        $this->post(route('setup.admin'), [
            'name' => 'Administrator',
            'username' => 'new-admin',
            'email' => 'admin@example.com',
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
        ])->assertRedirect(route('setup.index'))
            ->assertSessionHas('error', 'Complete the Install step first.');
    }

    public function test_testing_database_connection_preserves_non_secret_form_values_only(): void
    {
        config(['stagetrack.assume_installed' => false]);

        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('testConnection')
            ->once()
            ->andReturn('Connection refused');
        $this->app->instance(Installer::class, $installer);

        $response = $this->from(route('setup.index', ['step' => 'database']))
            ->post(route('setup.database.test'), [
                'driver' => 'sqlite',
                'database' => 'setup.sqlite',
                'password' => 'must-not-be-flashed',
            ]);

        $response->assertRedirect(route('setup.index', ['step' => 'database']))
            ->assertSessionHas('error', 'Connection failed: Connection refused')
            ->assertSessionHas('_old_input.database', 'setup.sqlite')
            ->assertSessionHas('_old_input.driver', 'sqlite')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_failed_database_save_does_not_flash_the_database_password(): void
    {
        config(['stagetrack.assume_installed' => false]);

        $installer = Mockery::mock(Installer::class);
        $installer->shouldReceive('testConnection')
            ->once()
            ->andReturn('Connection refused');
        $this->app->instance(Installer::class, $installer);

        $this->withSession(['setup.completed' => ['requirements']])
            ->from(route('setup.index', ['step' => 'database']))
            ->post(route('setup.database'), [
                'driver' => 'mysql',
                'host' => 'db.example.test',
                'port' => '3306',
                'database' => 'stagetrack',
                'username' => 'installer',
                'password' => 'must-not-be-flashed',
            ])
            ->assertRedirect(route('setup.index', ['step' => 'database']))
            ->assertSessionHasErrors(['database'])
            ->assertSessionHas('_old_input.host', 'db.example.test')
            ->assertSessionMissing('_old_input.password');
    }
}

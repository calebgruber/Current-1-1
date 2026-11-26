<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Jobs\Models\ProductionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_super_admin_sees_pipeline_and_upcoming_jobs(): void
    {
        ProductionJob::factory()->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(5)]);

        $this->actingAs($this->superAdmin())
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('upcoming', 1)
                ->whereNot('stageCounts', null));
    }

    public function test_sections_are_hidden_without_permission(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stageCounts', null)
                ->where('upcoming', null)
                ->where('assetCounts', null)
                ->where('maintenanceOpen', null));
    }
}

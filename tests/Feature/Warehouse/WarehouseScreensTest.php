<?php

namespace Tests\Feature\Warehouse;

use App\Domain\Jobs\Models\ProductionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_stations_accept_a_job_or_no_job_and_enforce_permissions(): void
    {
        $job = ProductionJob::factory()->create(['stage' => 'reserved']);
        $user = $this->userWithPermissions(['warehouse.picking.view']);

        $this->actingAs($user)->get('/warehouse/picking')
            ->assertOk()->assertViewIs('warehouse.station')->assertViewHas('job', null);
        $this->actingAs($user)->get("/warehouse/picking/{$job->id}")
            ->assertOk()->assertViewIs('warehouse.station')->assertViewHas('job.id', $job->id);
        $this->actingAs($user)->get('/warehouse/picking/999999')->assertNotFound();
        $this->actingAs($user)->get("/warehouse/prep/{$job->id}")->assertForbidden();
    }

    public function test_job_number_can_be_scanned_before_selecting_a_job(): void
    {
        $job = ProductionJob::factory()->create(['stage' => 'reserved']);
        $user = $this->userWithPermissions(['warehouse.picking.edit']);

        $this->actingAs($user)->postJson('/warehouse/scan', ['mode' => 'pick', 'code' => $job->number])
            ->assertOk()->assertJsonPath('result.level', 'success')->assertJsonPath('select_job.id', $job->id);
        $this->actingAs($user)->postJson('/warehouse/scan', ['mode' => 'pick', 'code' => 'unknown'])
            ->assertOk()->assertJsonPath('result.level', 'error')->assertJsonPath('result.title', 'Choose a job');
    }

    public function test_display_requires_permission_and_accepts_a_selected_job(): void
    {
        $job = ProductionJob::factory()->create(['stage' => 'reserved']);
        $user = $this->userWithPermissions(['warehouse.display.view']);
        $this->actingAs($user)->get("/warehouse/display/{$job->id}?mode=damage")
            ->assertOk()->assertViewIs('warehouse.display')->assertViewHas('job.id', $job->id)->assertViewHas('initialMode', 'pick');
        $this->actingAs($this->userWithPermissions([]))->get('/warehouse/display')->assertForbidden();
    }
}

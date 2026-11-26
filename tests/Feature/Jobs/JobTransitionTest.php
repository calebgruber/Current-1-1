<?php

namespace Tests\Feature\Jobs;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Jobs\Services\JobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_transition_is_applied_and_recorded(): void
    {
        $job = ProductionJob::factory()->create(['stage' => 'draft']);

        $this->actingAs($this->userWithPermissions(['jobs.jobs.view', 'jobs.stages.edit']))
            ->post("/jobs/{$job->id}/stage", ['stage' => 'quote'])
            ->assertSessionHasNoErrors();

        $this->assertSame('quote', $job->fresh()->stage->value);
        $this->assertDatabaseHas('job_stage_history', ['job_id' => $job->id, 'from_stage' => 'draft', 'to_stage' => 'quote']);
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $job = ProductionJob::factory()->create(['stage' => 'draft']);

        $this->actingAs($this->superAdmin())
            ->from("/jobs/{$job->id}")
            ->post("/jobs/{$job->id}/stage", ['stage' => 'checked_out'])
            ->assertRedirect("/jobs/{$job->id}")
            ->assertSessionHasErrors('stage');
        $this->assertSame('draft', $job->fresh()->stage->value);

        $this->actingAs($this->superAdmin())
            ->postJson("/jobs/{$job->id}/stage", ['stage' => 'closed'])
            ->assertStatus(422);
    }

    public function test_approval_requires_approve_permission(): void
    {
        $job = ProductionJob::factory()->create(['stage' => 'quote']);

        $this->actingAs($this->userWithPermissions(['jobs.jobs.view', 'jobs.stages.edit']))
            ->post("/jobs/{$job->id}/stage", ['stage' => 'approved'])
            ->assertForbidden();
        $this->assertSame('quote', $job->fresh()->stage->value);

        $this->actingAs($this->userWithPermissions(['jobs.jobs.view', 'jobs.stages.edit', 'jobs.jobs.approve']))
            ->post("/jobs/{$job->id}/stage", ['stage' => 'approved'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $job->fresh()->stage->value);
    }

    public function test_transition_requires_edit_permission(): void
    {
        $job = ProductionJob::factory()->create(['stage' => 'draft']);
        $this->actingAs($this->userWithPermissions(['jobs.jobs.view']))
            ->post("/jobs/{$job->id}/stage", ['stage' => 'quote'])
            ->assertForbidden();
    }

    public function test_reserving_an_overbooked_job_is_blocked_unless_overridden(): void
    {
        $product = Product::factory()->bulk()->create();
        StockLevel::query()->create(['product_id' => $product->id, 'quantity' => 10]);
        $job = ProductionJob::factory()->create(['stage' => 'approved']);
        app(JobService::class)->addItem($job, $product, 12, 0);

        $this->actingAs($this->superAdmin())
            ->postJson("/jobs/{$job->id}/stage", ['stage' => 'reserved'])
            ->assertStatus(422)
            ->assertJsonPath('conflicts.0.shortage', 2);
        $this->assertSame('approved', $job->fresh()->stage->value);

        $approver = $this->userWithPermissions(['jobs.jobs.view', 'jobs.stages.edit', 'jobs.jobs.approve']);
        $this->actingAs($approver)
            ->postJson("/jobs/{$job->id}/stage", ['stage' => 'reserved', 'override' => true, 'override_reason' => 'Hiring in two more'])
            ->assertForbidden();

        $approver2 = $this->userWithPermissions(['jobs.jobs.view', 'jobs.stages.edit', 'jobs.jobs.approve', 'reservations.overbooking.approve']);
        $this->actingAs($approver2)
            ->postJson("/jobs/{$job->id}/stage", ['stage' => 'reserved', 'override' => true, 'override_reason' => 'Hiring in two more'])
            ->assertOk();
        $this->assertSame('reserved', $job->fresh()->stage->value);
    }
}

<?php

namespace Tests\Feature\Jobs;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Jobs\Models\ProductionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_jobs_and_filters_by_stage(): void
    {
        ProductionJob::factory()->create(['name' => 'Hamlet', 'stage' => 'draft']);
        ProductionJob::factory()->create(['name' => 'Macbeth', 'stage' => 'quote']);

        $this->actingAs($this->userWithPermissions(['jobs.jobs.view']))
            ->get('/jobs?stage=quote')
            ->assertOk()
            ->assertViewIs('jobs.index')
            ->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 1 && $jobs->items()[0]['name'] === 'Macbeth')
            ->assertSee('Macbeth');
    }

    public function test_board_view_groups_jobs_by_stage(): void
    {
        ProductionJob::factory()->create(['stage' => 'draft']);
        ProductionJob::factory()->create(['stage' => 'prep']);

        $this->actingAs($this->superAdmin())
            ->get('/jobs?view=board')
            ->assertOk()
            ->assertViewIs('jobs.index')
            ->assertViewHas('view', 'board')
            ->assertViewHas('board', fn ($board) => count($board['draft']) === 1 && count($board['prep']) === 1 && count($board['quote']) === 0);
    }

    public function test_index_requires_permission(): void
    {
        $this->actingAs($this->userWithPermissions([]))->get('/jobs')->assertForbidden();
    }

    public function test_create_store_show_update_and_delete(): void
    {
        $user = $this->userWithPermissions(['jobs.jobs.view', 'jobs.jobs.create', 'jobs.jobs.edit', 'jobs.jobs.delete']);
        $contact = Contact::query()->create(['name' => 'Stage Manager', 'type' => 'person']);

        $this->actingAs($user)->get('/jobs/create')->assertOk()->assertViewIs('jobs.form')->assertSee('New job');

        $response = $this->actingAs($user)->post('/jobs', [
            'name' => 'The Tempest',
            'client_contact_id' => $contact->id,
            'prep_starts_at' => '2030-03-01 09:00',
            'starts_at' => '2030-03-03 19:00',
            'ends_at' => '2030-03-05 23:00',
            'returns_at' => '2030-03-06 12:00',
            'contacts' => [['contact_id' => $contact->id, 'role' => 'Stage manager']],
        ]);
        $job = ProductionJob::query()->where('name', 'The Tempest')->firstOrFail();
        $response->assertRedirect("/jobs/{$job->id}");
        $this->assertSame('inquiry', $job->stage->value);
        $this->assertSame('Stage manager', $job->contacts()->first()->pivot->role);
        $this->assertMatchesRegularExpression('/^J-\d{5}$/', $job->number);

        $this->actingAs($user)->get("/jobs/{$job->id}")->assertOk()
            ->assertViewIs('jobs.show')
            ->assertViewHas('job', fn ($shownJob) => $shownJob['number'] === $job->number && count($shownJob['allowed_transitions']) === 3)
            ->assertViewHas('contacts', fn ($contacts) => count($contacts) === 1)
            ->assertSee($job->number);

        $this->actingAs($user)->put("/jobs/{$job->id}", ['name' => 'The Tempest (tour)', 'starts_at' => '2030-03-03 19:00', 'ends_at' => '2030-03-02 19:00'])
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($user)->put("/jobs/{$job->id}", ['name' => 'The Tempest (tour)'])->assertRedirect("/jobs/{$job->id}");
        $this->assertSame('The Tempest (tour)', $job->fresh()->name);
        $this->assertSame('Stage manager', $job->fresh()->contacts()->first()->pivot->role);

        $this->actingAs($user)->delete("/jobs/{$job->id}")->assertRedirect('/jobs');
        $this->assertSoftDeleted($job);
    }

    public function test_store_requires_create_permission_and_name(): void
    {
        $this->actingAs($this->userWithPermissions(['jobs.jobs.view']))->post('/jobs', ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->superAdmin())->post('/jobs', [])->assertSessionHasErrors('name');
    }

    public function test_checked_out_job_cannot_be_deleted(): void
    {
        $job = ProductionJob::factory()->create(['stage' => 'checked_out']);
        $this->actingAs($this->superAdmin())->delete("/jobs/{$job->id}")->assertRedirect();
        $this->assertNotSoftDeleted($job);
    }

    public function test_attachments_whitelist_and_authorized_download(): void
    {
        Storage::fake('local');
        $job = ProductionJob::factory()->create();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post("/jobs/{$job->id}/attachments", ['files' => [UploadedFile::fake()->create('evil.php', 10, 'application/x-php')]])
            ->assertSessionHasErrors('files.0');

        $this->actingAs($admin)->post("/jobs/{$job->id}/attachments", ['files' => [UploadedFile::fake()->create('rider.pdf', 20, 'application/pdf')]])
            ->assertSessionHasNoErrors();
        $attachment = $job->attachments()->firstOrFail();
        $this->assertStringStartsWith("attachments/jobs/{$job->id}/", $attachment->path);

        $this->actingAs($admin)->get("/jobs/{$job->id}/attachments/{$attachment->id}")->assertOk();
        $this->actingAs($this->userWithPermissions(['jobs.jobs.view']))->get("/jobs/{$job->id}/attachments/{$attachment->id}")->assertForbidden();

        $other = ProductionJob::factory()->create();
        $this->actingAs($admin)->get("/jobs/{$other->id}/attachments/{$attachment->id}")->assertNotFound();
    }

    public function test_notes_can_be_added(): void
    {
        $job = ProductionJob::factory()->create();
        $this->actingAs($this->superAdmin())->post("/jobs/{$job->id}/notes", ['body' => 'Load in via stage door'])->assertSessionHasNoErrors();
        $this->assertSame('Load in via stage door', $job->notes()->first()->body);
    }

    public function test_lookup_returns_products(): void
    {
        \App\Domain\Inventory\Models\Product::factory()->create(['name' => 'Lustr Series 3']);
        $this->actingAs($this->userWithPermissions(['jobs.jobs.view']))
            ->getJson('/jobs/lookup/products?q=Lustr')
            ->assertOk()
            ->assertJsonPath('0.label', 'Lustr Series 3');
        $this->actingAs($this->userWithPermissions([]))->getJson('/jobs/lookup/products')->assertForbidden();
    }
}

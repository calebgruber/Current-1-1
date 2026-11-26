<?php

namespace Tests\Feature\CustomFields;

use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\Jobs\Models\ProductionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_fields_are_validated_saved_and_exposed_on_create_edit_and_detail(): void
    {
        CustomFieldDefinition::create(['entity_type' => 'job', 'key' => 'reference', 'label' => 'Reference', 'type' => 'text', 'is_required' => true]);
        CustomFieldDefinition::create(['entity_type' => 'job', 'key' => 'kind', 'label' => 'Kind', 'type' => 'dropdown', 'options' => ['Tour', 'Local']]);
        $user = $this->userWithPermissions(['jobs.jobs.view', 'jobs.jobs.create', 'jobs.jobs.edit']);

        $this->actingAs($user)->get('/jobs/create')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Jobs/Form')->where('customFieldSchema.0.key', 'reference')->where('customFields', []));
        $this->post('/jobs', ['name' => 'Show', 'custom_fields' => ['kind' => 'Invalid']])
            ->assertSessionHasErrors(['custom_fields.reference', 'custom_fields.kind']);
        $this->post('/jobs', ['name' => 'Show', 'custom_fields' => ['reference' => 'R1', 'kind' => 'Tour']])->assertSessionHasNoErrors();
        $job = ProductionJob::query()->where('name', 'Show')->firstOrFail();

        $this->get("/jobs/{$job->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Jobs/Show')->where('customFieldSchema.0.label', 'Reference')->where('customFields.reference', 'R1')->where('customFields.kind', 'Tour'));
        $this->get("/jobs/{$job->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Jobs/Form')->where('customFields.reference', 'R1')->where('customFields.kind', 'Tour'));
        $this->put("/jobs/{$job->id}", ['name' => 'Show', 'custom_fields' => ['reference' => '', 'kind' => 'Local']])
            ->assertSessionHasErrors('custom_fields.reference');
        $this->assertSame('R1', $job->fresh()->customFields()['reference']);
        $this->put("/jobs/{$job->id}", ['name' => 'Show', 'custom_fields' => ['reference' => 'R2', 'kind' => 'Local']])
            ->assertSessionHasNoErrors();
        $this->assertSame(['reference' => 'R2', 'kind' => 'Local'], $job->fresh()->customFields());
        $this->actingAs($this->userWithPermissions([]))->get("/jobs/{$job->id}")->assertForbidden();
    }

    public function test_job_field_management_requires_permissions_and_validates_definitions(): void
    {
        $viewer = $this->userWithPermissions(['settings.custom_fields.view']);
        $creator = $this->userWithPermissions(['settings.custom_fields.view', 'settings.custom_fields.create']);
        $payload = ['key' => 'region', 'label' => 'Region', 'type' => 'dropdown', 'is_required' => false, 'options' => ['North', 'South']];

        $this->actingAs($this->userWithPermissions([]))->get('/settings/custom-fields')->assertForbidden();
        $this->actingAs($viewer)->get('/settings/custom-fields')->assertOk()->assertInertia(fn (Assert $p) => $p->component('CustomFields/Index')->has('fields', 0));
        $this->post('/settings/custom-fields', $payload)->assertForbidden();
        $this->actingAs($creator)->post('/settings/custom-fields', $payload)->assertSessionHasNoErrors()->assertRedirect('/settings/custom-fields');
        $this->assertDatabaseHas('custom_field_definitions', ['entity_type' => 'job', 'key' => 'region', 'type' => 'dropdown']);
        $this->post('/settings/custom-fields', $payload)->assertSessionHasErrors('key');
        $this->post('/settings/custom-fields', ['key' => 'Bad key', 'label' => 'X', 'type' => 'dropdown', 'is_required' => false, 'options' => []])
            ->assertSessionHasErrors(['key', 'options']);
        $this->post('/settings/custom-fields', ['key' => 'other', 'label' => 'X', 'type' => 'unsupported', 'is_required' => false])
            ->assertSessionHasErrors('type');
        $this->assertDatabaseCount('custom_field_definitions', 1);
    }

    public function test_job_update_treats_custom_fields_as_a_partial_map(): void
    {
        CustomFieldDefinition::create(['entity_type' => 'job', 'key' => 'reference', 'label' => 'Reference', 'type' => 'text', 'is_required' => true]);
        CustomFieldDefinition::create(['entity_type' => 'job', 'key' => 'note', 'label' => 'Note', 'type' => 'text']);
        $job = ProductionJob::factory()->create();
        $job->setCustomFields(['reference' => 'R1', 'note' => 'Original']);
        $this->actingAs($this->userWithPermissions(['jobs.jobs.edit']));

        $this->put("/jobs/{$job->id}", ['name' => 'Updated'])->assertSessionHasNoErrors();
        $this->assertSame('R1', $job->fresh()->customFields()['reference']);
        $this->assertSame('Original', $job->fresh()->customFields()['note']);
        $this->put("/jobs/{$job->id}", ['name' => 'Updated', 'custom_fields' => ['note' => 'Changed']])->assertSessionHasNoErrors();
        $this->assertSame('R1', $job->fresh()->customFields()['reference']);
        $this->assertSame('Changed', $job->fresh()->customFields()['note']);
        $this->put("/jobs/{$job->id}", ['name' => 'Updated', 'custom_fields' => ['reference' => null]])->assertSessionHasErrors('custom_fields.reference');
        $this->assertSame('R1', $job->fresh()->customFields()['reference']);
        $this->put("/jobs/{$job->id}", ['name' => 'Updated', 'custom_fields' => ['note' => null]])->assertSessionHasNoErrors();
        $this->assertNull($job->fresh()->customFields()['note']);

        CustomFieldDefinition::create(['entity_type' => 'job', 'key' => 'new_required', 'label' => 'New required', 'type' => 'text', 'is_required' => true]);
        $this->put("/jobs/{$job->id}", ['name' => 'Updated again'])->assertSessionHasErrors('custom_fields.new_required');
        $this->assertSame('Updated', $job->fresh()->name);
        $this->put("/jobs/{$job->id}", ['name' => 'Updated again', 'custom_fields' => ['new_required' => 'Present']])->assertSessionHasNoErrors();
        $this->assertSame('R1', $job->fresh()->customFields()['reference']);
        $this->assertSame('Present', $job->fresh()->customFields()['new_required']);
    }

    public function test_numeric_job_field_values_are_exposed_as_decimal_strings(): void
    {
        CustomFieldDefinition::create(['entity_type' => 'job', 'key' => 'amount', 'label' => 'Amount', 'type' => 'number']);
        $this->actingAs($this->userWithPermissions(['jobs.jobs.create', 'jobs.jobs.edit', 'jobs.jobs.view']))
            ->post('/jobs', ['name' => 'Precision', 'custom_fields' => ['amount' => '99999999999999.9999']])->assertSessionHasNoErrors();
        $job = ProductionJob::query()->where('name', 'Precision')->firstOrFail();
        $this->get("/jobs/{$job->id}/edit")->assertInertia(fn (Assert $p) => $p->where('customFields.amount', '99999999999999.9999'));
        $this->put("/jobs/{$job->id}", ['name' => 'Precision', 'custom_fields' => ['amount' => '99999999999999.9999']])->assertSessionHasNoErrors();
        $this->get("/jobs/{$job->id}")->assertInertia(fn (Assert $p) => $p->where('customFields.amount', '99999999999999.9999'));
    }

    public function test_editing_a_job_field_changes_only_label_and_help_text(): void
    {
        $field = CustomFieldDefinition::create([
            'entity_type' => 'job', 'key' => 'region', 'label' => 'Region', 'type' => 'dropdown',
            'options' => ['North', 'South'], 'help_text' => 'Choose a region', 'is_required' => true,
        ]);
        $other = CustomFieldDefinition::create(['entity_type' => 'contact', 'key' => 'region', 'label' => 'Region', 'type' => 'text']);
        $job = ProductionJob::factory()->create();
        $job->setCustomFields(['region' => 'North']);
        $url = "/settings/custom-fields/{$field->id}";
        $creator = $this->userWithPermissions(['settings.custom_fields.view', 'settings.custom_fields.create']);
        $editor = $this->userWithPermissions(['settings.custom_fields.view', 'settings.custom_fields.edit']);
        $payload = ['label' => 'Area', 'help_text' => 'Choose an area', 'key' => 'changed', 'type' => 'text', 'options' => ['Other'], 'is_required' => false];

        $this->actingAs($creator)->put($url, $payload)->assertForbidden();
        $this->actingAs($editor)->post('/settings/custom-fields', ['key' => 'x', 'label' => 'X', 'type' => 'text', 'is_required' => false])->assertForbidden();
        $this->put($url, ['label' => '', 'help_text' => str_repeat('x', 256)])->assertSessionHasErrors(['label', 'help_text']);
        $this->put("/settings/custom-fields/{$other->id}", $payload)->assertNotFound();
        $this->put($url, $payload)->assertSessionHasNoErrors()->assertRedirect('/settings/custom-fields');

        $field->refresh();
        $this->assertSame('Area', $field->label);
        $this->assertSame('Choose an area', $field->help_text);
        $this->assertSame('region', $field->key);
        $this->assertSame('dropdown', $field->type->value);
        $this->assertSame(['North', 'South'], $field->options);
        $this->assertTrue($field->is_required);
        $this->assertSame('North', $job->fresh()->customFields()['region']);
        $this->actingAs($this->userWithPermissions(['jobs.jobs.view']))->get("/jobs/{$job->id}")
            ->assertInertia(fn (Assert $p) => $p->where('customFieldSchema.0.label', 'Area')
                ->where('customFieldSchema.0.help_text', 'Choose an area')->where('customFields.region', 'North'));
    }
}

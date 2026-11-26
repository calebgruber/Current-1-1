<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Jobs\Models\ProductionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_and_template_screens_resolve(): void
    {
        $user = $this->superAdmin();
        $job = ProductionJob::factory()->create();
        $template = DocumentTemplate::create(['name' => 'Quote', 'type' => 'quotation', 'entity_type' => 'job', 'editor' => 'code']);
        $document = GeneratedDocument::create(['type' => 'quotation', 'documentable_type' => 'job', 'documentable_id' => $job->id, 'title' => 'Quote', 'html' => '<p>Quote</p>', 'template_id' => $template->id]);

        $this->actingAs($user)->get('/documents')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Documents/Index')->has('documents.data', 1));
        $this->get("/documents/{$document->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Documents/Show')->where('html', '<p>Quote</p>'));
        $this->get('/documents/templates')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Documents/Templates/Index'));
        $this->get('/documents/templates/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Documents/Templates/Designer'));
        $this->get("/documents/templates/{$template->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Documents/Templates/Designer'));
    }

    public function test_changing_a_default_template_type_requires_admin_and_replaces_new_type_default(): void
    {
        $old = DocumentTemplate::create(['name' => 'Old', 'type' => 'quotation', 'entity_type' => 'job', 'editor' => 'code', 'is_default' => true]);
        $other = DocumentTemplate::create(['name' => 'Other', 'type' => 'invoice', 'entity_type' => 'job', 'editor' => 'code', 'is_default' => true]);
        $data = ['name' => 'Old', 'type' => 'invoice', 'entity_type' => 'job', 'editor' => 'code', 'html' => 'Hello', 'paper_size' => 'letter', 'orientation' => 'portrait', 'is_default' => true];

        $this->actingAs($this->userWithPermissions(['documents.templates.edit']))->put("/documents/templates/{$old->id}", $data)->assertSessionHasErrors('type');
        $this->assertSame('quotation', $old->fresh()->type);
        $this->actingAs($this->superAdmin())->put("/documents/templates/{$old->id}", $data)->assertSessionHasNoErrors();
        $this->assertTrue($old->fresh()->is_default);
        $this->assertFalse($other->fresh()->is_default);
    }
}

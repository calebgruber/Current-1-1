<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Jobs\Models\ProductionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($user)->get('/documents')->assertOk()->assertViewIs('documents.index')->assertSee($document->number);
        $this->get("/documents/{$document->id}")->assertOk()->assertViewIs('documents.show')->assertSee('Document preview');
        $this->get('/documents/templates')->assertOk()->assertViewIs('documents.templates.index')->assertSee('Quote');
        $this->get('/documents/templates/create')->assertOk()->assertViewIs('documents.templates.designer')->assertSee('New template');
        $this->get("/documents/templates/{$template->id}/edit")->assertOk()->assertViewIs('documents.templates.designer')->assertSee('Edit template');
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

    public function test_designer_preview_compile_and_save_preserve_nested_blocks_and_page_settings(): void
    {
        $this->actingAs($this->superAdmin());
        $blocks = [['id' => 'outer', 'type' => 'columns', 'props' => ['columns' => [
            ['width' => '35%', 'blocks' => [['id' => 'title', 'type' => 'heading', 'props' => ['text' => 'Nested title']]]],
            ['width' => '65%', 'blocks' => [['id' => 'condition', 'type' => 'conditional', 'props' => [
                'condition' => 'true', 'blocks' => [['id' => 'text', 'type' => 'text', 'props' => ['html' => '<p>Nested content</p>']]],
            ]]]],
        ]]]];
        $draft = [
            'name' => 'Example', 'type' => 'quotation', 'entity_type' => 'job', 'editor' => 'visual',
            'blocks' => $blocks, 'paper_size' => 'a4', 'orientation' => 'landscape',
            'margins' => ['top' => 10, 'right' => 20, 'bottom' => 30, 'left' => 40],
            'watermark' => '<script>alert(1)</script>',
        ];
        $this->postJson('/documents/templates/compile', ['blocks' => $blocks])
            ->assertOk()->assertJsonPath('error', null)->assertJsonFragment(['error' => null])
            ->assertSee('width:35%', false);
        $this->postJson('/documents/templates/preview', $draft)
            ->assertOk()->assertJsonPath('error', null)
            ->assertSee('Nested title')->assertSee('width:35%')
            ->assertSee('&lt;script&gt;', false);
        $this->post('/documents/templates', $draft)->assertSessionHasNoErrors();
        $template = DocumentTemplate::where('name', 'Example')->firstOrFail();
        $this->assertSame('35%', $template->blocks[0]['props']['columns'][0]['width']);
        $this->assertSame('Nested content', strip_tags($template->blocks[0]['props']['columns'][1]['blocks'][0]['props']['blocks'][0]['props']['html']));
        $this->assertEquals($draft['margins'], $template->margins);
        $this->assertSame('landscape', $template->orientation);
    }

    public function test_admin_can_change_default_template_type_without_replacing_destination_default(): void
    {
        $old = DocumentTemplate::create(['name' => 'Old', 'type' => 'quotation', 'entity_type' => 'job', 'editor' => 'code', 'is_default' => true]);
        $other = DocumentTemplate::create(['name' => 'Other', 'type' => 'invoice', 'entity_type' => 'job', 'editor' => 'code', 'is_default' => true]);
        $this->actingAs($this->superAdmin())->put("/documents/templates/{$old->id}", [
            'name' => 'Old', 'type' => 'invoice', 'entity_type' => 'job', 'editor' => 'code',
            'html' => 'Hello', 'paper_size' => 'letter', 'orientation' => 'portrait', 'is_default' => false,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($old->fresh()->is_default);
        $this->assertTrue($other->fresh()->is_default);
    }

    public function test_preview_and_compile_require_template_view_permission(): void
    {
        $this->actingAs($this->userWithPermissions(['documents.templates.create']));
        $this->postJson('/documents/templates/compile', ['blocks' => []])->assertForbidden();
        $this->postJson('/documents/templates/preview', [
            'type' => 'quotation', 'editor' => 'visual', 'paper_size' => 'letter', 'orientation' => 'portrait',
        ])->assertForbidden();
    }
}

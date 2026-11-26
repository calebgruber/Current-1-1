<?php

namespace Tests\Feature\CustomFields;

use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Inventory\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomFieldServiceTest extends TestCase
{
    use RefreshDatabase;

    private function define(array $attributes): CustomFieldDefinition
    {
        return CustomFieldDefinition::create(array_merge([
            'entity_type' => 'product',
            'show_in_list' => false,
            'is_required' => false,
        ], $attributes));
    }

    public function test_validates_saves_and_reads_typed_values(): void
    {
        $this->define(['key' => 'wattage', 'label' => 'Wattage', 'type' => 'number', 'is_required' => true, 'show_in_list' => true]);
        $this->define(['key' => 'dmx_mode', 'label' => 'DMX mode', 'type' => 'dropdown', 'options' => ['Basic', 'Extended']]);
        $product = Product::factory()->create();
        $service = app(CustomFieldService::class);

        $clean = $service->validate('product', ['wattage' => '450', 'dmx_mode' => 'Extended']);
        $service->save($product, $clean);

        $this->assertEquals(450, $product->fresh()->customFields()['wattage']);
        $this->assertSame('Extended', $product->fresh()->customFields()['dmx_mode']);
        $this->assertSame([$product->id => ['wattage' => 450.0, 'dmx_mode' => 'Extended']], array_map(
            fn ($v) => ['wattage' => (float) $v['wattage'], 'dmx_mode' => $v['dmx_mode']],
            $service->valuesForMany('product', [$product->id])
        ));
        $this->assertCount(1, $service->listColumns('product'));
    }

    public function test_errors_are_keyed_under_custom_fields(): void
    {
        $this->define(['key' => 'wattage', 'label' => 'Wattage', 'type' => 'number', 'is_required' => true]);
        $this->define(['key' => 'dmx_mode', 'label' => 'DMX mode', 'type' => 'dropdown', 'options' => ['Basic']]);

        try {
            app(CustomFieldService::class)->validate('product', ['dmx_mode' => 'Nope']);
            $this->fail('Expected validation exception');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('custom_fields.wattage', $e->errors());
            $this->assertArrayHasKey('custom_fields.dmx_mode', $e->errors());
        }
    }

    public function test_file_values_must_live_in_custom_field_directory(): void
    {
        $this->define(['key' => 'manual', 'label' => 'Manual', 'type' => 'file']);

        $this->expectException(ValidationException::class);
        app(CustomFieldService::class)->validate('product', ['manual' => ['path' => '../.env', 'name' => 'x']]);
    }

    public function test_upload_and_download_are_authorized(): void
    {
        Storage::fake('local');
        $this->define(['key' => 'manual', 'label' => 'Manual', 'type' => 'file']);
        $editor = $this->userWithPermissions(['inventory.products.view', 'inventory.products.edit']);

        $file = $this->actingAs($editor)
            ->post(route('custom-fields.upload'), ['file' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf')])
            ->assertOk()
            ->json();
        $this->assertStringStartsWith('custom-fields/', $file['path']);

        $product = Product::factory()->create();
        $service = app(CustomFieldService::class);
        $service->save($product, $service->validate('product', ['manual' => $file]));

        $this->actingAs($editor)->get(route('custom-fields.download', ['path' => $file['path']]))->assertOk();
        $this->actingAs($this->userWithPermissions([]))->get(route('custom-fields.download', ['path' => $file['path']]))->assertForbidden();
        $this->actingAs($editor)->get(route('custom-fields.download', ['path' => 'custom-fields/other.pdf']))->assertNotFound();
    }

    public function test_lookup_returns_options(): void
    {
        $user = $this->userWithPermissions(['inventory.products.view']);
        $user->update(['name' => 'Stage Manager Sam']);

        $this->actingAs($user)
            ->getJson(route('custom-fields.lookup', ['type' => 'user', 'q' => 'Sam']))
            ->assertOk()
            ->assertJsonFragment(['value' => $user->id]);
    }
}

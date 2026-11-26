<?php

namespace Tests\Feature\Rules;

use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\Inventory\Models\Product;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Rules\Models\ValidationRule;
use App\Domain\Rules\Services\JobValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RuleScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_screens_and_draft_test_without_name(): void
    {
        $user = $this->superAdmin();
        $job = ProductionJob::factory()->create();
        $definition = ['scope' => ['match' => 'any', 'values' => []], 'check' => 'max_quantity', 'requirement' => ['quantity' => 2]];
        $this->actingAs($user)->get('/rules')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Rules/Index'));
        $this->get('/rules/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Rules/Builder'));
        $this->post('/rules', ['name' => 'Limit', 'severity' => 'warning', 'definition' => $definition])->assertSessionHasNoErrors();
        $rule = ValidationRule::where('name', 'Limit')->firstOrFail();
        $this->get("/rules/{$rule->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Rules/Builder'));
        $this->postJson('/rules/test', ['job_id' => $job->id, 'definition' => $definition])->assertOk()->assertJsonPath('passed', false);
    }

    public function test_negative_limits_are_rejected_in_saved_and_draft_rules(): void
    {
        $definition = ['scope' => ['match' => 'any'], 'check' => 'max_quantity', 'requirement' => ['quantity' => -1]];
        $job = ProductionJob::factory()->create();
        $this->actingAs($this->superAdmin())->post('/rules', ['name' => 'Limit', 'severity' => 'warning', 'definition' => $definition])->assertSessionHasErrors('definition');
        $this->postJson('/rules/test', ['job_id' => $job->id, 'definition' => $definition])->assertUnprocessable()->assertJsonValidationErrors('definition');
    }

    public function test_job_rules_use_product_custom_field_types_for_decimal_matching(): void
    {
        CustomFieldDefinition::create(['entity_type' => 'product', 'key' => 'power', 'label' => 'Power', 'type' => 'number']);
        CustomFieldDefinition::create(['entity_type' => 'product', 'key' => 'code', 'label' => 'Code', 'type' => 'text']);
        $product = Product::factory()->create();
        $product->setCustomFields(['power' => '1.0000', 'code' => '1.0000']);
        $job = ProductionJob::factory()->create();
        $job->items()->create(['product_id' => $product->id, 'required_quantity' => 1, 'spare_quantity' => 0]);
        $definition = [
            'scope' => ['match' => 'custom_field', 'field' => 'power', 'operator' => 'is', 'values' => ['1']],
            'check' => 'max_quantity', 'requirement' => ['quantity' => 0],
        ];

        $findings = app(JobValidationService::class)->validate($job, [['name' => 'Limit', 'definition' => $definition]]);
        $this->assertCount(1, $findings);
        $this->actingAs($this->superAdmin())->postJson('/rules/test', ['job_id' => $job->id, 'definition' => $definition])
            ->assertOk()->assertJsonCount(1, 'matched')->assertJsonCount(1, 'findings');
        $definition['scope']['field'] = 'code';
        $this->postJson('/rules/test', ['job_id' => $job->id, 'definition' => $definition])
            ->assertOk()->assertJsonCount(0, 'matched');
    }
}

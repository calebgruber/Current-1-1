<?php

namespace Tests\Feature\Rules;

use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Rules\Models\ValidationRule;
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
}

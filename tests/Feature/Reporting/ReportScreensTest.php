<?php

namespace Tests\Feature\Reporting;

use App\Domain\Reporting\Models\Report;
use App\Domain\Reporting\Services\ReportSourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_create_run_and_edit_screens(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->get('/reports')->assertOk()->assertViewIs('reports.index');
        $this->get('/reports/create')->assertOk()->assertViewIs('reports.builder');
        $this->post('/reports', ['name' => 'Jobs', 'source' => 'jobs', 'definition' => ['columns' => ['number', 'name']]])->assertSessionHasNoErrors();
        $report = Report::where('name', 'Jobs')->firstOrFail();
        $this->get("/reports/{$report->id}")->assertOk()->assertViewIs('reports.show')->assertViewHas('result.columns', fn ($columns) => count($columns) === 2);
        $this->get("/reports/{$report->id}/edit")->assertOk()->assertViewIs('reports.builder');
    }

    public function test_schedules_require_access_to_the_report_source(): void
    {
        $owner = $this->superAdmin();
        $report = Report::create(['name' => 'Jobs', 'source' => 'jobs', 'definition' => ['columns' => ['name']], 'is_shared' => true, 'created_by' => $owner->id]);
        $user = $this->userWithPermissions(['reporting.reports.view', 'reporting.reports.export', 'reporting.schedules.view', 'reporting.schedules.create']);
        $this->actingAs($user)->get("/reports/{$report->id}/schedules")->assertForbidden();
        $this->post("/reports/{$report->id}/schedules", ['frequency' => 'daily', 'format' => 'csv', 'hour' => 8, 'recipients' => ['person@example.com']])->assertForbidden();
        $this->assertDatabaseCount('report_schedules', 0);
    }

    public function test_all_catalogue_columns_can_be_saved_and_shown(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (app(ReportSourceRegistry::class)->catalogue() as $source) {
            $columns = array_column($source['columns'], 'key');
            $this->get('/reports/create?source='.$source['key'])
                ->assertOk()
                ->assertViewIs('reports.builder')
                ->assertViewHas('report.source', $source['key']);
            $this->post('/reports', [
                'name' => $source['label'], 'source' => $source['key'], 'definition' => ['columns' => $columns],
            ])->assertSessionHasNoErrors();
            $report = Report::where('source', $source['key'])->firstOrFail();
            $this->get("/reports/{$report->id}")
                ->assertOk()
                ->assertViewIs('reports.show')
                ->assertViewHas('result.columns', fn ($resultColumns) => count($resultColumns) === count($columns));
        }
    }

    public function test_create_with_no_permitted_sources_uses_null_source(): void
    {
        $this->actingAs($this->userWithPermissions(['reporting.reports.create']))
            ->get('/reports/create')
            ->assertOk()
            ->assertViewIs('reports.builder')
            ->assertViewHas('report.source', null)
            ->assertViewHas('sources', []);
    }
}

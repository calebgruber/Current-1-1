<?php

namespace Tests\Feature\Reporting;

use App\Domain\Reporting\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_create_run_and_edit_screens(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->get('/reports')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Reports/Index'));
        $this->get('/reports/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Reports/Builder'));
        $this->post('/reports', ['name' => 'Jobs', 'source' => 'jobs', 'definition' => ['columns' => ['number', 'name']]])->assertSessionHasNoErrors();
        $report = Report::where('name', 'Jobs')->firstOrFail();
        $this->get("/reports/{$report->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Reports/Show')->has('result.columns', 2));
        $this->get("/reports/{$report->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Reports/Builder'));
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
}

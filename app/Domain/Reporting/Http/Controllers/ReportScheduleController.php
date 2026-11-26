<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Models\Report;
use App\Domain\Reporting\Models\ReportSchedule;
use App\Domain\Reporting\Services\ReportExporter;
use App\Domain\Reporting\Services\ReportSourceRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportScheduleController extends Controller
{
    public function index(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report, 'view');

        return response()->json($report->schedules()->orderBy('id')->get()->map(fn ($s) => self::scheduleArray($s))->values());
    }

    public function store(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($request, $report, 'create');
        $schedule = new ReportSchedule($this->validated($request) + ['report_id' => $report->id]);
        $schedule->next_run_at = $schedule->computeNextRun();
        $schedule->save();

        return back()->with('success', 'Schedule created. Next delivery '.$schedule->next_run_at->format('M j, g:i A').'.');
    }

    public function update(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->authorizeReport($request, $schedule->report, 'edit');
        $schedule->fill($this->validated($request));
        $schedule->next_run_at = $schedule->computeNextRun();
        $schedule->save();

        return back()->with('success', 'Schedule updated.');
    }

    public function destroy(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->authorizeReport($request, $schedule->report, 'delete');
        $schedule->delete();

        return back()->with('success', 'Schedule removed.');
    }

    public static function scheduleArray(ReportSchedule $s): array
    {
        return [
            'id' => $s->id,
            'frequency' => $s->frequency,
            'format' => $s->format,
            'recipients' => $s->recipients ?? [],
            'hour' => $s->hour,
            'day_of_week' => $s->day_of_week,
            'day_of_month' => $s->day_of_month,
            'is_active' => (bool) $s->is_active,
            'last_run_at' => $s->last_run_at?->toIso8601String(),
            'next_run_at' => $s->next_run_at?->toIso8601String(),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'format' => ['required', Rule::in(array_keys(ReportExporter::FORMATS))],
            'hour' => ['required', 'integer', 'between:0,23'],
            'day_of_week' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,28'],
            'recipients' => ['required', 'array', 'min:1', 'max:25'],
            'recipients.*' => ['required', 'email:rfc', 'max:255', 'distinct'],
            'is_active' => ['boolean'],
        ]);

        return [
            'frequency' => $data['frequency'],
            'format' => $data['format'],
            'hour' => (int) $data['hour'],
            'day_of_week' => $data['frequency'] === 'weekly' ? (int) $data['day_of_week'] : null,
            'day_of_month' => $data['frequency'] === 'monthly' ? (int) $data['day_of_month'] : null,
            'recipients' => array_values(array_unique(array_map(fn ($e) => mb_strtolower(trim($e)), $data['recipients']))),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function authorizeReport(Request $request, ?Report $report, string $action): void
    {
        abort_unless($report, 404);
        Gate::authorize('reporting.reports.view');
        Gate::authorize('reporting.schedules.'.$action);
        abort_unless($report->isVisibleTo($request->user()), 403);
        $registry = app(ReportSourceRegistry::class);
        abort_unless($registry->has($report->source) && $request->user()->can($registry->get($report->source)['permission']), 403);
        if ($action !== 'view') {
            // Scheduled deliveries export data, so the scheduler must be allowed to export it.
            Gate::authorize('reporting.reports.export');
        }
    }
}

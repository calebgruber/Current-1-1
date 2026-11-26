<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Jobs\Services\JobService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PullSheetController extends Controller
{
    public function __invoke(Request $request, ProductionJob $job, JobService $jobs): View|JsonResponse
    {
        Gate::any(['jobs.jobs.view', 'warehouse.picking.view', 'warehouse.prep.view']) || abort(403);
        $job->load(['client:id,name', 'venue:id,name']);
        $sheet = $jobs->pullSheet($job);

        $header = [
            'id' => $job->id,
            'number' => $job->number,
            'name' => $job->name,
            'stage' => $job->stage->value,
            'stage_label' => $job->stage->label(),
            'client' => $job->client?->name,
            'venue' => $job->venue?->name,
            'prep_starts_at' => $job->prep_starts_at?->toIso8601String(),
            'starts_at' => $job->starts_at?->toIso8601String(),
            'ends_at' => $job->ends_at?->toIso8601String(),
            'returns_at' => $job->returns_at?->toIso8601String(),
        ];

        if ($request->wantsJson()) {
            return response()->json(['job' => $header, 'sheet' => $sheet]);
        }

        return view('jobs.pull-sheet', [
            'job' => $header,
            'sheet' => $sheet,
            'generatedAt' => now()->toIso8601String(),
        ]);
    }
}

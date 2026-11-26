<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Jobs\Services\JobService;
use App\Domain\Reservations\Exceptions\OverbookingException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class JobStageController extends Controller
{
    public function __invoke(Request $request, ProductionJob $job, JobService $jobs): RedirectResponse|JsonResponse
    {
        Gate::any(['jobs.stages.edit', 'jobs.jobs.edit']) || abort(403);

        $data = $request->validate([
            'stage' => ['required', Rule::enum(JobStage::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override,true', 'string', 'min:5', 'max:500'],
        ]);
        $target = JobStage::from($data['stage']);

        if ($target->requiresApproval() && ! Gate::any(['jobs.jobs.approve', 'jobs.stages.approve'])) {
            return $this->fail($request, "You need approval permission to move a job to {$target->label()}.", 403);
        }
        $override = $request->boolean('override');
        if ($override) {
            Gate::authorize('reservations.overbooking.approve');
        }

        $notes = $data['notes'] ?? null;
        if ($override) {
            $notes = trim(($notes ? $notes."\n" : '').'Overbooking override: '.$data['override_reason']);
        }

        try {
            $jobs->transition($job, $target, $request->user(), $notes, $override);
        } catch (InvalidArgumentException $e) {
            return $this->fail($request, $e->getMessage(), 422);
        } catch (OverbookingException $e) {
            return $this->fail($request, 'Cannot move to '.$target->label().' — equipment is overbooked. '.$e->getMessage(), 422, $e->toArray());
        }

        $message = "{$job->number} moved to {$target->label()}.";
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'stage' => $target->value]);
        }

        return back()->with('success', $message);
    }

    private function fail(Request $request, string $message, int $status, array $conflicts = []): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => ['stage' => [$message]], 'conflicts' => $conflicts], $status);
        }
        if ($status === 403) {
            abort(403, $message);
        }

        return back()->withErrors(['stage' => $message])->with('stage_conflicts', $conflicts);
    }
}

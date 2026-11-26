<?php

namespace App\Domain\Warehouse\Http\Controllers;

use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Warehouse\DTOs\ScanResult;
use App\Domain\Warehouse\Enums\ScanMode;
use App\Domain\Warehouse\Models\ScanEvent;
use App\Domain\Warehouse\Services\JobProgress;
use App\Domain\Warehouse\Services\WarehouseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScanController extends Controller
{
    public function __invoke(Request $request, WarehouseService $warehouse, JobProgress $progress): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::enum(ScanMode::class)],
            'code' => ['required', 'string', 'max:191'],
            'job_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'station' => ['nullable', 'string', 'max:64'],
        ]);
        $mode = ScanMode::from($data['mode']);

        if ($mode === ScanMode::Lookup) {
            Gate::any([...WarehouseController::VIEW_KEYS, 'warehouse.display.view']) || abort(403);
        } else {
            Gate::authorize(WarehouseController::permissionFor($mode, 'edit'));
        }

        $job = isset($data['job_id']) ? ProductionJob::query()->find($data['job_id']) : null;
        if (isset($data['job_id']) && ! $job) {
            return $this->respond(ScanResult::error('Job not found', 'The selected job no longer exists. Pick another job.'), null, $progress);
        }

        if ($job && $mode->requiresJob() && ! $job->stage->isWarehouseActive()) {
            $result = ScanResult::error('Job not ready for the warehouse', "{$job->number} is {$job->stage->label()}. Only Reserved to Returned jobs can be scanned.");
            ScanEvent::query()->create([
                'mode' => $mode->value,
                'code' => mb_substr($data['code'], 0, 191),
                'result' => $result->level,
                'message' => mb_substr($result->title.': '.$result->message, 0, 255),
                'job_id' => $job->id,
                'quantity' => 1,
                'station' => $data['station'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            return $this->respond($result, $job, $progress);
        }

        $result = $warehouse->scan($mode, $data['code'], $job, $request->user(), (int) ($data['quantity'] ?? 1), $data['station'] ?? null);

        $selected = null;
        foreach ($result->effects as $effect) {
            if (($effect['type'] ?? null) === 'select_job') {
                $selected = ProductionJob::query()->find($effect['job_id']);
            }
        }

        return $this->respond($result, $selected ?? $job, $progress, $selected);
    }

    private function respond(ScanResult $result, ?ProductionJob $job, JobProgress $progress, ?ProductionJob $selected = null): JsonResponse
    {
        $payload = ['result' => $result->toArray(), 'progress' => null, 'select_job' => null];
        if ($job) {
            $job->loadMissing(['client:id,name', 'venue:id,name']);
            $payload['progress'] = ['job' => $progress->header($job), ...$progress->lines($job)];
        }
        if ($selected) {
            $payload['select_job'] = ['id' => $selected->id, 'number' => $selected->number, 'name' => $selected->name, 'stage' => $selected->stage->value];
        }

        return response()->json($payload);
    }
}

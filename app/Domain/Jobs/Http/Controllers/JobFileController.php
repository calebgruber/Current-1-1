<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Jobs\Models\Attachment;
use App\Domain\Jobs\Models\Note;
use App\Domain\Jobs\Models\ProductionJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobFileController extends Controller
{
    public const ALLOWED_EXTENSIONS = 'pdf,png,jpg,jpeg,gif,webp,txt,csv,xlsx,xls,docx,doc,zip';

    public function storeNote(Request $request, ProductionJob $job): RedirectResponse
    {
        Gate::any(['jobs.files.create', 'jobs.jobs.edit']) || abort(403);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $job->notes()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_pinned' => (bool) ($data['is_pinned'] ?? false)]);

        return back()->with('success', 'Note added.');
    }

    public function destroyNote(Request $request, ProductionJob $job, Note $note): RedirectResponse
    {
        abort_unless($note->notable_type === $job->getMorphClass() && (int) $note->notable_id === $job->id, 404);
        abort_unless($note->user_id === $request->user()->id || Gate::allows('jobs.jobs.admin') || Gate::allows('jobs.files.delete'), 403);
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    public function storeAttachment(Request $request, ProductionJob $job): RedirectResponse
    {
        Gate::authorize('jobs.files.create');
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:20480', 'mimes:'.self::ALLOWED_EXTENSIONS],
        ], [], ['files.*' => 'file']);

        foreach ($request->file('files') as $file) {
            $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
            if (! in_array($ext, explode(',', self::ALLOWED_EXTENSIONS), true)) {
                $ext = strtolower($file->getClientOriginalExtension());
            }
            $path = $file->storeAs("attachments/jobs/{$job->id}", Str::uuid().'.'.$ext, 'local');
            $job->attachments()->create([
                'user_id' => $request->user()->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return back()->with('success', 'Files uploaded.');
    }

    public function download(ProductionJob $job, Attachment $attachment): StreamedResponse
    {
        Gate::any(['jobs.files.view', 'jobs.jobs.edit']) || abort(403);
        $this->assertOwned($job, $attachment);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(ProductionJob $job, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('jobs.files.delete');
        $this->assertOwned($job, $attachment);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'File deleted.');
    }

    private function assertOwned(ProductionJob $job, Attachment $attachment): void
    {
        abort_unless(
            $attachment->attachable_type === $job->getMorphClass()
            && (int) $attachment->attachable_id === $job->id
            && str_starts_with($attachment->path, "attachments/jobs/{$job->id}/")
            && ! str_contains($attachment->path, '..')
            && Storage::disk($attachment->disk)->exists($attachment->path),
            404,
        );
    }
}

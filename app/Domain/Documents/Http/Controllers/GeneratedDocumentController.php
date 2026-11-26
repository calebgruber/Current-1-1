<?php

namespace App\Domain\Documents\Http\Controllers;

use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Documents\Exceptions\TemplateException;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Services\DocumentGenerator;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GeneratedDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentGenerator $generator,
        private readonly CustomFieldService $customFields,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('documents.documents.view');
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(DocumentTemplate::TYPES))],
            'entity_type' => ['nullable', Rule::in(array_keys(DocumentGenerator::ENTITIES))],
            'entity_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();
        $allowedEntities = array_values(array_filter(array_keys(DocumentGenerator::ENTITIES), fn ($e) => $user->can(DocumentGenerator::viewPermission($e))));

        $paginator = GeneratedDocument::query()
            ->with(['creator:id,name', 'template:id,name', 'documentable'])
            ->whereIn('documentable_type', $allowedEntities ?: ['__none__'])
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['entity_type'] ?? null, fn ($q, $t) => $q->where('documentable_type', $t))
            ->when($filters['entity_id'] ?? null, fn ($q, $id) => $q->where('documentable_id', $id))
            ->when($filters['q'] ?? null, function ($q, $s) {
                $like = '%'.addcslashes($s, '%_\\').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like));
            })
            ->latest('id')
            ->paginate(25)->withQueryString();

        $listColumns = $this->customFields->listColumns('document');
        $values = $listColumns ? $this->customFields->valuesForMany('document', $paginator->getCollection()->pluck('id')->all()) : [];

        $documents = $paginator->through(fn (GeneratedDocument $d) => $this->row($d) + ['custom' => $values[$d->id] ?? []]);

        $entityLabel = null;
        if (($filters['entity_type'] ?? null) && ($filters['entity_id'] ?? null) && in_array($filters['entity_type'], $allowedEntities, true)) {
            $entity = DocumentGenerator::findEntity($filters['entity_type'], (int) $filters['entity_id']);
            $entityLabel = $entity ? DocumentGenerator::entityLabel($entity) : null;
        }

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'filters' => $filters,
            'entityLabel' => $entityLabel,
            'types' => collect(DocumentTemplate::TYPES)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'templates' => DocumentTemplate::query()->orderBy('type')->orderByDesc('is_default')->orderBy('name')
                ->get(['id', 'name', 'type', 'entity_type', 'is_default'])
                ->map(fn ($t) => [
                    'id' => $t->id, 'name' => $t->name, 'type' => $t->type, 'entity_type' => $t->entity_type,
                    'is_default' => (bool) $t->is_default, 'type_label' => DocumentTemplate::TYPES[$t->type] ?? $t->type,
                ]),
            'entityTypes' => collect($allowedEntities)->map(fn ($e) => ['value' => $e, 'label' => $e === 'job' ? 'Job' : 'Maintenance record'])->values(),
            'customFieldSchema' => $this->customFields->schema('document'),
            'customColumns' => $listColumns,
        ]);
    }

    public function show(Request $request, GeneratedDocument $document): Response
    {
        $this->authorizeView($request, $document);
        $document->load(['creator:id,name', 'template:id,name,paper_size,orientation', 'documentable']);

        return Inertia::render('Documents/Show', [
            'document' => $this->row($document),
            // The HTML snapshot was sanitised by DocumentRenderer when generated; it is shown in a sandboxed iframe.
            'html' => (string) $document->html,
            'customFieldSchema' => $this->customFields->schema('document'),
            'customFields' => $document->customFields(),
        ]);
    }

    public function pdf(Request $request, GeneratedDocument $document): HttpResponse
    {
        $this->authorizeView($request, $document);
        $inline = ! $request->boolean('download');
        if (! $inline) {
            Gate::authorize('documents.documents.export');
        }
        $filename = $document->number.'.pdf';

        return response($this->generator->pdfBytes($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function html(Request $request, GeneratedDocument $document): HttpResponse
    {
        $this->authorizeView($request, $document);

        return response((string) $document->html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // Defence in depth: the snapshot is sanitised, and scripts are blocked outright.
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function generate(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('documents.documents.create');
        $request->merge([
            'entity_type' => $request->input('entity_type', $request->input('documentable_type')),
            'entity_id' => $request->input('entity_id', $request->input('documentable_id')),
        ]);
        $data = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('document_templates', 'id')],
            'entity_type' => ['required', Rule::in(array_keys(DocumentGenerator::ENTITIES))],
            'entity_id' => ['required', 'integer'],
            'custom_fields' => ['nullable', 'array'],
        ]);
        Gate::authorize(DocumentGenerator::viewPermission($data['entity_type']));

        $template = DocumentTemplate::query()->findOrFail($data['template_id']);
        if ($template->entity_type !== $data['entity_type']) {
            throw ValidationException::withMessages(['template_id' => 'This template is for '.($template->entity_type === 'job' ? 'jobs' : 'maintenance records').'.']);
        }
        $entity = DocumentGenerator::findEntity($data['entity_type'], (int) $data['entity_id']);
        if (! $entity) {
            throw ValidationException::withMessages(['entity_id' => 'The selected record no longer exists.']);
        }
        $clean = $this->customFields->validate('document', $data['custom_fields'] ?? []);

        try {
            $document = DB::transaction(function () use ($template, $entity, $request, $clean) {
                $document = $this->generator->generate($template, $entity, $request->user());
                $this->customFields->save($document, $clean);

                return $document;
            });
        } catch (TemplateException $e) {
            throw ValidationException::withMessages(['template_id' => 'The template could not be rendered: '.$e->getMessage()]);
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'document' => $this->row($document->fresh(['creator:id,name', 'template:id,name', 'documentable'])),
                'pdf_url' => route('documents.pdf', [$document, 'download' => 1]),
                'show_url' => route('documents.show', $document),
            ], 201);
        }

        return redirect()->route('documents.show', $document)->with('success', "{$document->number} generated.");
    }

    public function update(Request $request, GeneratedDocument $document): RedirectResponse
    {
        $this->authorizeView($request, $document);
        Gate::authorize('documents.documents.create');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
        ]);
        $clean = $this->customFields->validate('document', $data['custom_fields'] ?? []);
        DB::transaction(function () use ($document, $data, $clean) {
            $document->update(['title' => $data['title']]);
            $this->customFields->save($document, $clean);
        });

        return back()->with('success', 'Document updated.');
    }

    public function destroy(Request $request, GeneratedDocument $document): RedirectResponse
    {
        $this->authorizeView($request, $document);
        Gate::authorize('documents.documents.delete');
        $path = (string) $document->pdf_path;
        $document->delete();
        if (preg_match('#^documents/[A-Za-z0-9_-]+\.pdf$#', $path)) {
            Storage::disk(DocumentGenerator::DISK)->delete($path);
        }

        return redirect()->route('documents.index')->with('success', 'Document deleted.');
    }

    /** Lookup for the generate dialog / designer "preview with real record" picker. */
    public function entities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DocumentGenerator::ENTITIES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        if (! $request->user()->can('documents.documents.create') && ! $request->user()->can('documents.templates.view')) {
            abort(403);
        }
        Gate::authorize(DocumentGenerator::viewPermission($data['type']));
        $like = '%'.addcslashes((string) ($data['q'] ?? ''), '%_\\').'%';

        if ($data['type'] === 'job') {
            $rows = ProductionJob::query()
                ->when($data['q'] ?? null, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('name', 'like', $like)))
                ->latest('id')->limit(20)->get(['id', 'number', 'name', 'stage'])
                ->map(fn ($j) => ['value' => $j->id, 'label' => $j->number.' — '.$j->name, 'description' => $j->stage?->label()]);
        } else {
            $rows = MaintenanceRecord::query()
                ->when($data['q'] ?? null, fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('title', 'like', $like)))
                ->latest('id')->limit(20)->get(['id', 'reference', 'title', 'status'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => $m->reference.' — '.$m->title, 'description' => $m->status?->label()]);
        }

        return response()->json($rows->values());
    }

    private function authorizeView(Request $request, GeneratedDocument $document): void
    {
        Gate::authorize('documents.documents.view');
        $permission = DocumentGenerator::viewPermission((string) $document->documentable_type);
        abort_unless($permission && $request->user()->can($permission), 403);
    }

    private function row(GeneratedDocument $d): array
    {
        $entity = $d->documentable;
        $entityUrl = null;
        if ($entity) {
            $routeName = $entity instanceof MaintenanceRecord ? 'maintenance.show' : 'jobs.show';
            $entityUrl = \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName, $entity) : null;
        }

        return [
            'id' => $d->id,
            'number' => $d->number,
            'title' => $d->title,
            'type' => $d->type,
            'type_label' => DocumentTemplate::TYPES[$d->type] ?? $d->type,
            'entity_type' => $d->documentable_type,
            'entity_id' => $d->documentable_id,
            'entity_label' => $entity ? DocumentGenerator::entityLabel($entity) : 'Deleted record',
            'entity_url' => $entityUrl,
            'template' => $d->template ? ['id' => $d->template->id, 'name' => $d->template->name] : null,
            'created_by' => $d->creator?->name,
            'created_at' => $d->created_at?->toIso8601String(),
            'has_pdf' => (bool) $d->pdf_path,
        ];
    }
}

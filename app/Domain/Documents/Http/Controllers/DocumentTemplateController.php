<?php

namespace App\Domain\Documents\Http\Controllers;

use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Documents\Exceptions\TemplateException;
use App\Domain\Documents\Http\Requests\TemplateRequest;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\BlockCompiler;
use App\Domain\Documents\Services\BlockSanitizer;
use App\Domain\Documents\Services\DefaultTemplateInstaller;
use App\Domain\Documents\Services\DefaultTemplates;
use App\Domain\Documents\Services\DocumentContextBuilder;
use App\Domain\Documents\Services\DocumentGenerator;
use App\Domain\Documents\Services\DocumentRenderer;
use App\Domain\Documents\Services\TemplateRenderer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentRenderer $renderer,
        private readonly DocumentContextBuilder $contexts,
        private readonly BlockSanitizer $blocks,
        private readonly CustomFieldService $customFields,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('documents.templates.view');
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(DocumentTemplate::TYPES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $templates = DocumentTemplate::query()
            ->withCount('documents')
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->orderBy('type')->orderByDesc('is_default')->orderBy('name')
            ->get()
            ->map(fn (DocumentTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => $t->type,
                'type_label' => DocumentTemplate::TYPES[$t->type] ?? $t->type,
                'entity_type' => $t->entity_type,
                'editor' => $t->editor,
                'paper_size' => $t->paper_size,
                'orientation' => $t->orientation,
                'is_default' => (bool) $t->is_default,
                'version' => (int) $t->version,
                'documents_count' => $t->documents_count,
                'updated_at' => $t->updated_at?->toIso8601String(),
            ]);

        $counts = DocumentTemplate::query()->select('type', DB::raw('count(*) as c'))->groupBy('type')->pluck('c', 'type');

        return Inertia::render('Documents/Templates/Index', [
            'templates' => $templates,
            'types' => collect(DocumentTemplate::TYPES)->map(fn ($label, $key) => [
                'value' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0),
            ])->values(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('documents.templates.create');
        $type = $request->validate(['type' => ['nullable', Rule::in(array_keys(DocumentTemplate::TYPES))]])['type'] ?? 'quotation';
        $default = DefaultTemplates::for($type);

        return $this->designer([
            'id' => null,
            'name' => 'New '.mb_strtolower(DocumentTemplate::TYPES[$type]),
            'type' => $type,
            'entity_type' => $default['entity_type'],
            'editor' => 'visual',
            'blocks' => $default['blocks'],
            'html' => '',
            'css' => '',
            'paper_size' => 'letter',
            'orientation' => $default['orientation'] ?? 'portrait',
            'margins' => null,
            'watermark' => $default['watermark'] ?? null,
            'is_default' => false,
            'version' => 1,
        ]);
    }

    public function store(TemplateRequest $request): RedirectResponse
    {
        $template = DB::transaction(function () use ($request) {
            $template = DocumentTemplate::create($this->payload($request) + ['version' => 1]);
            if ($request->boolean('is_default') && $request->user()->can('documents.templates.admin')) {
                $this->makeDefault($template);
            }

            return $template;
        });

        return redirect()->route('documents.templates.edit', $template)->with('success', 'Template created.');
    }

    public function edit(DocumentTemplate $template): Response
    {
        Gate::authorize('documents.templates.edit');

        return $this->designer($this->templateArray($template));
    }

    public function update(TemplateRequest $request, DocumentTemplate $template): RedirectResponse
    {
        DB::transaction(function () use ($request, $template) {
            $template->fill($this->payload($request));
            $template->version = (int) $template->version + 1;
            $template->save();
            if ($request->boolean('is_default') && ! $template->is_default && $request->user()->can('documents.templates.admin')) {
                $this->makeDefault($template);
            }
        });

        return back()->with('success', 'Template saved.');
    }

    public function destroy(DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('documents.templates.delete');
        $template->delete();

        return redirect()->route('documents.templates.index')->with('success', 'Template deleted.');
    }

    public function duplicate(Request $request, DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('documents.templates.create');
        $copy = $template->replicate(['is_default', 'version']);
        $copy->name = mb_substr($template->name.' (copy)', 0, 255);
        $copy->is_default = false;
        $copy->version = 1;
        $copy->updated_by = $request->user()->id;
        $copy->save();

        return redirect()->route('documents.templates.edit', $copy)->with('success', 'Template duplicated.');
    }

    public function makeDefaultAction(DocumentTemplate $template): RedirectResponse
    {
        Gate::authorize('documents.templates.admin');
        DB::transaction(fn () => $this->makeDefault($template));

        return back()->with('success', "“{$template->name}” is now the default ".mb_strtolower(DocumentTemplate::TYPES[$template->type] ?? 'template').'.');
    }

    /** Install the starter template for every type (or reset one type) from DefaultTemplates. */
    public function installDefaults(Request $request): RedirectResponse
    {
        Gate::authorize('documents.templates.admin');
        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(DocumentTemplate::TYPES))],
            'mode' => ['required', Rule::in(['missing', 'reset'])],
        ]);
        $count = DefaultTemplateInstaller::install($data['type'] ?? null, $data['mode'] === 'reset', $request->user()->id);

        return back()->with('success', $count === 0 ? 'Default templates are already installed.' : "{$count} default template(s) ".($data['mode'] === 'reset' ? 'reset' : 'installed').'.');
    }

    /** Live designer preview: renders a draft through DocumentRenderer (sanitised) as JSON. */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('documents.templates.view');
        $data = $request->validate(array_merge(TemplateRequest::draftRules(), [
            'type' => ['required', Rule::in(array_keys(DocumentTemplate::TYPES))],
            'entity_type' => ['nullable', Rule::in(array_keys(DocumentGenerator::ENTITIES))],
            'entity_id' => ['nullable', 'integer'],
            'sample' => ['nullable', 'array'],
            'sample.type' => ['nullable', Rule::in(array_keys(DocumentGenerator::ENTITIES))],
            'sample.id' => ['nullable', 'integer'],
            'editor' => ['nullable', Rule::in(['visual', 'code'])],
            'paper_size' => ['nullable', Rule::in(DocumentRenderer::PAPER_SIZES)],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape'])],
        ]));
        $entityType = $data['entity_type'] ?? $data['sample']['type'] ?? null;
        $entityId = $data['entity_id'] ?? $data['sample']['id'] ?? null;

        $source = null;
        try {
            $blocks = $this->blocks->clean($data['blocks'] ?? []);
            $template = [
                'editor' => $data['editor'] ?? 'visual',
                'blocks' => $blocks,
                'html' => $data['html'] ?? '',
                'css' => $data['css'] ?? '',
                'paper_size' => $data['paper_size'] ?? 'letter',
                'orientation' => $data['orientation'] ?? 'portrait',
                'margins' => $data['margins'] ?? null,
                'watermark' => $data['watermark'] ?? null,
            ];
            $context = $this->previewContext($request, $data['type'], $entityType, $entityId);
            $source = $template['editor'] === 'visual' ? $this->renderer->source($template) : null;
            $html = $this->renderer->document($template, $context, DocumentTemplate::TYPES[$data['type']]);

            return response()->json(['html' => $html, 'error' => null, 'source' => $source]);
        } catch (TemplateException $e) {
            return response()->json([
                'html' => null,
                'error' => ['message' => preg_replace('/^Line \d+: /', '', $e->getMessage()), 'line' => $e->templateLine],
                'source' => $source,
            ]);
        }
    }

    /** "Eject" visual blocks to template code for the Code tab. */
    public function compile(Request $request, BlockCompiler $compiler): JsonResponse
    {
        Gate::authorize('documents.templates.view');
        $data = $request->validate(['blocks' => ['nullable', 'array', 'max:200']]);
        try {
            return response()->json(['source' => $compiler->compile($this->blocks->clean($data['blocks'] ?? [])), 'error' => null]);
        } catch (TemplateException $e) {
            return response()->json(['source' => null, 'error' => ['message' => $e->getMessage(), 'line' => $e->templateLine]]);
        }
    }

    private function previewContext(Request $request, string $type, ?string $entityType, ?int $entityId): array
    {
        if ($entityType && $entityId) {
            Gate::authorize(DocumentGenerator::viewPermission($entityType));
            $entity = DocumentGenerator::findEntity($entityType, $entityId);
            abort_if(! $entity, 404, 'Record not found.');

            return app(DocumentGenerator::class)->context($type, $entity, $request->user());
        }
        $context = $this->contexts->sample($type, $request->user());
        foreach (['job', 'maintenance'] as $entity) {
            foreach ($this->customFields->schema($entity) as $field) {
                $context[$entity]['custom'][$field['key']] ??= '['.$field['label'].']';
            }
        }
        $context['document']['number'] = 'D-000123';

        return $context;
    }

    private function payload(TemplateRequest $request): array
    {
        $data = $request->validated();
        $visual = $data['editor'] === 'visual';

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'entity_type' => $data['entity_type'],
            'editor' => $data['editor'],
            'blocks' => $this->blocks->clean($data['blocks'] ?? []),
            'html' => $visual ? null : ($data['html'] ?? ''),
            'css' => $data['css'] ?? null,
            'paper_size' => $data['paper_size'],
            'orientation' => $data['orientation'],
            'margins' => isset($data['margins']) ? array_map(fn ($v) => $v === null ? null : (float) $v, array_intersect_key($data['margins'], array_flip(['top', 'right', 'bottom', 'left']))) : null,
            'watermark' => $data['watermark'] ?? null,
            'updated_by' => $request->user()->id,
        ];
    }

    private function makeDefault(DocumentTemplate $template): void
    {
        DocumentTemplate::query()->where('type', $template->type)->whereKeyNot($template->id)->update(['is_default' => false]);
        $template->forceFill(['is_default' => true])->save();
    }

    private function templateArray(DocumentTemplate $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'type' => $t->type,
            'entity_type' => $t->entity_type,
            'editor' => $t->editor,
            'blocks' => $t->blocks ?? [],
            'html' => $t->html ?? '',
            'css' => $t->css ?? '',
            'paper_size' => $t->paper_size,
            'orientation' => $t->orientation,
            'margins' => $t->margins,
            'watermark' => $t->watermark,
            'is_default' => (bool) $t->is_default,
            'version' => (int) $t->version,
        ];
    }

    private function designer(array $template): Response
    {
        $variables = $this->contexts->variables();
        foreach (['job' => 'Job custom fields', 'maintenance' => 'Maintenance custom fields'] as $entity => $group) {
            $fields = $this->customFields->schema($entity);
            if ($fields) {
                $variables[$group] = array_map(fn ($f) => $entity.'.custom.'.$f['key'], $fields);
            }
        }

        return Inertia::render('Documents/Templates/Designer', [
            'template' => $template,
            'types' => collect(DocumentTemplate::TYPES)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'blockTypes' => BlockCompiler::TYPES,
            'variables' => $variables,
            'filters' => TemplateRenderer::FILTERS,
            'functions' => TemplateRenderer::FUNCTIONS,
            'paperSizes' => DocumentRenderer::PAPER_SIZES,
            'canAdmin' => request()->user()->can('documents.templates.admin'),
        ]);
    }
}

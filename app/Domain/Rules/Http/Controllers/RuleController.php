<?php

namespace App\Domain\Rules\Http\Controllers;

use App\Domain\CustomFields\Services\CustomFieldService;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductAccessory;
use App\Domain\Inventory\Models\Tag;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Rules\DTOs\RuleFinding;
use App\Domain\Rules\Models\ValidationRule;
use App\Domain\Rules\Services\JobValidationService;
use App\Domain\Rules\Services\RuleEvaluator;
use App\Domain\Rules\Services\RulePresets;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RuleController extends Controller
{
    public function __construct(
        private readonly RuleEvaluator $evaluator,
        private readonly JobValidationService $validation,
        private readonly CustomFieldService $customFields,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('rules.rules.view');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'severity' => ['nullable', Rule::in(RuleEvaluator::SEVERITIES)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $rules = ValidationRule::query()->with('creator:id,name')
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('description', 'like', '%'.addcslashes($s, '%_\\').'%')))
            ->when($filters['severity'] ?? null, fn ($q, $s) => $q->where('severity', $s))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('is_active', $s === 'active'))
            ->orderByDesc('is_active')->orderBy('name')->get();

        $labels = $this->labelMaps($rules->pluck('definition')->all());

        $accessories = ProductAccessory::query()
            ->with(['product:id,name', 'accessory:id,name'])
            ->orderBy('product_id')->limit(500)->get()
            ->filter(fn ($a) => $a->product && $a->accessory)
            ->groupBy('product_id')
            ->map(fn ($group) => [
                'product_id' => $group->first()->product_id,
                'product' => $group->first()->product->name,
                'url' => Route::has('inventory.products.show') ? route('inventory.products.show', $group->first()->product_id) : null,
                'accessories' => $group->map(fn ($a) => [
                    'name' => $a->accessory->name,
                    'quantity' => (float) $a->quantity,
                    'type' => $a->type,
                    'auto_add' => (bool) $a->auto_add,
                ])->values(),
            ])->values();

        return view('rules.index', [
            'rules' => $rules->map(fn (ValidationRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'severity' => $r->severity,
                'is_active' => (bool) $r->is_active,
                'summary' => $this->summary($r->definition ?? [], $labels),
                'created_by' => $r->creator?->name,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ])->values(),
            'filters' => $filters,
            'counts' => [
                'total' => ValidationRule::count(),
                'active' => ValidationRule::where('is_active', true)->count(),
                'errors' => ValidationRule::where('is_active', true)->where('severity', 'error')->count(),
            ],
            'accessories' => $accessories,
            'accessoryTotal' => ProductAccessory::count(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('rules.rules.create');
        $preset = collect(RulePresets::all())->firstWhere('key', $request->query('preset'));

        return $this->builder([
            'id' => null,
            'name' => $preset['name'] ?? '',
            'description' => $preset['description'] ?? '',
            'severity' => $preset['severity'] ?? 'warning',
            'is_active' => true,
            'definition' => $preset['definition'] ?? [
                'scope' => ['match' => 'category', 'values' => []],
                'check' => 'requires',
                'requirement' => ['match' => 'product', 'values' => [], 'per' => 1, 'quantity' => 1],
                'basis' => 'pull',
                'message' => '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('rules.rules.create');
        $rule = ValidationRule::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('rules.index')->with('success', "Rule “{$rule->name}” created.");
    }

    public function edit(ValidationRule $rule): View
    {
        Gate::authorize('rules.rules.edit');

        return $this->builder([
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description ?? '',
            'severity' => $rule->severity,
            'is_active' => (bool) $rule->is_active,
            'definition' => $rule->definition ?? [],
        ]);
    }

    public function update(Request $request, ValidationRule $rule): RedirectResponse
    {
        Gate::authorize('rules.rules.edit');
        $rule->update($this->validated($request));

        return redirect()->route('rules.index')->with('success', 'Rule saved.');
    }

    public function destroy(ValidationRule $rule): RedirectResponse
    {
        Gate::authorize('rules.rules.delete');
        $rule->delete();

        return redirect()->route('rules.index')->with('success', 'Rule deleted.');
    }

    public function toggle(ValidationRule $rule): RedirectResponse
    {
        Gate::authorize('rules.rules.edit');
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', $rule->is_active ? 'Rule enabled.' : 'Rule disabled.');
    }

    /** Run a draft rule against a job and report its findings (nothing is saved). */
    public function test(Request $request): JsonResponse
    {
        Gate::authorize('rules.rules.view');
        $data = $request->validate([
            'job_id' => ['required', 'integer', Rule::exists('production_jobs', 'id')],
            'name' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', Rule::in(RuleEvaluator::SEVERITIES)],
            'definition' => ['required', 'array'],
        ]);
        Gate::authorize('jobs.jobs.view');
        $definition = $this->cleanDefinition($data['definition']);
        if ($errors = $this->evaluator->validateDefinition($definition)) {
            throw ValidationException::withMessages(['definition' => $errors]);
        }

        $job = ProductionJob::query()->findOrFail($data['job_id']);
        $items = $job->items()->with(['product.tags', 'product.customFieldValues.definition'])->get();
        $lines = $this->validation->lines($items);
        $rule = ['id' => null, 'name' => ($data['name'] ?? null) ?: 'Draft rule', 'severity' => $data['severity'] ?? 'warning', 'definition' => $definition];
        $findings = array_map(fn (RuleFinding $f) => $f->toArray(), $this->evaluator->evaluate($lines, [$rule]));
        $matched = $this->evaluator->matching($lines, $definition['scope']);
        $supporting = in_array($definition['check'], ['requires', 'requires_any'], true)
            ? $this->evaluator->matching($lines, $definition['requirement'])
            : [];
        $summarise = fn (array $ls) => array_values(array_map(fn ($l) => ['product' => $l['product'], 'required' => $l['required'], 'spare' => $l['spare']], $ls));

        return response()->json([
            'job' => ['id' => $job->id, 'number' => $job->number, 'name' => $job->name],
            'lines' => count($lines),
            'matched' => $summarise($matched),
            'supporting' => $summarise($supporting),
            'findings' => $findings,
            'passed' => $matched !== [] && $findings === [],
        ]);
    }

    public function jobs(Request $request): JsonResponse
    {
        Gate::authorize('rules.rules.view');
        Gate::authorize('jobs.jobs.view');
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');
        $like = '%'.addcslashes($q, '%_\\').'%';

        return response()->json(ProductionJob::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('name', 'like', $like)))
            ->withCount('items')->latest('id')->limit(20)->get(['id', 'number', 'name', 'stage'])
            ->map(fn ($j) => ['value' => $j->id, 'label' => $j->number.' — '.$j->name, 'description' => $j->items_count.' line(s) · '.$j->stage?->label()])
            ->values());
    }

    public function products(Request $request): JsonResponse
    {
        Gate::authorize('rules.rules.view');
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');
        $like = '%'.addcslashes($q, '%_\\').'%';

        return response()->json(Product::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like)))
            ->orderBy('name')->limit(25)->get(['id', 'name', 'sku'])
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'description' => $p->sku])
            ->values());
    }

    private function builder(array $rule): View
    {
        $definition = $rule['definition'];
        $productIds = collect([$definition['scope'] ?? [], $definition['requirement'] ?? []])
            ->filter(fn ($s) => ($s['match'] ?? null) === 'product')
            ->flatMap(fn ($s) => (array) ($s['values'] ?? []))->map(fn ($v) => (int) $v)->unique()->all();

        return view('rules.builder', [
            'rule' => $rule,
            'checks' => RuleEvaluator::CHECKS,
            'matches' => RuleEvaluator::MATCHES,
            'severities' => RuleEvaluator::SEVERITIES,
            'categories' => $this->categoryOptions(),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'slug'])->map(fn ($t) => ['value' => $t->slug ?: $t->name, 'label' => $t->name])->values(),
            'products' => Product::query()->whereIn('id', $productIds)->get(['id', 'name', 'sku'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'description' => $p->sku])->values(),
            'customFields' => collect($this->customFields->schema('product'))->map(fn ($f) => [
                'key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'],
                'options' => collect($f['options'] ?? [])->map(fn ($o) => is_array($o) ? (string) ($o['value'] ?? $o['label'] ?? '') : (string) $o)->filter()->values(),
            ])->values(),
            'presets' => RulePresets::all(),
        ]);
    }

    private function categoryOptions(): array
    {
        $all = Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name']);
        $byId = $all->keyBy('id');
        $path = function ($c) use ($byId) {
            $names = [$c->name];
            $seen = [$c->id => true];
            while ($c->parent_id && isset($byId[$c->parent_id]) && ! isset($seen[$c->parent_id])) {
                $c = $byId[$c->parent_id];
                $seen[$c->id] = true;
                array_unshift($names, $c->name);
            }

            return implode(' › ', $names);
        };

        return $all->map(fn ($c) => ['value' => (string) $c->id, 'label' => $path($c)])->sortBy('label')->values()->all();
    }

    private function cleanDefinition(array $d): array
    {
        $selector = function ($s, bool $withQuantities) {
            $s = is_array($s) ? $s : [];
            $out = [
                'match' => is_string($s['match'] ?? null) ? $s['match'] : null,
                'values' => array_values(array_slice(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', (array) ($s['values'] ?? [])), fn ($v) => $v !== ''), 0, 100)),
            ];
            if (in_array($out['match'], ['name', 'custom_field'], true)) {
                $out['operator'] = in_array($s['operator'] ?? null, RuleEvaluator::OPERATORS, true) ? $s['operator'] : ($out['match'] === 'name' ? 'contains' : 'is');
            }
            if ($out['match'] === 'custom_field') {
                $out['field'] = is_string($s['field'] ?? null) ? $s['field'] : '';
            }
            if ($withQuantities) {
                foreach (['per', 'quantity', 'ratio'] as $k) {
                    if (isset($s[$k]) && is_numeric($s[$k])) {
                        $out[$k] = $k === 'ratio' ? round((float) $s[$k], 4) : min(10000, (float) $s[$k]);
                    }
                }
            }

            return $out;
        };

        return [
            'scope' => $selector($d['scope'] ?? [], false),
            'check' => is_string($d['check'] ?? null) ? $d['check'] : null,
            'requirement' => $selector($d['requirement'] ?? [], true),
            'basis' => ($d['basis'] ?? 'pull') === 'required' ? 'required' : 'pull',
            'message' => mb_substr(is_string($d['message'] ?? null) ? $d['message'] : '', 0, 500),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'severity' => ['required', Rule::in(RuleEvaluator::SEVERITIES)],
            'is_active' => ['boolean'],
            'definition' => ['required', 'array'],
        ]);
        $definition = $this->cleanDefinition($data['definition']);
        $errors = $this->evaluator->validateDefinition($definition);
        if ($errors) {
            throw ValidationException::withMessages(['definition' => $errors]);
        }

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'severity' => $data['severity'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'definition' => $definition,
        ];
    }

    /** Category/product id → name maps for human-readable rule summaries. */
    private function labelMaps(array $definitions): array
    {
        $ids = ['category' => [], 'product' => []];
        foreach ($definitions as $d) {
            foreach ([$d['scope'] ?? [], $d['requirement'] ?? []] as $s) {
                if (isset($ids[$s['match'] ?? ''])) {
                    array_push($ids[$s['match']], ...array_map('intval', (array) ($s['values'] ?? [])));
                }
            }
        }

        return [
            'category' => Category::query()->whereIn('id', array_unique($ids['category']))->pluck('name', 'id')->all(),
            'product' => Product::query()->whereIn('id', array_unique($ids['product']))->pluck('name', 'id')->all(),
        ];
    }

    private function summary(array $d, array $labels): string
    {
        $describe = function (array $s) use ($labels) {
            $values = (array) ($s['values'] ?? []);
            $names = match ($s['match'] ?? null) {
                'category', 'product' => array_map(fn ($v) => $labels[$s['match']][(int) $v] ?? '#'.$v, $values),
                default => $values,
            };
            $list = implode(' or ', array_map(fn ($n) => '“'.$n.'”', $names));

            return match ($s['match'] ?? null) {
                'any' => 'any item',
                'category' => 'items in '.$list,
                'tag' => 'items tagged '.$list,
                'product' => $list,
                'name' => 'items whose name '.(($s['operator'] ?? 'contains') === 'is' ? 'is ' : 'contains ').$list,
                'custom_field' => 'items where '.($s['field'] ?? '?').' '.(($s['operator'] ?? 'is') === 'contains' ? 'contains ' : 'is ').$list,
                default => 'items',
            };
        };
        $scope = $describe($d['scope'] ?? []);
        $req = $d['requirement'] ?? [];

        return 'When a job contains '.$scope.' → '.match ($d['check'] ?? null) {
            'requires' => 'require '.(float) ($req['quantity'] ?? 1).' × '.$describe($req).' per '.((float) ($req['per'] ?? 1) == 1 ? 'item' : (float) $req['per'].' items'),
            'requires_any' => 'require at least '.(int) ($req['quantity'] ?? 1).' × '.$describe($req).' on the job',
            'max_quantity' => 'allow at most '.(int) ($req['quantity'] ?? 0),
            'min_spares' => isset($req['ratio']) ? 'carry '.round((float) $req['ratio'] * 100).'% spares' : 'carry '.(int) ($req['quantity'] ?? 0).' spare(s) per line',
            default => 'check',
        };
    }
}

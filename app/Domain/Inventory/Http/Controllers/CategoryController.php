<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Services\HierarchyOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly HierarchyOptions $hierarchy) {}

    public function index(): Response
    {
        Gate::authorize('inventory.categories.view');

        $props = DB::transaction(function () {
            $version = DB::table('category_tree_version')->where('id', 1)->lockForUpdate()->value('version');
            $counts = Category::query()->withCount('products')->get()->keyBy('id');
            $options = $this->hierarchy->categories();

            return [
                'categories' => array_map(function (array $option) use ($counts) {
                    $c = $counts[$option['value']];

                    return [
                        'id' => $c->id,
                        'parent_id' => $c->parent_id,
                        'name' => $c->name,
                        'path' => $option['label'],
                        'depth' => $option['depth'],
                        'slug' => $c->slug,
                        'color' => $c->color,
                        'icon' => $c->icon,
                        'sort_order' => $c->sort_order,
                        'products_count' => $c->products_count,
                    ];
                }, $options),
                'parentOptions' => $options,
                'treeVersion' => (int) $version,
            ];
        });

        return Inertia::render('Inventory/Categories/Index', $props);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.categories.create');
        DB::transaction(function () use ($request) {
            $this->lockTree();
            $data = $this->validated($request);
            $data['slug'] = $this->uniqueSlug($data['name']);
            Category::query()->create($data);
        });

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('inventory.categories.edit');
        DB::transaction(function () use ($request, $category) {
            $version = $this->lockTree();
            $category = Category::query()->findOrFail($category->id);
            $data = $this->validated($request);
            if (isset($data['version']) && (int) $data['version'] !== $version - 1) {
                throw ValidationException::withMessages(['version' => 'Categories changed since this form was opened. Close it and try again.']);
            }
            unset($data['version']);
            if ($this->hierarchy->wouldCycle(Category::class, $category->id, $data['parent_id'] ?? null)) {
                throw ValidationException::withMessages(['parent_id' => 'A category cannot be placed inside itself or one of its sub-categories.']);
            }
            if ($data['name'] !== $category->name) {
                $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
            }
            $category->update($data);
        });

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('inventory.categories.delete');
        $deleted = DB::transaction(function () use ($category) {
            $this->lockTree();
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);
            // Use a locking read after the category lock so MySQL sees assignments
            // committed while this transaction was waiting, even under repeatable read.
            if ($category->products()->lockForUpdate()->first(['id'])) {
                return false;
            }
            Category::query()->where('parent_id', $category->id)->update(['parent_id' => $category->parent_id]);
            $category->delete();

            return true;
        });

        if (! $deleted) {
            return back()->with('error', 'Move the products in this category before deleting it.');
        }

        return back()->with('success', 'Category deleted.');
    }

    public function move(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.categories.edit');

        DB::transaction(function () use ($request) {
            $version = $this->lockTree();
            $data = $request->validate([
                'id' => ['required', 'integer', 'exists:categories,id'],
                'parent_id' => ['present', 'nullable', 'integer', 'exists:categories,id'],
                'before_id' => ['present', 'nullable', 'integer', 'exists:categories,id'],
                'version' => ['required', 'integer', 'min:0'],
            ]);
            if ((int) $data['version'] !== $version - 1) {
                throw ValidationException::withMessages(['move' => 'Categories changed since this page was loaded. Refresh and try again.']);
            }
            $category = Category::query()->findOrFail($data['id']);
            $parentId = $data['parent_id'] === null ? null : (int) $data['parent_id'];
            $beforeId = $data['before_id'] === null ? null : (int) $data['before_id'];
            if ($this->hierarchy->wouldCycle(Category::class, $category->id, $parentId)) {
                throw ValidationException::withMessages(['move' => 'A category cannot be placed inside itself or one of its sub-categories.']);
            }
            $siblings = Category::query()->where('parent_id', $parentId)->whereKeyNot($category->id)
                ->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
            $index = $beforeId === null ? $siblings->count() : $siblings->search(fn ($sibling) => $sibling->id === $beforeId);
            if ($index === false) {
                throw ValidationException::withMessages(['move' => 'The target must be another category in the destination group.']);
            }
            $siblings->splice($index, 0, [$category]);
            foreach ($siblings as $position => $sibling) {
                if ($sibling->id === $category->id) {
                    $sibling->parent_id = $parentId;
                }
                $sibling->sort_order = $position;
                if ($sibling->isDirty()) {
                    $sibling->save();
                }
            }
        });

        return back()->with('success', 'Category moved.');
    }

    /** Serialize every category mutation, including writes on an empty tree. */
    private function lockTree(): int
    {
        DB::table('category_tree_version')->where('id', 1)->increment('version');

        return (int) DB::table('category_tree_version')->where('id', 1)->value('version');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);
        if ($request->routeIs('inventory.categories.store')) {
            unset($data['version']);
        }
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Category::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}

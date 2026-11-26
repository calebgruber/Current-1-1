<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Services\HierarchyOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly HierarchyOptions $hierarchy) {}

    public function index(): Response
    {
        Gate::authorize('inventory.categories.view');

        $counts = Category::query()->withCount('products')->get()->keyBy('id');
        $options = $this->hierarchy->categories();

        return Inertia::render('Inventory/Categories/Index', [
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.categories.create');
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        Category::query()->create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('inventory.categories.edit');
        $data = $this->validated($request);

        if ($this->hierarchy->wouldCycle(Category::class, $category->id, $data['parent_id'] ?? null)) {
            return back()->withErrors(['parent_id' => 'A category cannot be placed inside itself or one of its sub-categories.']);
        }
        if ($data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }
        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('inventory.categories.delete');
        if ($category->products()->exists()) {
            return back()->with('error', 'Move the products in this category before deleting it.');
        }
        // children are re-parented to the deleted category's parent
        Category::query()->where('parent_id', $category->id)->update(['parent_id' => $category->parent_id]);
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
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

<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Inventory\Http\Requests\ProductBulkRequest;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductBulkController extends Controller
{
    public function __invoke(ProductBulkRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            if (isset($data['changes']['category_id']) && ! Category::query()
                ->whereKey($data['changes']['category_id'])->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['changes.category_id' => 'The selected category no longer exists.']);
            }

            $products = Product::query()->whereKey($data['product_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($products->count() !== count($data['product_ids'])) {
                throw ValidationException::withMessages(['product_ids' => 'One or more selected products no longer exist.']);
            }

            foreach ($products as $product) {
                $product->fill($data['changes']);
                if ($product->isDirty()) {
                    $product->save();
                }
            }
        });

        return back()->with('success', 'Selected products updated.');
    }
}

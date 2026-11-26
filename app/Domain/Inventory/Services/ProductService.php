<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductImage;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Persists products together with their tags, accessories, stock and images. */
class ProductService
{
    public const IMAGE_DIRECTORY = 'product-images';

    /**
     * @param  array<string, mixed>  $data  validated attributes (may include tags, accessories, stock)
     */
    public function save(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $attributes = collect($data)->only([
                'name', 'sku', 'category_id', 'manufacturer_id', 'model', 'description', 'tracking',
                'replacement_cost', 'rental_price', 'weight_kg', 'barcode', 'default_location_id', 'is_active',
            ])->all();

            if (isset($attributes['category_id']) && ! Category::query()->whereKey($attributes['category_id'])->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['category_id' => 'The selected category no longer exists.']);
            }

            if (array_key_exists('metadata', $data)) {
                $attributes['metadata'] = $this->normalizeMetadata($data['metadata'] ?? []);
            }

            $product->fill($attributes)->save();

            if (array_key_exists('tags', $data)) {
                $this->syncTags($product, $data['tags'] ?? []);
            }
            if (array_key_exists('accessories', $data)) {
                $this->syncAccessories($product, $data['accessories'] ?? []);
            }
            if (array_key_exists('alternatives', $data)) {
                $product->alternatives()->sync(collect($data['alternatives'] ?? [])->pluck('product_id')->all());
            }
            if (! empty($data['stock']) && ! $product->isSerialized()) {
                foreach ($data['stock'] as $row) {
                    $this->setStock($product, $row);
                }
            }

            return $product;
        });
    }

    /** @param list<array{key?: string, value?: mixed}>|array<string, mixed> $metadata */
    public function normalizeMetadata(array $metadata): ?array
    {
        $out = [];
        if (array_is_list($metadata)) {
            foreach ($metadata as $row) {
                $key = trim((string) ($row['key'] ?? ''));
                if ($key !== '') {
                    $out[$key] = (string) ($row['value'] ?? '');
                }
            }
        } else {
            foreach ($metadata as $key => $value) {
                if (trim((string) $key) !== '') {
                    $out[trim((string) $key)] = is_scalar($value) ? (string) $value : '';
                }
            }
        }

        return $out ?: null;
    }

    /** @param list<string> $names */
    public function syncTags(Product $product, array $names): void
    {
        $ids = collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->map(fn (string $name) => $this->resolveTag($name)->id)
            ->unique()
            ->all();

        $product->tags()->sync($ids);
    }

    private function resolveTag(string $name): Tag
    {
        $existing = Tag::query()->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        $base = substr(Str::slug($name) ?: 'tag', 0, 200);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $slug = $attempt === 0 ? $base : $base.'-'.($attempt === 1 ? substr(hash('sha256', $name), 0, 12) : Str::random(12));
            try {
                return Tag::query()->createOrFirst(['name' => $name], ['slug' => $slug]);
            } catch (UniqueConstraintViolationException $e) {
                $existing = Tag::query()->where('name', $name)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
                if ($attempt === 9) {
                    throw $e;
                }
            }
        }
    }

    /** @param list<array{accessory_product_id: int, quantity: float|int|string, type?: string, auto_add?: bool, notes?: ?string}> $rows */
    public function syncAccessories(Product $product, array $rows): void
    {
        $keep = [];
        foreach ($rows as $row) {
            $accessoryId = (int) $row['accessory_product_id'];
            if ($accessoryId === $product->id) {
                continue;
            }
            $keep[] = $accessoryId;
            $product->accessories()->updateOrCreate(
                ['accessory_product_id' => $accessoryId],
                [
                    'quantity' => (float) $row['quantity'],
                    'type' => $row['type'] ?? 'required',
                    'auto_add' => (bool) ($row['auto_add'] ?? true),
                    'notes' => $row['notes'] ?? null,
                ],
            );
        }
        $product->accessories()->whereNotIn('accessory_product_id', $keep ?: [0])->delete();
    }

    /**
     * Sets the stock buckets of a bulk product at one location (audited).
     *
     * @param  array{location_id: ?int, quantity: int, quantity_maintenance?: int, quantity_damaged?: int, quantity_lost?: int}  $row
     */
    public function setStock(Product $product, array $row, ?string $reason = null): StockLevel
    {
        $level = StockLevel::query()->firstOrNew(['product_id' => $product->id, 'location_id' => $row['location_id'] ?? null]);
        $old = $level->exists ? $level->only(['quantity', 'quantity_maintenance', 'quantity_damaged', 'quantity_lost']) : [];
        $level->fill([
            'quantity' => (int) $row['quantity'],
            'quantity_maintenance' => (int) ($row['quantity_maintenance'] ?? 0),
            'quantity_damaged' => (int) ($row['quantity_damaged'] ?? 0),
            'quantity_lost' => (int) ($row['quantity_lost'] ?? 0),
        ])->save();

        AuditLog::record('stock.adjusted', $product, $old, $level->only(['location_id', 'quantity', 'quantity_maintenance', 'quantity_damaged', 'quantity_lost']), $reason ? mb_substr($reason, 0, 255) : 'Stock adjusted');

        return $level;
    }

    public function addImage(Product $product, UploadedFile $file, ?string $caption = null): ProductImage
    {
        $path = $file->store(self::IMAGE_DIRECTORY.'/'.$product->id, 'public');
        $image = $product->images()->create([
            'path' => $path,
            'caption' => $caption,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);

        if (! $product->image_path) {
            $product->forceFill(['image_path' => $path])->save();
        }

        return $image;
    }

    public function removeImage(Product $product, ProductImage $image): void
    {
        if (str_starts_with($image->path, self::IMAGE_DIRECTORY.'/') && ! str_contains($image->path, '..')) {
            Storage::disk('public')->delete($image->path);
        }
        $image->delete();

        if ($product->image_path === $image->path) {
            $product->forceFill(['image_path' => $product->images()->value('path')])->save();
        }
    }

    public static function imageUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}

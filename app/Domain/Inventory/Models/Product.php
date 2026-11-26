<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Assets\Models\Asset;
use App\Domain\Inventory\Enums\TrackingType;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Reservations\Models\Reservation;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\HasCustomFields;
use App\Support\Concerns\Searchable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, HasCustomFields, HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'name', 'sku', 'category_id', 'manufacturer_id', 'model', 'description', 'tracking',
        'replacement_cost', 'rental_price', 'weight_kg', 'barcode', 'image_path',
        'default_location_id', 'metadata', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tracking' => TrackingType::class,
            'replacement_cost' => 'decimal:2',
            'rental_price' => 'decimal:2',
            'weight_kg' => 'decimal:3',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function accessories(): HasMany
    {
        return $this->hasMany(ProductAccessory::class);
    }

    public function accessoryProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_accessories', 'product_id', 'accessory_product_id')
            ->withPivot(['quantity', 'type', 'auto_add', 'notes']);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function jobItems(): HasMany
    {
        return $this->hasMany(JobItem::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function isSerialized(): bool
    {
        return $this->tracking === TrackingType::Serialized;
    }

    public function toSearchEntry(): ?array
    {
        return [
            'title' => $this->name,
            'subtitle' => trim(implode(' · ', array_filter([$this->manufacturer?->name, $this->model, $this->category?->name]))),
            'keywords' => $this->description ? mb_substr(strip_tags($this->description), 0, 1000) : null,
            'codes' => [$this->sku, $this->barcode],
            'url' => '/inventory/products/'.$this->id,
        ];
    }
}

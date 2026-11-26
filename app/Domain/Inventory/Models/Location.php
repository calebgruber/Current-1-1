<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Assets\Models\Asset;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\HasCustomFields;
use App\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use Auditable, HasCustomFields, Searchable;

    protected $fillable = ['parent_id', 'name', 'code', 'type', 'barcode', 'address', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** Full path such as "Main Warehouse › Aisle 3 › Shelf B". */
    public function path(): string
    {
        $parts = [$this->name];
        $node = $this;
        $guard = 0;
        while ($node->parent_id && $guard++ < 16) {
            $node = $node->parent;
            if (! $node) {
                break;
            }
            array_unshift($parts, $node->name);
        }

        return implode(' › ', $parts);
    }

    public function toSearchEntry(): ?array
    {
        return [
            'title' => $this->name,
            'subtitle' => ucfirst($this->type).($this->code ? ' · '.$this->code : ''),
            'keywords' => $this->address,
            'codes' => [$this->code, $this->barcode],
            'url' => '/inventory/locations/'.$this->id,
        ];
    }
}

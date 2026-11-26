<?php

namespace App\Domain\CustomFields\Models;

use App\Domain\CustomFields\Enums\CustomFieldType;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFieldDefinition extends Model
{
    use Auditable;

    /** Entity types (morph aliases) that support custom fields. */
    public const ENTITY_TYPES = [
        'product' => 'Products',
        'asset' => 'Assets',
        'job' => 'Jobs',
        'reservation' => 'Reservations',
        'document' => 'Documents',
        'contact' => 'Contacts',
        'maintenance' => 'Maintenance records',
        'location' => 'Locations',
    ];

    protected $fillable = [
        'entity_type', 'key', 'label', 'type', 'group', 'help_text', 'options', 'validation',
        'is_required', 'show_in_list', 'is_searchable', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'validation' => 'array',
            'is_required' => 'boolean',
            'show_in_list' => 'boolean',
            'is_searchable' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'definition_id');
    }

    /** Plain array consumed by the pure CustomFieldValidator. */
    public function toValidatorDefinition(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->is_required,
            'options' => $this->options ?? [],
            'validation' => $this->validation ?? [],
        ];
    }
}

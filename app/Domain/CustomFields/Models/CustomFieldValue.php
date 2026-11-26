<?php

namespace App\Domain\CustomFields\Models;

use App\Domain\CustomFields\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomFieldValue extends Model
{
    protected $fillable = ['definition_id', 'entity_type', 'entity_id', 'value_text', 'value_number', 'value_date', 'value_json'];

    protected function casts(): array
    {
        return ['value_json' => 'array', 'value_date' => 'datetime', 'value_number' => 'float'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, mixed> column => value, other columns nulled */
    public static function columnsFor(CustomFieldType $type, mixed $value): array
    {
        $columns = ['value_text' => null, 'value_number' => null, 'value_date' => null, 'value_json' => null];
        if ($value === null || $value === '') {
            return $columns;
        }
        $column = $type->storageColumn();
        $columns[$column] = match ($column) {
            'value_number' => (float) $value,
            'value_json' => is_array($value) ? $value : ['value' => $value],
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };

        return $columns;
    }

    public function typedValue(): mixed
    {
        $type = $this->definition?->type;

        return match ($type) {
            CustomFieldType::Number, CustomFieldType::Currency => $this->value_number,
            CustomFieldType::User, CustomFieldType::Asset => $this->value_number !== null ? (int) $this->value_number : null,
            CustomFieldType::Date => $this->value_date?->toDateString(),
            CustomFieldType::DateTime => $this->value_date?->toIso8601String(),
            CustomFieldType::Checkbox => (bool) ($this->value_json['value'] ?? false),
            CustomFieldType::MultiSelect, CustomFieldType::Relation, CustomFieldType::File => $this->value_json,
            default => $this->value_text,
        };
    }
}

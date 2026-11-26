<?php

namespace App\Support\Concerns;

use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\CustomFields\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Attaches administrator-defined custom fields to a model.
 * Values are stored in custom_field_values (typed columns) so they can be
 * filtered and aggregated by the report builder.
 */
trait HasCustomFields
{
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'entity');
    }

    /** @return array<string, mixed> key => value */
    public function customFields(): array
    {
        $values = $this->relationLoaded('customFieldValues')
            ? $this->customFieldValues
            : $this->customFieldValues()->with('definition')->get();

        $result = [];
        foreach ($values as $value) {
            if ($value->definition) {
                $result[$value->definition->key] = $value->typedValue();
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $values key => value (already validated) */
    public function setCustomFields(array $values): void
    {
        $definitions = CustomFieldDefinition::query()
            ->where('entity_type', $this->getMorphClass())
            ->whereIn('key', array_keys($values))
            ->get()
            ->keyBy('key');

        foreach ($values as $key => $value) {
            $definition = $definitions->get($key);
            if (! $definition) {
                continue;
            }
            $record = CustomFieldValue::query()->firstOrNew([
                'definition_id' => $definition->id,
                'entity_type' => $this->getMorphClass(),
                'entity_id' => $this->getKey(),
            ]);
            $record->fill(CustomFieldValue::columnsFor($definition->type, $value));
            $record->save();
        }
    }
}

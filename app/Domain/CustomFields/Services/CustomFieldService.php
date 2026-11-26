<?php

namespace App\Domain\CustomFields\Services;

use App\Domain\Assets\Models\Asset;
use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Application-facing custom field API used by every module controller:
 *
 *   'customFieldSchema' => $fields->schema('product'),          // for <CustomFieldInputs>
 *   'customFields'      => $product->customFields(),            // current values
 *   $clean = $fields->validate('product', $request->input('custom_fields', []));
 *   $fields->save($product, $clean);
 *
 * Validation errors are thrown keyed as "custom_fields.{key}" so Inertia
 * forms can show them next to the matching input.
 */
class CustomFieldService
{
    public const FILE_DIRECTORY = 'custom-fields';

    /** @var array<string, Collection<int, CustomFieldDefinition>> */
    private array $definitions = [];

    /** @return Collection<int, CustomFieldDefinition> */
    public function definitions(string $entity): Collection
    {
        return $this->definitions[$entity] ??= CustomFieldDefinition::query()
            ->where('entity_type', $entity)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** Field metadata for the frontend form renderer. */
    public function schema(string $entity): array
    {
        return $this->definitions($entity)->map(fn (CustomFieldDefinition $d) => [
            'key' => $d->key,
            'label' => $d->label,
            'type' => $d->type->value,
            'group' => $d->group,
            'help_text' => $d->help_text,
            'options' => $d->options ?? [],
            'validation' => $d->validation ?? [],
            'required' => $d->is_required,
            'show_in_list' => $d->show_in_list,
        ])->values()->all();
    }

    /** Columns flagged "show in list" for index tables. */
    public function listColumns(string $entity): array
    {
        return array_values(array_filter($this->schema($entity), fn ($f) => $f['show_in_list']));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> cleaned values
     *
     * @throws ValidationException
     */
    public function validate(string $entity, array $input, bool $partial = false): array
    {
        $definitions = $this->definitions($entity)->map->toValidatorDefinition()->all();
        [$errors, $clean] = (new CustomFieldValidator($this->existenceChecker()))->validate($definitions, $input, $partial);

        foreach ($clean as $key => $value) {
            if (is_array($value) && isset($value['path']) && ! str_starts_with((string) $value['path'], self::FILE_DIRECTORY.'/')) {
                $errors[$key] = 'Invalid file reference.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn ($message, $key) => ["custom_fields.{$key}" => $message])->all()
            );
        }

        return $clean;
    }

    public function save(Model $model, array $clean): void
    {
        if ($clean !== [] && method_exists($model, 'setCustomFields')) {
            $model->setCustomFields($clean);
        }
    }

    /** Values for many models at once (avoids N+1 on index pages): [id => [key => value]]. */
    public function valuesForMany(string $entity, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        CustomFieldValue::query()
            ->with('definition')
            ->where('entity_type', $entity)
            ->whereIn('entity_id', $ids)
            ->get()
            ->each(function (CustomFieldValue $v) use (&$out) {
                if ($v->definition) {
                    $out[$v->entity_id][$v->definition->key] = $v->typedValue();
                }
            });

        return $out;
    }

    public function existenceChecker(): callable
    {
        return function (string $type, int $id): bool {
            return match ($type) {
                'user' => User::query()->whereKey($id)->exists(),
                'asset' => Asset::query()->whereKey($id)->exists(),
                default => ($class = Relation::getMorphedModel($type)) !== null && $class::query()->whereKey($id)->exists(),
            };
        };
    }
}

<?php

namespace App\Domain\CustomFields\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case LongText = 'long_text';
    case Number = 'number';
    case Currency = 'currency';
    case Date = 'date';
    case DateTime = 'datetime';
    case Checkbox = 'checkbox';
    case Dropdown = 'dropdown';
    case MultiSelect = 'multi_select';
    case File = 'file';
    case User = 'user';
    case Asset = 'asset';
    case Url = 'url';
    case Relation = 'relation';

    public function label(): string
    {
        return match ($this) {
            self::LongText => 'Long text',
            self::DateTime => 'Date & time',
            self::MultiSelect => 'Multi-select',
            self::File => 'File upload',
            self::User => 'User reference',
            self::Asset => 'Asset reference',
            self::Url => 'URL',
            self::Relation => 'Relation (any record)',
            default => ucfirst($this->value),
        };
    }

    /** Which typed column stores the value. */
    public function storageColumn(): string
    {
        return match ($this) {
            self::Number, self::Currency, self::User, self::Asset => 'value_number',
            self::Date, self::DateTime => 'value_date',
            self::Checkbox, self::MultiSelect, self::Relation, self::File => 'value_json',
            default => 'value_text',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}

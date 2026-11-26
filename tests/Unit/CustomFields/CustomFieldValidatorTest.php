<?php

namespace Tests\Unit\CustomFields;

use App\Domain\CustomFields\Enums\CustomFieldType;
use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Domain\CustomFields\Services\CustomFieldValidator;
use PHPUnit\Framework\TestCase;

class CustomFieldValidatorTest extends TestCase
{
    public function test_close_decimal_changes_are_dirty_without_float_rounding(): void
    {
        $record = new CustomFieldValue;
        $record->setRawAttributes(['value_number' => '99999999999999.9998'], true);
        $record->fill(CustomFieldValue::columnsFor(CustomFieldType::Number, '99999999999999.9999'));
        $this->assertTrue($record->isDirty('value_number'));
        $this->assertSame('99999999999999.9999', $record->getDirty()['value_number']);
        $record->setRawAttributes(['value_number' => '1.0000'], true);
        $record->fill(['value_number' => '1']);
        $this->assertFalse($record->isDirty('value_number'));
    }

    public function test_decimal_values_are_read_without_float_conversion(): void
    {
        $record = new CustomFieldValue;
        $record->setRawAttributes(['value_number' => '99999999999999.9999']);
        $record->setRelation('definition', new CustomFieldDefinition(['type' => 'number']));
        $this->assertSame('99999999999999.9999', $record->typedValue());
        $record->setRawAttributes(['value_number' => 100000000000000.0, 'value_text' => '99999999999999.9999']);
        $this->assertSame('99999999999999.9999', $record->typedValue());
        $record->setRawAttributes(['value_number' => '99999999999999.9999']);
        $record->setRelation('definition', new CustomFieldDefinition(['type' => 'currency']));
        $this->assertSame('99999999999999.9999', $record->typedValue());
        $record->setRawAttributes(['value_number' => '12.0000']);
        $record->setRelation('definition', new CustomFieldDefinition(['type' => 'user']));
        $this->assertSame(12, $record->typedValue());
        $record->setRawAttributes(['value_number' => null]);
        $record->setRelation('definition', new CustomFieldDefinition(['type' => 'number']));
        $this->assertNull($record->typedValue());
    }

    public function test_decimal_values_are_checked_without_losing_precision(): void
    {
        $validator = new CustomFieldValidator;
        $definition = [['key' => 'amount', 'label' => 'Amount', 'type' => 'number']];
        foreach ([
            '99999999999999.9999' => '99999999999999.9999',
            '-99999999999999.9999' => '-99999999999999.9999',
            '0.0001000' => '0.0001',
            '1e-4' => '0.0001',
            '1.23e2' => '123',
        ] as $input => $expected) {
            [$errors, $clean] = $validator->validate($definition, ['amount' => $input]);
            $this->assertSame([], $errors);
            $this->assertSame($expected, $clean['amount']);
            $this->assertSame($expected, CustomFieldValue::columnsFor(CustomFieldType::Number, $clean['amount'])['value_number']);
        }
        foreach (['100000000000000', '-100000000000000', '0.00001', '1e-5', '1e100', '1e-100'] as $input) {
            [$errors] = $validator->validate($definition, ['amount' => $input]);
            $this->assertArrayHasKey('amount', $errors, $input);
        }
    }

    public function test_currency_rounding_cannot_overflow_and_other_types_still_validate(): void
    {
        $validator = new CustomFieldValidator;
        $definition = [
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'currency'],
            ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['key' => 'active', 'label' => 'Active', 'type' => 'checkbox'],
        ];
        [$errors, $clean] = $validator->validate($definition, ['amount' => '-1.235', 'name' => ' Show ', 'active' => true]);
        $this->assertSame([], $errors);
        $this->assertSame(['amount' => '-1.24', 'name' => 'Show', 'active' => true], $clean);
        [$errors] = $validator->validate($definition, ['amount' => '99999999999999.9999']);
        $this->assertArrayHasKey('amount', $errors);
        [$errors] = $validator->validate($definition, ['amount' => '0.00001']);
        $this->assertArrayHasKey('amount', $errors);
    }

    public function test_numeric_limits_are_compared_exactly(): void
    {
        $validator = new CustomFieldValidator;
        $def = [['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'validation' => [
            'min' => '99999999999999.9997', 'max' => '99999999999999.9998',
        ]]];
        [$errors] = $validator->validate($def, ['amount' => '99999999999999.9999']);
        $this->assertArrayHasKey('amount', $errors);
        [$errors] = $validator->validate($def, ['amount' => '99999999999999.9996']);
        $this->assertArrayHasKey('amount', $errors);
        [$errors] = $validator->validate($def, ['amount' => '99999999999999.9998']);
        $this->assertSame([], $errors);

        $def[0]['validation'] = ['min' => '-99999999999999.9998', 'max' => '-99999999999999.9997'];
        [$errors] = $validator->validate($def, ['amount' => '-99999999999999.9999']);
        $this->assertArrayHasKey('amount', $errors);
        [$errors] = $validator->validate($def, ['amount' => '-99999999999999.9997']);
        $this->assertSame([], $errors);
        $def[0]['validation'] = ['min' => '1e-4', 'max' => '1e2'];
        [$errors] = $validator->validate($def, ['amount' => '0.0001']);
        $this->assertSame([], $errors);
        $def[0]['validation'] = ['max' => '1e9999999999'];
        [$errors] = $validator->validate($def, ['amount' => '1']);
        $this->assertArrayHasKey('amount', $errors);
    }
}

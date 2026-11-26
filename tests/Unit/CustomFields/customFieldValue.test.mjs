import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';

const inputs = readFileSync(new URL('../../../resources/views/partials/custom-fields.blade.php', import.meta.url), 'utf8');

test('custom decimal fields use native numeric inputs without rounding values in JavaScript', () => {
    assert.match(inputs, /'number', 'currency' => 'number'/);
    assert.match(inputs, /step="any"/);
    assert.match(inputs, /is_scalar\(\$value\) \? \$value : ''/);
    assert.doesNotMatch(inputs, /parseFloat|Number\(\$value\)/);
});

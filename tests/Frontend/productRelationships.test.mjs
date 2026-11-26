import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const form = readFileSync(new URL('../../resources/views/inventory/products/form.blade.php', import.meta.url), 'utf8');

test('product relationship form preserves unchanged associations and submits explicit clears', () => {
    assert.match(form, /prepareRelationships\(\$el\)/);
    assert.match(form, /initialTags:/);
    assert.match(form, /initialAccessories:/);
    assert.match(form, /initialAlternatives:/);
    assert.match(form, /if \(isUnchanged\) form\.querySelectorAll/);
    assert.match(form, /else if \(empty\)/);
});

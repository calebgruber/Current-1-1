import { test } from 'node:test';
import assert from 'node:assert/strict';
import { relationshipPayload, relationshipSnapshot } from '../../resources/js/Modules/Inventory/Pages/Products/relationshipPayload.ts';

const initial = () => ({
    name: 'Console',
    tags: ['Audio'],
    accessories: [{ accessory_product_id: 1, quantity: 1, type: 'required', auto_add: true, notes: null }],
    alternatives: [{ product_id: 2 }],
});

test('unchanged edit omits relationships, including normalized quantities and empty notes', () => {
    const data = initial();
    const snapshot = relationshipSnapshot(data);
    data.accessories[0].quantity = '1.00';
    data.accessories[0].notes = '';
    assert.deepEqual(relationshipPayload(data, snapshot, true, true), { name: 'Console' });
});

test('changed relationships, including explicit clears, are submitted independently', () => {
    const data = initial();
    const snapshot = relationshipSnapshot(data);
    data.tags = [];
    data.accessories[0].quantity = 2;
    data.alternatives = [];
    assert.deepEqual(relationshipPayload(data, snapshot, true, true), data);
    assert.deepEqual(relationshipPayload(data, snapshot, true, false), { name: 'Console', tags: [] });
});

test('creation sends empty relationships and reordering is not a change', () => {
    const data = initial();
    data.tags.push('Live');
    const snapshot = relationshipSnapshot(data);
    data.tags.reverse();
    assert.deepEqual(relationshipPayload(data, snapshot, true, true), { name: 'Console' });
    assert.deepEqual(relationshipPayload(data, snapshot, false, true), data);
});

import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import { parse as parseSfc } from '@vue/compiler-sfc';
import { baseParse, NodeTypes } from '@vue/compiler-dom';
import { customFieldValue, formatCustomDecimal } from '../../../resources/js/lib/customFieldValue.ts';

test('custom fields ignore inherited properties and read existing own values', () => {
    assert.equal(customFieldValue({}, 'constructor'), undefined);
    assert.equal(customFieldValue({}, 'toString'), undefined);
    assert.equal(Boolean(customFieldValue({}, 'constructor')), false);
    const stored = JSON.parse('{"constructor":false,"__proto__":"saved"}');
    assert.equal(customFieldValue(stored, 'constructor'), false);
    assert.equal(customFieldValue(stored, '__proto__'), 'saved');
    assert.equal(customFieldValue({ ...stored, constructor: true }, 'constructor'), true);
});

test('decimal display preserves all digits from the server', () => {
    const number = '99999999999999.9999';
    const currency = '99999999999999.99';
    assert.equal(formatCustomDecimal(number), number);
    assert.equal(formatCustomDecimal(currency, 'USD'), `${currency} USD`);
    const model = JSON.parse(JSON.stringify({ number, currency }));
    assert.equal(model.number, number);
    assert.equal(model.currency, currency);
});

test('decimal inputs do not trigger Vue number coercion', () => {
    const source = readFileSync(new URL('../../../resources/js/Components/custom-fields/CustomFieldInputs.vue', import.meta.url), 'utf8');
    const template = parseSfc(source).descriptor.template.content;
    const root = baseParse(template);
    const matches = [];
    const visit = (node) => {
        if (node.type === NodeTypes.ELEMENT && node.tag === 'TextInput') {
            const condition = node.props.find((prop) => prop.type === NodeTypes.DIRECTIVE && prop.name === 'else-if')?.exp?.content;
            if (condition === "f.type === 'number'" || condition === "f.type === 'currency'") matches.push(node);
        }
        for (const child of node.children ?? []) visit(child);
    };
    visit(root);
    assert.equal(matches.length, 2);
    for (const input of matches) {
        const attribute = (name) => input.props.find((prop) => prop.type === NodeTypes.ATTRIBUTE && prop.name === name)?.value?.content;
        assert.equal(attribute('type'), 'text');
        assert.equal(attribute('inputmode'), 'decimal');
    }
});

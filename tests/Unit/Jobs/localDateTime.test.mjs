import { strict as assert } from 'node:assert';
import { spawnSync } from 'node:child_process';
import { test } from 'node:test';

const helper = new URL('../../../resources/js/lib/localDateTime.ts', import.meta.url).href;

for (const [timezone, expected] of [
    ['UTC', '2030-07-01T10:30:00.000Z'],
    ['America/New_York', '2030-07-01T14:30:00.000Z'],
    ['Asia/Kolkata', '2030-07-01T05:00:00.000Z'],
]) {
    test(`datetime-local serializes in ${timezone}`, () => {
        const code = `
            import { localDateTimeToUtc as convert } from ${JSON.stringify(helper)};
            console.log(JSON.stringify([
                convert('2030-07-01T10:30', '', null),
                convert('', '2030-07-01T10:30', '2030-07-01T10:30:00Z'),
                convert('invalid', '', null),
                convert('2024-11-03T01:30', '2024-11-03T01:30', '2024-11-03T06:30:45Z')
            ]));
        `;
        const result = spawnSync(process.execPath, ['--experimental-strip-types', '--input-type=module', '-e', code], {
            env: { ...process.env, TZ: timezone }, encoding: 'utf8',
        });
        assert.equal(result.status, 0, result.stderr);
        assert.deepEqual(JSON.parse(result.stdout), [expected, '', 'invalid', '2024-11-03T06:30:45.000Z']);
    });
}

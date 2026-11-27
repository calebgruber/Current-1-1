// Copies the Tabler UI and Tabler Icons distribution files into public/assets/vendor
// so the app can be uploaded to shared hosting without a build step.
import { cpSync, mkdirSync, readdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('..', import.meta.url).pathname;
const out = join(root, 'public/assets/vendor');
rmSync(out, { recursive: true, force: true });
mkdirSync(join(out, 'tabler'), { recursive: true });
mkdirSync(join(out, 'tabler-icons/fonts'), { recursive: true });

const core = join(root, 'node_modules/@tabler/core/dist');
cpSync(join(core, 'css/tabler.min.css'), join(out, 'tabler/tabler.min.css'));
cpSync(join(core, 'js/tabler.min.js'), join(out, 'tabler/tabler.min.js'));

const icons = join(root, 'node_modules/@tabler/icons-webfont/dist');
cpSync(join(icons, 'tabler-icons.min.css'), join(out, 'tabler-icons/tabler-icons.min.css'));
for (const file of readdirSync(join(icons, 'fonts'))) {
    // Only the regular-weight subsetted woff2 files are referenced by tabler-icons.min.css.
    if (/^tabler-icons\.[a-z0-9-]+\.woff2$/.test(file)) {
        cpSync(join(icons, 'fonts', file), join(out, 'tabler-icons/fonts', file));
    }
}
console.log('Copied Tabler assets to public/assets/vendor');

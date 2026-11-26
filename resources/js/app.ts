import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';

const appName = document.title || 'StageTrack';

/*
 * Pages live inside their domain module:
 *   'Inventory/Products/Index' → resources/js/Modules/Inventory/Pages/Products/Index.vue
 * Pages use AppLayout unless they declare `defineOptions({ layout: … })` (null = none).
 */
const pages = import.meta.glob<DefineComponent>('./Modules/*/Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: async (name) => {
        const [module, ...rest] = name.split('/');
        const path = `./Modules/${module}/Pages/${rest.length ? rest.join('/') : 'Index'}.vue`;
        const loader = pages[path];
        if (!loader) {
            throw new Error(`Page not found: ${name} (${path})`);
        }
        const page = (await loader()).default as DefineComponent & { layout?: unknown };
        if (page.layout === undefined) {
            page.layout = AppLayout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: { color: '#6366f1', delay: 150 },
});

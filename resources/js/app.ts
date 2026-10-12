import '../css/app.css';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue');

createInertiaApp({
    title: title => `${title} · Mesa de ayuda`,
    resolve: async name => {
        const page = pages[`./pages/${name}.vue`];
        if (!page) throw new Error(`Página no encontrada: ${name}`);
        return (await page()).default;
    },
    setup({ el, App, props, plugin }) { createApp({ render: () => h(App, props) }).use(plugin).mount(el); },
    progress: { color: '#3F5C79' },
});

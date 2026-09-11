import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { t, currentLocale, setLocale } from './i18n';

createInertiaApp({
    title: title => `${title} - Voice Core`,
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.globalProperties.$t = t;
        app.config.globalProperties.$locale = currentLocale;
        app.config.globalProperties.$setLocale = setLocale;
        app.use(plugin).mount(el);
    },
});

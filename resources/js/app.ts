import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue'; // Şablonu içeri aktar

const appName = import.meta.env.VITE_APP_NAME || 'Çevre Takip Sistemi';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue'));
        
        // Login sayfası HARİÇ tüm sayfalara menüyü otomatik ekle
        if (!name.startsWith('auth/')) {
            page.default.layout = page.default.layout || AppLayout;
        }
        
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4f46e5',
    },
});

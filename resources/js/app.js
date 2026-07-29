import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { Flows } from '@codebar-ag/storybook';

/*
 | `Flows` registers every design-system atom globally, so pages write <Button>,
 | <Card>, <DataTable> without per-file imports. Composables and types are still
 | imported by name where a page needs them.
 */

createInertiaApp({
    title: (title) => (title ? `${title} · LLM Gateway` : 'LLM Gateway'),

    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });

        return pages[`./Pages/${name}.vue`];
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(Flows)
            .mount(el);
    },

    // Matches --color-accent from the design tokens.
    progress: { color: '#18181b' },
});

import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            detectTls: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        cors: true,
    },
    build: {
        rolldownOptions: {
            /*
             | apexcharts is an optional peer of the design system: only its
             | <Chart> component reaches for it, and then only through a
             | dynamic import inside onMounted. This app never renders <Chart>,
             | so the package is not installed — but the bare `import(...)` is
             | still in flows.js, and rolldown refuses to leave an unresolvable
             | import alone unless it is told to. Bundling it instead cost an
             | 810 kB chunk, 46% of the build, that no page ever fetched.
             |
             | If a <Chart> is ever added, install apexcharts and drop this
             | line: the import will resolve and be code-split on its own.
             */
            external: ['apexcharts'],
        },
    },
});

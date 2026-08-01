import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            fonts: [
                // Inter for the UI, JetBrains Mono for identifiers (SKU, container,
                // B/L), IBM Plex Sans Arabic for the Arabic/Kurdish invoice locales.
                bunny('Inter', { weights: [400, 500, 600, 700] }),
                bunny('JetBrains Mono', { weights: [400, 500] }),
                bunny('IBM Plex Sans Arabic', { weights: [400, 500, 600] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

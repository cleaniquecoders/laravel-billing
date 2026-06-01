import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Dev-only build for the Testbench workbench demo (see testbench.yaml).
// Run `npm run dev` alongside `php vendor/bin/testbench serve`.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'workbench/resources/css/app.css',
                'workbench/resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});

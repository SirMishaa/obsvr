import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import devtoolsJson from 'vite-plugin-devtools-json';
import vueDevTools from 'vite-plugin-vue-devtools';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        /** Ensure that laravel routes frontend helper is being generated automatically */
        wayfinder(),
        vueDevTools({
            launchEditor: 'phpstorm',
            componentInspector: true,
            appendTo: 'resources/js/app.ts',
        }),
        /** For chrome workspace automatic discovery */
        devtoolsJson(),
    ],
});

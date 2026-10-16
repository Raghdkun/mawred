import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        // Installable app + offline check-in (D70). The service worker sits at the site root so it
        // can serve /attendance offline; it precaches only the built assets.
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: false,
            outDir: 'public',
            base: '/',
            scope: '/',
            buildBase: '/build/',
            manifestFilename: 'manifest.webmanifest',
            manifest: {
                name: 'Mawred',
                short_name: 'Mawred',
                lang: 'en',
                dir: 'auto',
                start_url: '/',
                display: 'standalone',
                background_color: '#F4F8FD',
                theme_color: '#4570C0',
                icons: [
                    { src: '/icons/pwa-icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
                    { src: '/icons/pwa-icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
                    { src: '/icons/pwa-maskable-192.png', sizes: '192x192', type: 'image/png', purpose: 'maskable' },
                    { src: '/icons/pwa-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
                shortcuts: [{ name: 'Check in', url: '/attendance' }],
            },
            workbox: {
                globDirectory: 'public/build',
                // Hashed assets are cached as pages use them (below), not all up front: a phone
                // only downloads what it opens, and /attendance keeps working offline afterwards.
                globPatterns: ['**/*.css'],
                modifyURLPrefix: { '': '/build/' },
                maximumFileSizeToCacheInBytes: 3 * 1024 * 1024,
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: ({ url }) => url.pathname.startsWith('/build/assets/'),
                        handler: 'CacheFirst',
                        options: { cacheName: 'mawred-assets', expiration: { maxEntries: 300, maxAgeSeconds: 60 * 60 * 24 * 60 } },
                    },
                    {
                        urlPattern: ({ request, url }) => request.mode === 'navigate' && url.pathname === '/attendance',
                        handler: 'NetworkFirst',
                        options: { cacheName: 'mawred-check-in', networkTimeoutSeconds: 4 },
                    },
                ],
            },
        }),
    ],
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
                '**/docs/**',
                '**/design/**',
            ],
        },
    },
});

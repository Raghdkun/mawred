import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: false,
        include: ['resources/js/**/*.test.{ts,tsx}', 'tests/js/**/*.test.{ts,tsx}'],
        setupFiles: ['tests/js/setup.ts'],
        css: false,
    },
});

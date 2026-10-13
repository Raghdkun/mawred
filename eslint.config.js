import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import betterTailwind from 'eslint-plugin-better-tailwindcss';
import i18next from 'eslint-plugin-i18next';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

/**
 * Block-axis (top/bottom) classes are direction-agnostic, so only the inline axis (left/right)
 * is enforced as logical properties (ADR-0011). Everything else in the rule's map stays on.
 */
const variant = '(^|:)!?-?';
const blockAxisClasses = [
    `${variant}(p|m|scroll-p|scroll-m)[tby]-.+$`,
    `${variant}(top|bottom|inset-y)-.+$`,
    `${variant}border-[tby](-.+)?$`,
    `${variant}rounded-[tb](-.+)?$`,
    // Sizing is direction-neutral; keep w-/h-/size- rather than inline-/block- sizes.
    `${variant}(min-|max-)?(w|h|size)-.+$`,
];

export default tseslint.config(
    {
        ignores: [
            'vendor',
            'node_modules',
            'public',
            'bootstrap/ssr',
            'storage',
            'design',
            'docs',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'resources/js/types/generated/**',
            'resources/js/types/generated.d.ts',
            'resources/js/components/ui/**',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['**/*.{ts,tsx}'],
        plugins: {
            react,
            'react-hooks': reactHooks,
            'jsx-a11y': jsxA11y,
            'better-tailwindcss': betterTailwind,
            i18next,
        },
        languageOptions: {
            globals: { ...globals.browser },
            parserOptions: { ecmaFeatures: { jsx: true } },
        },
        settings: {
            react: { version: 'detect' },
            'better-tailwindcss': { entryPoint: 'resources/css/app.css' },
        },
        rules: {
            ...react.configs.flat.recommended.rules,
            ...react.configs.flat['jsx-runtime'].rules,
            ...reactHooks.configs.recommended.rules,
            ...jsxA11y.flatConfigs.recommended.rules,
            'react/prop-types': 'off',
            '@typescript-eslint/consistent-type-imports': ['error', { fixStyle: 'inline-type-imports' }],
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],

            // RTL: physical left/right utilities are errors (ADR-0011).
            'better-tailwindcss/enforce-logical-properties': ['error', { ignore: blockAxisClasses }],
            'better-tailwindcss/no-conflicting-classes': 'error',
            'better-tailwindcss/no-unknown-classes': ['error', { ignore: ['^group(/.+)?$', '^peer(/.+)?$', '^toaster$'] }],
            'better-tailwindcss/no-duplicate-classes': 'error',
            'better-tailwindcss/no-deprecated-classes': 'error',

            // All user-facing copy goes through i18next (ADR-0011).
            'i18next/no-literal-string': ['error', { mode: 'jsx-text-only' }],
        },
    },
    {
        files: ['**/*.test.{ts,tsx}', 'tests/js/**'],
        rules: { 'i18next/no-literal-string': 'off' },
    },
    {
        // Single-purpose sign-in pages focus their only field (WCAG-acceptable); dialogs never do.
        files: ['resources/js/pages/auth/**', 'resources/js/pages/setup/claim.tsx'],
        rules: { 'jsx-a11y/no-autofocus': 'off' },
    },
    {
        files: ['*.config.{js,ts}', 'eslint.config.js', 'scripts/**'],
        languageOptions: { globals: { ...globals.node } },
    },
    prettier,
);

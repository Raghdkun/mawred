import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { I18nextProvider } from 'react-i18next';
import { LocalizedToaster } from '@/components/mw/localized-toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { i18n, namespacesForPage, prepareLocale } from '@/i18n';
import AppShellLayout from '@/layouts/app-shell-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';
import SettingsLayout from '@/layouts/settings/layout';
import SetupLayout from '@/layouts/setup-layout';

configureEcho({ broadcaster: 'reverb' });

const queryClient = new QueryClient({
    defaultOptions: { queries: { staleTime: 30_000, retry: 1, refetchOnWindowFocus: false } },
});

const pages = import.meta.glob('./pages/**/*.tsx');

/** Co-brand pages (D22, D87) title themselves with the company's name, not Mawred's. */
const CO_BRANDED = ['public/careers', 'public/job'];
let coBranded = false;

void createInertiaApp({
    title: (title) =>
        coBranded && title
            ? title
            : title
              ? `${title} · ${i18n.t('common:appName')}`
              : i18n.t('common:appName'),
    resolve: async (name, page) => {
        coBranded = CO_BRANDED.includes(name);

        // Translations for the page are loaded before it renders, so keys never flash (ADR-0011).
        await prepareLocale(page?.props.locale, namespacesForPage(name));

        const loader = pages[`./pages/${name}.tsx`];

        if (!loader) {
            throw new Error(`Page not found: ${name}`);
        }

        return loader() as never;
    },
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
            case name === 'setup/claim':
                return AuthLayout;
            case name.startsWith('setup/'):
            case name.startsWith('errors/'):
                return SetupLayout;
            case name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('settings/'):
                return [AppShellLayout, SettingsLayout];
            default:
                return AppShellLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <I18nextProvider i18n={i18n}>
                <QueryClientProvider client={queryClient}>
                    <TooltipProvider delayDuration={300}>
                        {app}
                        <LocalizedToaster />
                    </TooltipProvider>
                </QueryClientProvider>
            </I18nextProvider>
        );
    },
    progress: {
        color: '#5B88D6',
        delay: 300,
    },
});

initializeTheme();

// Installable app and offline check-in (D70); the service worker only exists in production builds.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    void navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => undefined);
}

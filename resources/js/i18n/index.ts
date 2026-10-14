import i18n from 'i18next';
import ICU from 'i18next-icu';
import { initReactI18next } from 'react-i18next';

/**
 * UI translations (ADR-0011): `lang/{locale}/ui/{namespace}.json`, ICU message format
 * (Arabic has six plural forms), loaded lazily per namespace before a page renders.
 */
export const SUPPORTED_LOCALES = ['en', 'ar'] as const;
export type Locale = (typeof SUPPORTED_LOCALES)[number];

type Messages = Record<string, unknown>;

const loaders = import.meta.glob<{ default: Messages }>('../../../lang/*/ui/*.json');

/** Namespaces every page needs. */
const BASE_NAMESPACES = ['common', 'shell', 'errors'];

/** Page-name prefix → extra namespace, e.g. `setup/wizard` → `setup`. */
const PAGE_NAMESPACES: Record<string, string[]> = {
    auth: ['auth'],
    setup: ['setup', 'auth', 'settings', 'organization', 'policies'],
    settings: ['settings', 'auth'],
    access: ['access'],
    organization: ['organization'],
    people: ['people', 'organization', 'imports', 'policies'],
    policies: ['policies', 'organization'],
    forms: ['forms', 'organization'],
    public: ['forms', 'requests'],
    requests: ['requests', 'forms'],
    approvals: ['requests', 'forms'],
    workflows: ['workflows', 'forms', 'requests'],
    audit: ['audit'],
    notifications: ['notifications'],
    dashboard: ['home', 'requests'],
    home: ['home', 'requests'],
    errors: [],
};

export function isLocale(value: unknown): value is Locale {
    return typeof value === 'string' && (SUPPORTED_LOCALES as readonly string[]).includes(value);
}

export function directionOf(locale: Locale): 'ltr' | 'rtl' {
    return locale === 'ar' ? 'rtl' : 'ltr';
}

export function namespacesForPage(component: string): string[] {
    const prefix = component.split('/')[0] ?? '';

    return [...BASE_NAMESPACES, ...(PAGE_NAMESPACES[prefix] ?? [])];
}

async function loadNamespace(locale: Locale, namespace: string): Promise<void> {
    if (i18n.hasResourceBundle(locale, namespace)) {
        return;
    }

    const loader = loaders[`../../../lang/${locale}/ui/${namespace}.json`];
    const messages: Messages = loader ? (await loader()).default : {};

    i18n.addResourceBundle(locale, namespace, messages, true, true);
}

let initialising: Promise<unknown> | null = null;

function init(locale: Locale): Promise<unknown> {
    initialising ??= i18n
        .use(ICU)
        .use(initReactI18next)
        .init({
            lng: locale,
            fallbackLng: 'en',
            supportedLngs: [...SUPPORTED_LOCALES],
            ns: BASE_NAMESPACES,
            defaultNS: 'common',
            resources: {},
            partialBundledLanguages: true,
            interpolation: { escapeValue: false },
            react: { useSuspense: false },
            returnNull: false,
            // Surface missing keys in development; production falls back to English silently.
            saveMissing: import.meta.env.DEV,
            missingKeyHandler: (languages, namespace, key) => {
                console.warn(`[i18n] Missing "${namespace}:${key}" for ${languages.join(', ')}`);
            },
        });

    return initialising;
}

/** Ensure i18next is initialised, on the right language, with the namespaces a page needs. */
export async function prepareLocale(locale: unknown, namespaces: string[]): Promise<void> {
    const lng: Locale = isLocale(locale) ? locale : 'en';

    await init(lng);
    await Promise.all(
        namespaces.flatMap((ns) => [
            loadNamespace(lng, ns),
            lng === 'en' ? null : loadNamespace('en', ns),
        ]),
    );

    if (i18n.language !== lng) {
        await i18n.changeLanguage(lng);
    }

    applyDocumentLocale(lng);
}

/** `<html lang dir>` follows the active locale (set server-side first, kept in sync here). */
export function applyDocumentLocale(locale: Locale): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.lang = locale;
    document.documentElement.dir = directionOf(locale);
}

export { i18n };

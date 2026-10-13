import { TZDate } from '@date-fns/tz';
import { type Locale } from '@/i18n';

/**
 * Locale-aware formatting (brief §17, tokens.css): Western digits by default, Arabic-Indic as a
 * per-user preference via `data-digits="arab"` on <html>. Never mix digit systems in one view.
 */
export function numberingSystem(): 'latn' | 'arab' {
    if (typeof document === 'undefined') {
        return 'latn';
    }

    return document.documentElement.dataset.digits === 'arab' ? 'arab' : 'latn';
}

export function intlLocale(
    locale: Locale,
    options: { calendar?: 'gregory' | 'islamic-umalqura' } = {},
): string {
    const base = locale === 'ar' ? 'ar' : 'en-US';
    const extensions = [`nu-${numberingSystem()}`];

    if (options.calendar) {
        extensions.push(`ca-${options.calendar}`);
    }

    return `${base}-u-${extensions.join('-')}`;
}

export function formatNumber(
    value: number,
    locale: Locale,
    options?: Intl.NumberFormatOptions,
): string {
    return new Intl.NumberFormat(intlLocale(locale), options).format(value);
}

export function formatDate(
    value: string | Date,
    locale: Locale,
    options: Intl.DateTimeFormatOptions & { timeZone?: string } = { dateStyle: 'medium' },
): string {
    const date = typeof value === 'string' ? new Date(value) : value;

    return new Intl.DateTimeFormat(intlLocale(locale), options).format(date);
}

/** Hijri (Umm al-Qura) date, shown as a secondary line under Gregorian when enabled. */
export function formatHijri(value: string | Date, locale: Locale): string {
    const date = typeof value === 'string' ? new Date(value) : value;

    return new Intl.DateTimeFormat(intlLocale(locale, { calendar: 'islamic-umalqura' }), {
        dateStyle: 'long',
    }).format(date);
}

/** A UTC instant rendered in a display timezone (ADR-0004). */
export function inTimeZone(value: string, timeZone: string): Date {
    return new TZDate(value, timeZone);
}

import { usePage } from '@inertiajs/react';
import { directionOf, isLocale, type Locale } from '@/i18n';

export function useLocale(): { locale: Locale; dir: 'ltr' | 'rtl'; isRtl: boolean } {
    const { locale } = usePage<{ locale: string }>().props;
    const current: Locale = isLocale(locale) ? locale : 'en';
    const dir = directionOf(current);

    return { locale: current, dir, isRtl: dir === 'rtl' };
}

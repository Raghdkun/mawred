import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { namespacesForPage, prepareLocale, type Locale } from '@/i18n';
import locale from '@/routes/locale';

/**
 * Switches EN ⇄ AR without a full reload (brief §17): translations load first, then `dir`
 * flips and the choice is saved (user preference, or the session for guests).
 */
export function LanguageToggle({ compact = false }: { compact?: boolean }) {
    const { t } = useTranslation('shell');
    const page = usePage();
    const { locale: current } = useLocale();
    const [switching, setSwitching] = useState(false);
    const next: Locale = current === 'ar' ? 'en' : 'ar';

    const switchTo = async () => {
        setSwitching(true);
        await prepareLocale(next, namespacesForPage(page.component));
        router.patch(
            locale.update.url(),
            { locale: next },
            { preserveScroll: true, preserveState: true, onFinish: () => setSwitching(false) },
        );
    };

    return (
        <Button
            variant="ghost"
            size={compact ? 'icon' : 'default'}
            onClick={switchTo}
            disabled={switching}
            aria-label={t('switchLanguage', { language: t(`common:language.${next}`) })}
            data-test="language-toggle"
            className="text-text-secondary"
        >
            <Languages aria-hidden />
            {compact ? null : <span lang={next}>{t(`common:language.${next}`)}</span>}
        </Button>
    );
}

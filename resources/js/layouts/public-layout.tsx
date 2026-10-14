import { usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { LanguageToggle } from '@/components/mw/language-toggle';
import { MawredLogo } from '@/components/mw/mawred-logo';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';

/**
 * Public pages (forms now, careers in Phase 6): a co-brand area, so the company logo leads and
 * Mawred appears only as "powered by".
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    useFlashToast();
    const { t } = useTranslation('shell');
    const { company } = usePage().props;
    const { locale } = useLocale();
    const name = localized(company.name, locale);

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="flex h-16 items-center justify-between gap-4 border-b border-border px-4 md:px-8">
                {company.logoUrl ? (
                    <img
                        src={company.logoUrl}
                        alt={name}
                        className="h-8 w-auto max-w-48 object-contain"
                    />
                ) : (
                    <span className="text-body font-semibold">{name}</span>
                )}
                <LanguageToggle />
            </header>
            <main id="main" className="flex flex-1 flex-col">
                {children}
            </main>
            <footer className="flex items-center justify-center gap-1.5 py-6 text-caption text-muted-foreground">
                <span>{t('poweredBy')}</span>
                <MawredLogo className="h-4 w-auto" />
            </footer>
        </div>
    );
}

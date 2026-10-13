import { usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Illustration } from '@/components/mw/illustration';
import { LanguageToggle } from '@/components/mw/language-toggle';
import { MawredLogo } from '@/components/mw/mawred-logo';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';

/**
 * Auth screens (handoff 1, 1a, 1b). `split`: 50/50 with the hero on the reading-end half
 * (left in Arabic), shrinking to a 160 px band on phones. `card`: a centred card at all widths.
 */
export default function AuthLayout({
    title,
    description,
    variant = 'card',
    children,
}: {
    title?: string;
    description?: string;
    variant?: 'split' | 'card';
    children: ReactNode;
}) {
    const { t } = useTranslation('auth');
    const { company } = usePage().props;
    const { locale } = useLocale();
    const companyName = company.name?.[locale] ?? company.name?.en;

    useFlashToast();

    const brand = (
        <div className="flex items-center gap-3">
            {company.logoUrl ? (
                <img
                    src={company.logoUrl}
                    alt={companyName ?? ''}
                    className="h-8 w-auto max-w-40 object-contain"
                />
            ) : (
                <MawredLogo className="h-8 w-auto" />
            )}
            {company.logoUrl ? <span className="sr-only">{companyName}</span> : null}
        </div>
    );

    const heading = title ? (
        <div className="flex flex-col gap-2">
            <h1 className="text-h2 text-foreground">{title}</h1>
            {description ? <p className="text-body text-muted-foreground">{description}</p> : null}
        </div>
    ) : null;

    if (variant === 'split') {
        return (
            <div className="grid min-h-svh bg-background md:grid-cols-2">
                <div className="relative flex h-40 items-center justify-center overflow-hidden bg-surface-sunken md:order-2 md:h-auto">
                    <Illustration
                        name="scene-login-hero"
                        priority
                        className="h-full w-full object-cover md:max-h-[640px] md:object-contain"
                    />
                </div>
                <div className="flex flex-col px-6 py-6 md:order-1 md:px-12 md:py-10">
                    <div className="flex items-center justify-between gap-4">
                        {brand}
                        <LanguageToggle />
                    </div>
                    <main className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-8 py-10">
                        {heading}
                        {children}
                    </main>
                    <p className="text-center text-caption text-muted-foreground">
                        {t('poweredBy')}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex min-h-svh flex-col bg-background px-4 py-6">
            <div className="mx-auto flex w-full max-w-md items-center justify-between gap-4">
                {brand}
                <LanguageToggle />
            </div>
            <main className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
                <div
                    className={cn(
                        'flex flex-col gap-8 rounded-xl bg-card p-6 shadow-md inset-shadow-highlight sm:p-8',
                    )}
                >
                    {heading}
                    {children}
                </div>
            </main>
        </div>
    );
}

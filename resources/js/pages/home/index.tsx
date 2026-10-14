import { Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageState } from '@/components/mw/page-state';
import { useAccess } from '@/hooks/use-access';
import { useLocale } from '@/hooks/use-locale';
import { hrefOf, navigation } from '@/layouts/shell/navigation';
import { NavIcon } from '@/layouts/shell/nav-icon';
import { formatDate, formatHijri } from '@/lib/format';
import security from '@/routes/security';
import { ChevronRight } from 'lucide-react';

function greetingKey(hour: number): string {
    if (hour < 12) {
        return 'greeting.morning';
    }

    return hour < 18 ? 'greeting.afternoon' : 'greeting.evening';
}

/**
 * Home (handoff 4–6 are role-based and arrive with their modules). Phase 0: greeting, today's
 * date, shortcuts to what exists, and the empty agenda state.
 */
export default function Home() {
    const { t } = useTranslation('home');
    const { auth, setup } = usePage().props;
    const { locale } = useLocale();
    const { can } = useAccess();
    const now = new Date();
    const firstName = auth?.user.displayName.split(' ')[0] ?? '';

    const shortcuts = navigation(setup.complete)
        .flatMap((group) => group.items)
        .filter((item) => item.key !== 'home' && (!item.permission || can(item.permission)));

    return (
        <>
            <Head title={t('pageTitle')} />

            <div className="mb-8 flex flex-col gap-1">
                <h1 className="text-h2 text-foreground">
                    {t(greetingKey(now.getHours()), { name: firstName })}
                </h1>
                <p className="text-body text-muted-foreground">
                    {formatDate(now, locale, { dateStyle: 'full' })}
                    {auth?.user.preferences.showHijri ? (
                        <span className="block text-small">{formatHijri(now, locale)}</span>
                    ) : null}
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-12">
                <div className="flex flex-col gap-6 lg:col-span-8">
                    <section
                        aria-labelledby="agenda"
                        className="rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight"
                    >
                        <h2 id="agenda" className="text-h4">
                            {t('agenda.title')}
                        </h2>
                        <PageState
                            kind="empty"
                            illustration="empty-schedule"
                            title={t('agenda.emptyTitle')}
                            description={t('agenda.emptyDescription')}
                            className="py-6"
                        />
                    </section>
                </div>

                <div className="flex flex-col gap-6 lg:col-span-4">
                    {auth && !auth.user.twoFactorEnabled ? (
                        <Link
                            href={security.edit()}
                            className="group flex items-start gap-3 rounded-lg border border-info-border bg-info-bg p-4 text-small text-info transition-colors hover:border-info focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <ShieldCheck aria-hidden className="mt-0.5 size-5 shrink-0" />
                            <span className="flex flex-col gap-0.5">
                                <span className="font-medium">{t('twoFactor.title')}</span>
                                <span>{t('twoFactor.description')}</span>
                            </span>
                        </Link>
                    ) : null}

                    {shortcuts.length > 0 ? (
                        <section
                            aria-labelledby="shortcuts"
                            className="rounded-lg bg-card p-2 shadow-sm inset-shadow-highlight"
                        >
                            <h2
                                id="shortcuts"
                                className="px-3 pt-3 pb-2 text-overline text-muted-foreground uppercase"
                            >
                                {t('shortcuts')}
                            </h2>
                            <ul>
                                {shortcuts.map((item) => (
                                    <li key={item.key}>
                                        <Link
                                            href={hrefOf(item.href)}
                                            className="flex h-12 items-center gap-3 rounded-md px-3 text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <NavIcon
                                                icon={item.icon}
                                                className="text-text-secondary"
                                            />
                                            <span className="flex-1">
                                                {t(`shell:${item.label}`)}
                                            </span>
                                            <DirIcon>
                                                <ChevronRight
                                                    aria-hidden
                                                    className="size-4 text-muted-foreground"
                                                />
                                            </DirIcon>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : null}
                </div>
            </div>
        </>
    );
}

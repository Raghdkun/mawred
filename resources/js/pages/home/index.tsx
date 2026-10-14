import { Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageState } from '@/components/mw/page-state';
import { useAccess } from '@/hooks/use-access';
import { useLocale } from '@/hooks/use-locale';
import { hrefOf, navigation } from '@/layouts/shell/navigation';
import { NavIcon } from '@/layouts/shell/nav-icon';
import { formatDate, formatHijri, formatNumber } from '@/lib/format';
import { RequestStatusBadge } from '@/features/requests/request-status';
import { SlaBadge } from '@/features/requests/sla-badge';
import { RequestTypeIcon } from '@/features/requests/type-icon';
import { localized } from '@/lib/localized';
import approvalRoutes from '@/routes/approvals';
import requestRoutes from '@/routes/requests';
import security from '@/routes/security';
import { ChevronRight } from 'lucide-react';

type Props = {
    layout: 'employee' | 'manager' | 'hr_admin';
    myRequests: App.Domain.Requests.Data.RequestRowData[];
    quickTypes: App.Domain.Requests.Data.RequestTypeData[];
    approvals: {
        open: number;
        overdue: number;
        decidedThisWeek: number;
        tasks: App.Domain.Requests.Data.TaskData[];
    } | null;
};

function greetingKey(hour: number): string {
    if (hour < 12) {
        return 'greeting.morning';
    }

    return hour < 18 ? 'greeting.afternoon' : 'greeting.evening';
}

/**
 * Home (handoff 4 employee v1, 5 manager): greeting, quick requests, my requests and, for
 * approvers, the approvals queue with KPIs. Clock-in, balances and the agenda fill in with Phase 5.
 */
export default function Home({
    myRequests = [],
    quickTypes = [],
    approvals = null,
}: Partial<Props>) {
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

            {quickTypes.length > 0 ? (
                <section aria-labelledby="quick" className="mb-6 lg:hidden">
                    <h2 id="quick" className="mb-2 text-overline text-muted-foreground uppercase">
                        {t('quick.title')}
                    </h2>
                    <QuickRequests types={quickTypes} scroller />
                </section>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-12">
                <div className="flex flex-col gap-6 lg:col-span-8">
                    {approvals ? <ApprovalsQueue approvals={approvals} /> : null}
                    <MyRequests requests={myRequests} />
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
                    {quickTypes.length > 0 ? (
                        <section
                            aria-labelledby="quick-desktop"
                            className="hidden rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight lg:block"
                        >
                            <h2 id="quick-desktop" className="mb-3 text-h4">
                                {t('quick.title')}
                            </h2>
                            <QuickRequests types={quickTypes} />
                        </section>
                    ) : null}
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

function QuickRequests({
    types,
    scroller = false,
}: {
    types: App.Domain.Requests.Data.RequestTypeData[];
    scroller?: boolean;
}) {
    const { t } = useTranslation('home');
    const { locale } = useLocale();

    return (
        <ul
            className={
                scroller
                    ? '-mx-4 flex snap-x gap-2 overflow-x-auto px-4 pb-1'
                    : 'grid grid-cols-2 gap-2'
            }
        >
            {types.map((type) => (
                <li key={type.id} className={scroller ? 'shrink-0 snap-start' : undefined}>
                    <Link
                        href={requestRoutes.create.url(type.id)}
                        className="flex min-h-11 items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-small hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <RequestTypeIcon icon={type.icon} className="size-4 text-primary" />
                        <span className={scroller ? 'whitespace-nowrap' : 'line-clamp-2'}>
                            {localized(type.name, locale)}
                        </span>
                    </Link>
                </li>
            ))}
            <li className={scroller ? 'shrink-0 snap-start' : undefined}>
                <Link
                    href={requestRoutes.center.url()}
                    className="flex min-h-11 items-center gap-1 rounded-md px-3 py-2 text-small text-primary hover:underline"
                >
                    {t('quick.all')}
                    <DirIcon>
                        <ChevronRight aria-hidden className="size-4" />
                    </DirIcon>
                </Link>
            </li>
        </ul>
    );
}

function MyRequests({ requests }: { requests: App.Domain.Requests.Data.RequestRowData[] }) {
    const { t } = useTranslation('home');
    const { locale } = useLocale();

    return (
        <section
            aria-labelledby="my-requests"
            className="rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight"
        >
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 id="my-requests" className="text-h4">
                    {t('myRequests.title')}
                </h2>
                <Link
                    href={requestRoutes.index.url()}
                    className="text-small text-primary hover:underline"
                >
                    {t('myRequests.all')}
                </Link>
            </div>
            {requests.length === 0 ? (
                <p className="text-small text-muted-foreground">{t('myRequests.empty')}</p>
            ) : (
                <ul className="flex flex-col divide-y divide-border">
                    {requests.map((request) => (
                        <li key={request.id} className="relative flex items-center gap-3 py-3">
                            <RequestTypeIcon
                                icon={request.typeIcon}
                                className="size-4 text-primary"
                            />
                            <span className="flex min-w-0 flex-1 flex-col">
                                <Link
                                    href={requestRoutes.show.url(request.id)}
                                    className="truncate text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none"
                                >
                                    {localized(request.typeName, locale)}
                                </Link>
                                <span className="text-caption text-muted-foreground">
                                    <bdi dir="ltr">{request.number}</bdi> ·{' '}
                                    {formatDate(request.submittedAt, locale, {
                                        dateStyle: 'medium',
                                    })}
                                </span>
                            </span>
                            <RequestStatusBadge status={request.status} />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function ApprovalsQueue({ approvals }: { approvals: NonNullable<Props['approvals']> }) {
    const { t } = useTranslation('home');
    const { locale } = useLocale();
    const kpis: Array<[string, number]> = [
        ['open', approvals.open],
        ['overdue', approvals.overdue],
        ['decided', approvals.decidedThisWeek],
    ];

    return (
        <section aria-labelledby="approvals-queue" className="flex flex-col gap-4">
            <ul className="grid grid-cols-3 gap-3">
                {kpis.map(([key, value]) => (
                    <li
                        key={key}
                        className="flex flex-col gap-1 rounded-lg bg-card p-4 shadow-sm inset-shadow-highlight"
                    >
                        <span className="text-caption text-muted-foreground">
                            {t(`approvals.kpis.${key}`)}
                        </span>
                        <span
                            className={
                                key === 'overdue' && value > 0
                                    ? 'text-h2 text-status-escalated-fg tabular-nums'
                                    : 'text-h2 tabular-nums'
                            }
                        >
                            {formatNumber(value, locale)}
                        </span>
                    </li>
                ))}
            </ul>
            <div className="rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight">
                <div className="mb-3 flex items-center justify-between gap-2">
                    <h2 id="approvals-queue" className="text-h4">
                        {t('approvals.title')}
                    </h2>
                    <Link
                        href={approvalRoutes.index.url()}
                        className="text-small text-primary hover:underline"
                    >
                        {t('approvals.all')}
                    </Link>
                </div>
                {approvals.tasks.length === 0 ? (
                    <p className="text-small text-muted-foreground">{t('approvals.empty')}</p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {approvals.tasks.map((task) => (
                            <li
                                key={task.id}
                                className="relative flex items-center gap-3 rounded-md border border-border p-3 hover:bg-muted/60"
                            >
                                <RequestTypeIcon
                                    icon={task.request?.typeIcon ?? null}
                                    className="size-4 text-primary"
                                />
                                <span className="flex min-w-0 flex-1 flex-col">
                                    <Link
                                        href={approvalRoutes.index.url({
                                            query: { task: task.id },
                                        })}
                                        className="truncate text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none"
                                    >
                                        {task.request
                                            ? localized(task.request.typeName, locale)
                                            : localized(task.step, locale)}
                                    </Link>
                                    <span className="truncate text-caption text-muted-foreground">
                                        {task.request?.requester ?? task.about}
                                    </span>
                                </span>
                                <SlaBadge dueAt={task.dueAt} overdue={task.overdue} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}

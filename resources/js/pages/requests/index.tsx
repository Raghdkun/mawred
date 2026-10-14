import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { SearchBox } from '@/components/mw/search-box';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { Button } from '@/components/ui/button';
import { REQUEST_STATUSES, RequestStatusBadge } from '@/features/requests/request-status';
import { RequestTypeIcon } from '@/features/requests/type-icon';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import requests from '@/routes/requests';
import { type Paginated } from '@/types/pagination';

type Row = App.Domain.Requests.Data.RequestRowData;

type Props = {
    requests: Paginated<Row>;
    filters: { tab: 'mine' | 'all'; status: string | null; search: string | null };
    can: { all: boolean; create: boolean };
};

/** My requests (handoff 12), and for HR the requests in their scope. Rows open the detail. */
export default function RequestsIndex({ requests: list, filters, can }: Props) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search ?? '');
    const query = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, search: search || null, ...next };

        return Object.fromEntries(
            Object.entries(merged).filter(
                ([key, value]) =>
                    value !== null && value !== '' && !(key === 'tab' && value === 'mine'),
            ),
        ) as Record<string, string>;
    };
    const visit = (next: Partial<Props['filters']>) =>
        router.get(requests.index.url(), query(next), { preserveState: true, replace: true });

    return (
        <>
            <Head title={t('index.pageTitle')} />
            <PageHeader
                title={t('index.title')}
                description={t('index.description')}
                actions={
                    can.create ? (
                        <Button asChild>
                            <Link href={requests.center.url()}>
                                <Plus aria-hidden />
                                {t('index.new')}
                            </Link>
                        </Button>
                    ) : null
                }
            />

            {can.all ? (
                <UnderlineTabs
                    label={t('index.tabs')}
                    items={(['mine', 'all'] as const).map((tab) => ({
                        key: tab,
                        label: t(`index.tab.${tab}`),
                        href: requests.index.url({ query: tab === 'mine' ? {} : { tab } }),
                        active: filters.tab === tab,
                    }))}
                />
            ) : null}

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                {filters.tab === 'all' ? (
                    <SearchBox
                        value={search}
                        onChange={setSearch}
                        onSubmit={() => visit({})}
                        label={t('index.search')}
                        className="sm:max-w-xs"
                    />
                ) : null}
                <label className="flex items-center gap-2 text-small text-text-secondary">
                    {t('index.status')}
                    <select
                        value={filters.status ?? ''}
                        onChange={(event) => visit({ status: event.target.value || null })}
                        className="h-10 rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option value="">{t('index.anyStatus')}</option>
                        {REQUEST_STATUSES.map((status) => (
                            <option key={status} value={status}>
                                {t(`status.${status}`)}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            {list.data.length === 0 ? (
                filters.status || filters.search ? (
                    <PageState kind="no-results" title={t('index.noResults')} />
                ) : (
                    <PageState
                        kind="empty"
                        illustration="empty-requests"
                        title={t('index.empty')}
                        description={t('index.emptyHelp')}
                        action={
                            can.create ? (
                                <Button asChild>
                                    <Link href={requests.center.url()}>{t('index.new')}</Link>
                                </Button>
                            ) : undefined
                        }
                    />
                )
            ) : (
                <ul className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
                    {list.data.map((row) => (
                        <li
                            key={row.id}
                            className="relative flex items-center gap-3 rounded-lg bg-card p-4 shadow-sm transition-colors duration-150 ease-soft focus-within:bg-muted/60 hover:bg-muted/60 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                        >
                            <span className="hidden size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary sm:flex">
                                <RequestTypeIcon icon={row.typeIcon} className="size-4" />
                            </span>
                            <span className="flex min-w-0 flex-1 flex-col gap-0.5 md:flex-row md:items-center md:gap-4">
                                <Link
                                    href={requests.show.url(row.id)}
                                    className="truncate text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none md:flex-1"
                                >
                                    {localized(row.typeName, locale)}
                                </Link>
                                <span className="text-caption text-muted-foreground md:w-48 md:text-small">
                                    <bdi dir="ltr">{row.number}</bdi>
                                    {filters.tab === 'all' ? <> · {row.requester}</> : null}
                                </span>
                                <time
                                    dateTime={row.submittedAt}
                                    className="text-caption text-muted-foreground md:w-32 md:text-small"
                                >
                                    {formatDate(row.submittedAt, locale, { dateStyle: 'medium' })}
                                </time>
                            </span>
                            <RequestStatusBadge status={row.status} className="shrink-0" />
                        </li>
                    ))}
                </ul>
            )}

            <Paginator
                prevUrl={list.prev_page_url}
                nextUrl={list.next_page_url}
                summary={
                    list.total > 0
                        ? `${formatNumber(list.from ?? 0, locale)}–${formatNumber(list.to ?? 0, locale)} / ${formatNumber(list.total, locale)}`
                        : undefined
                }
            />
        </>
    );
}

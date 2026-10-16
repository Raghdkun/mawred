import { Head, Link, router } from '@inertiajs/react';
import { Copy, Star } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { SearchBox } from '@/components/mw/search-box';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ApplicationStatusBadge } from '@/features/hiring/status';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import hiring from '@/routes/hiring';
import { type Paginated } from '@/types/pagination';

type Row = App.Domain.Hiring.Data.CandidateRowData;
type Props = {
    candidates: Paginated<Row>;
    filters: { search: string; tag: string; view: 'all' | 'pool' | 'duplicates' };
    tags: string[];
};

const ANY = '__any';

/** Candidates and the talent pool: search by name or exact email, filter by tag, review duplicates. */
export default function Candidates({ candidates, filters, tags }: Props) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search);
    const visit = (next: Partial<Props['filters']>) =>
        router.get(
            hiring.candidates.index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, search, ...next }).filter(
                    ([key, v]) => v && !(key === 'view' && v === 'all'),
                ),
            ),
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={t('candidates.title')} />
            <PageHeader title={t('candidates.title')} description={t('candidates.description')} />

            <UnderlineTabs
                label={t('candidates.tabs')}
                items={(['all', 'pool', 'duplicates'] as const).map((view) => ({
                    key: view,
                    label: t(`candidates.view.${view}`),
                    href: hiring.candidates.index.url({ query: view === 'all' ? {} : { view } }),
                    active: filters.view === view,
                }))}
            />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => visit({})}
                    label={t('candidates.search')}
                    className="sm:max-w-xs"
                />
                {tags.length > 0 ? (
                    <Select
                        value={filters.tag || ANY}
                        onValueChange={(tag) => visit({ tag: tag === ANY ? '' : tag })}
                    >
                        <SelectTrigger
                            className="min-h-11 sm:w-48"
                            aria-label={t('candidates.tag')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>{t('candidates.anyTag')}</SelectItem>
                            {tags.map((tag) => (
                                <SelectItem key={tag} value={tag}>
                                    {tag}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ) : null}
            </div>

            {candidates.data.length === 0 ? (
                filters.search || filters.tag || filters.view !== 'all' ? (
                    <PageState kind="no-results" title={t('candidates.noResults')} />
                ) : (
                    <PageState
                        kind="empty"
                        illustration="empty-candidates"
                        title={t('candidates.empty')}
                        description={t('candidates.emptyHelp')}
                    />
                )
            ) : (
                <ul
                    className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card"
                    data-candidates
                >
                    {candidates.data.map((c) => (
                        <li
                            key={c.id}
                            className="relative flex flex-col gap-2 rounded-lg bg-card p-4 shadow-sm transition-colors duration-150 ease-soft focus-within:bg-muted/60 hover:bg-muted/60 md:flex-row md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                        >
                            <span className="flex min-w-0 flex-1 flex-col gap-1">
                                <span className="flex items-center gap-2">
                                    <Link
                                        href={hiring.candidates.show.url(c.id)}
                                        className="truncate text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none"
                                    >
                                        {c.name}
                                    </Link>
                                    {c.talentPool ? (
                                        <Star
                                            aria-label={t('candidates.inPool')}
                                            className="size-3.5 shrink-0 fill-current text-warning"
                                        />
                                    ) : null}
                                    {c.possibleDuplicate ? (
                                        <Copy
                                            aria-label={t('board.duplicate')}
                                            className="size-3.5 shrink-0 text-info"
                                        />
                                    ) : null}
                                </span>
                                <span className="flex flex-wrap gap-1">
                                    {c.tags.map((tag) => (
                                        <span
                                            key={tag}
                                            className="rounded-full bg-muted px-2 py-0.5 text-caption text-text-secondary"
                                        >
                                            {tag}
                                        </span>
                                    ))}
                                </span>
                            </span>
                            <span className="flex flex-col gap-1 md:w-80">
                                {c.applications.slice(0, 2).map((a) => (
                                    <span
                                        key={a.id}
                                        className="flex items-center gap-2 text-caption text-text-secondary"
                                    >
                                        <span className="min-w-0 flex-1 truncate">
                                            {a.job} · {a.stage}
                                        </span>
                                        <ApplicationStatusBadge status={a.status} />
                                    </span>
                                ))}
                                {c.applications.length > 2 ? (
                                    <span className="text-caption text-text-secondary">
                                        {t('candidates.more', {
                                            count: c.applications.length - 2,
                                            formatted: formatNumber(
                                                c.applications.length - 2,
                                                locale,
                                            ),
                                        })}
                                    </span>
                                ) : null}
                            </span>
                            <time
                                dateTime={c.lastActivityAt}
                                className="text-caption text-muted-foreground md:w-28"
                            >
                                {formatDate(c.lastActivityAt, locale, { dateStyle: 'medium' })}
                            </time>
                        </li>
                    ))}
                </ul>
            )}

            <Paginator
                prevUrl={candidates.prev_page_url}
                nextUrl={candidates.next_page_url}
                summary={
                    candidates.total > 0
                        ? `${formatNumber(candidates.from ?? 0, locale)}–${formatNumber(candidates.to ?? 0, locale)} / ${formatNumber(candidates.total, locale)}`
                        : undefined
                }
            />
        </>
    );
}

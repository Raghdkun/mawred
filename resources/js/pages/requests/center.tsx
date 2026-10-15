import { Head, Link } from '@inertiajs/react';
import { ExternalLink, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { SearchBox } from '@/components/mw/search-box';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { RequestTypeIcon } from '@/features/requests/type-icon';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import requests from '@/routes/requests';

type RequestType = App.Domain.Requests.Data.RequestTypeData;

/** Request center (handoff 10): search, category pills and a card per request type. */
export default function RequestCenter({
    types,
    showSource = false,
}: {
    types: RequestType[];
    showSource?: boolean;
}) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const categories = useMemo(() => [...new Set(types.map((type) => type.category))], [types]);
    const needle = search.trim().toLocaleLowerCase(locale);
    const shown = types.filter(
        (type) =>
            (category === 'all' || type.category === category) &&
            (needle === '' ||
                [localized(type.name, locale), localized(type.description, locale)].some((text) =>
                    text.toLocaleLowerCase(locale).includes(needle),
                )),
    );

    return (
        <>
            <Head title={t('center.pageTitle')} />
            <PageHeader title={t('center.title')} description={t('center.description')} />

            <div className="mb-6 flex flex-col gap-3">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => undefined}
                    label={t('center.search')}
                />
                {categories.length > 1 ? (
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={category}
                        onValueChange={(next) => next && setCategory(next)}
                        aria-label={t('center.categories')}
                        className="flex-wrap justify-start"
                    >
                        {['all', ...categories].map((key) => (
                            <ToggleGroupItem key={key} value={key} className="rounded-full px-4">
                                {t(`categories.${key}`)}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                ) : null}
            </div>

            {types.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-requests"
                    title={t('center.empty')}
                    description={t('center.emptyHelp')}
                />
            ) : shown.length === 0 ? (
                <PageState kind="no-results" title={t('center.noResults')} />
            ) : (
                <ul className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                    {shown.map((type) => (
                        <li key={type.id}>
                            {type.externalUrl ? (
                                <a
                                    href={type.externalUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex h-full min-h-36 flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-sm transition-[border-color,box-shadow] duration-150 ease-soft hover:border-primary/50 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span className="flex items-start justify-between gap-2">
                                        <span className="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                            <RequestTypeIcon icon={type.icon} />
                                        </span>
                                        {showSource ? (
                                            <span className="rounded-full border border-border px-2 py-0.5 text-caption text-muted-foreground">
                                                {t(`center.sources.${type.source}`)}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="flex flex-1 flex-col gap-1">
                                        <span className="text-sm font-medium text-foreground">
                                            {localized(type.name, locale)}
                                        </span>
                                        {localized(type.description, locale) ? (
                                            <span className="line-clamp-2 text-caption text-muted-foreground">
                                                {localized(type.description, locale)}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="flex items-center gap-1.5 text-caption text-text-secondary">
                                        {type.isSensitive ? (
                                            <ShieldCheck aria-hidden className="size-3.5" />
                                        ) : null}
                                        {type.isSensitive
                                            ? t('center.sensitive')
                                            : t('center.needsApproval')}
                                        {type.externalUrl ? (
                                            <>
                                                <ExternalLink
                                                    aria-hidden
                                                    className="ms-auto size-3.5 rtl:-scale-x-100"
                                                />
                                                <span className="sr-only">
                                                    {t('center.opensCognito')}
                                                </span>
                                            </>
                                        ) : null}
                                    </span>
                                </a>
                            ) : (
                                <Link
                                    href={requests.create.url(type.id)}
                                    prefetch
                                    className="flex h-full min-h-36 flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-sm transition-[border-color,box-shadow] duration-150 ease-soft hover:border-primary/50 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span className="flex items-start justify-between gap-2">
                                        <span className="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                            <RequestTypeIcon icon={type.icon} />
                                        </span>
                                        {showSource ? (
                                            <span className="rounded-full border border-border px-2 py-0.5 text-caption text-muted-foreground">
                                                {t(`center.sources.${type.source}`)}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="flex flex-1 flex-col gap-1">
                                        <span className="text-sm font-medium text-foreground">
                                            {localized(type.name, locale)}
                                        </span>
                                        {localized(type.description, locale) ? (
                                            <span className="line-clamp-2 text-caption text-muted-foreground">
                                                {localized(type.description, locale)}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="flex items-center gap-1.5 text-caption text-text-secondary">
                                        {type.isSensitive ? (
                                            <ShieldCheck aria-hidden className="size-3.5" />
                                        ) : null}
                                        {type.isSensitive
                                            ? t('center.sensitive')
                                            : t('center.needsApproval')}
                                        {type.externalUrl ? (
                                            <>
                                                <ExternalLink
                                                    aria-hidden
                                                    className="ms-auto size-3.5 rtl:-scale-x-100"
                                                />
                                                <span className="sr-only">
                                                    {t('center.opensCognito')}
                                                </span>
                                            </>
                                        ) : null}
                                    </span>
                                </Link>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

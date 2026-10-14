import { Head, Link, router } from '@inertiajs/react';
import { Globe, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { SearchBox } from '@/components/mw/search-box';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FormSettingsDialog } from '@/features/forms/form-settings-dialog';
import { FormStatusBadge } from '@/features/forms/form-status-badge';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import forms from '@/routes/forms';
import { type Paginated } from '@/types/pagination';

type FormData = App.Domain.Forms.Data.FormData;

type Props = {
    forms: Paginated<FormData>;
    filters: { search: string | null };
    can: { create: boolean; update: boolean; delete: boolean };
};

/** Forms (ADR-0008): every form with its status, public link and submissions; cards on phones. */
export default function FormsIndex({ forms: list, filters, can }: Props) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={t('index.pageTitle')} />
            <PageHeader
                title={t('index.title')}
                description={t('index.description')}
                actions={
                    can.create ? (
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden />
                            {t('index.new')}
                        </Button>
                    ) : null
                }
            />
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() =>
                        router.get(forms.index.url(), search ? { search } : {}, {
                            preserveState: true,
                            replace: true,
                        })
                    }
                    label={t('index.search')}
                />
            </div>

            {list.data.length === 0 ? (
                filters.search ? (
                    <PageState kind="no-results" title={t('index.noResults')} />
                ) : (
                    <PageState
                        kind="empty"
                        illustration="empty-requests"
                        title={t('index.empty')}
                        description={t('index.emptyHelp')}
                    />
                )
            ) : (
                <ul className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
                    {list.data.map((form) => (
                        <li
                            key={form.id}
                            className="relative flex flex-col gap-2 rounded-lg bg-card p-4 shadow-sm transition-colors duration-150 ease-soft focus-within:bg-muted/60 hover:bg-muted/60 md:flex-row md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                        >
                            <span className="flex min-w-0 flex-1 flex-col">
                                <Link
                                    href={forms.builder.url(form.id)}
                                    className="truncate text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none"
                                >
                                    {localized(form.name, locale)}
                                </Link>
                                <bdi
                                    dir="ltr"
                                    className="max-w-full self-start text-caption text-muted-foreground"
                                >
                                    {form.code}
                                </bdi>
                            </span>
                            <span className="md:w-64">
                                <FormStatusBadge form={form} />
                            </span>
                            <span className="flex items-center gap-2 md:w-28">
                                {form.isPublic ? (
                                    <Badge variant="outline">
                                        <Globe aria-hidden />
                                        {t('index.public')}
                                    </Badge>
                                ) : null}
                            </span>
                            <span className="text-small text-text-secondary tabular-nums md:w-36">
                                {t('index.submissions', {
                                    count: form.submissionsCount,
                                    formatted: formatNumber(form.submissionsCount, locale),
                                })}
                            </span>
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

            {creating ? (
                <FormSettingsDialog form={null} onClose={() => setCreating(false)} />
            ) : null}
        </>
    );
}

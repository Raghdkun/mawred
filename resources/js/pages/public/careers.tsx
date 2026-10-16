import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, ChevronRight, MapPin } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageState } from '@/components/mw/page-state';
import { SearchBox } from '@/components/mw/search-box';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import careers from '@/routes/careers';

type Job = App.Domain.Hiring.Data.CareersPostingData;
type Props = {
    jobs: Job[];
    total: number;
    intro: string;
    branches: Array<{ id: string; name: string }>;
    filters: { q: string; workplace: string; branch: string };
};

const ANY = '__any';

/**
 * The careers page (handoff 31, D87): the company's open roles as cards with search and filters.
 * Mobile-first and server-rendered; a co-brand area (company logo in the header).
 */
export default function Careers({ jobs, total, intro, branches, filters }: Props) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [q, setQ] = useState(filters.q);
    const company = localized(usePage().props.company.name, locale);
    const visit = (next: Partial<Props['filters']>) =>
        router.get(
            careers.index.url(),
            Object.fromEntries(Object.entries({ ...filters, q, ...next }).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-8 md:py-12">
            <Head title={`${t('careers.title')} · ${company}`} />
            <header className="flex flex-col gap-2">
                <h1 className="text-h1">{t('careers.title')}</h1>
                {intro ? <p className="max-w-2xl text-body text-text-secondary">{intro}</p> : null}
            </header>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center" role="search">
                <SearchBox value={q} onChange={setQ} onSubmit={() => visit({})} label={t('careers.search')} className="sm:max-w-sm" />
                {branches.length > 1 ? (
                    <Select value={filters.branch || ANY} onValueChange={(branch) => visit({ branch: branch === ANY ? '' : branch })}>
                        <SelectTrigger className="min-h-11 sm:w-52" aria-label={t('careers.location')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>{t('careers.anyLocation')}</SelectItem>
                            {branches.map((b) => (
                                <SelectItem key={b.id} value={b.id}>
                                    {b.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ) : null}
                <Select value={filters.workplace || ANY} onValueChange={(workplace) => visit({ workplace: workplace === ANY ? '' : workplace })}>
                    <SelectTrigger className="min-h-11 sm:w-44" aria-label={t('careers.workplace')}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>{t('careers.anyWorkplace')}</SelectItem>
                        {(['onsite', 'hybrid', 'remote'] as const).map((w) => (
                            <SelectItem key={w} value={w}>
                                {t(`workplace.${w}`)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <p className="text-small text-text-secondary" aria-live="polite">
                {t('careers.count', { count: jobs.length, formatted: formatNumber(jobs.length, locale) })}
            </p>

            {jobs.length === 0 ? (
                total > 0 ? (
                    <PageState kind="no-results" title={t('careers.noResults')} />
                ) : (
                    <PageState kind="empty" illustration="empty-candidates" title={t('careers.empty')} description={t('careers.emptyHelp')} />
                )
            ) : (
                <ul className="grid gap-3 md:grid-cols-2" data-jobs>
                    {jobs.map((job) => (
                        <li key={job.slug}>
                            <Link
                                href={careers.show.url(job.slug)}
                                className="group relative flex h-full flex-col gap-3 rounded-lg border border-border bg-card p-5 shadow-sm transition-shadow duration-150 ease-soft hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none"
                                data-job={job.slug}
                            >
                                <span className="flex items-start justify-between gap-2">
                                    <span className="text-h4">{job.title}</span>
                                    <DirIcon>
                                        <ChevronRight aria-hidden className="mt-1 size-4 shrink-0 text-text-secondary" />
                                    </DirIcon>
                                </span>
                                {job.summary ? <span className="text-small text-text-secondary">{job.summary}</span> : null}
                                <span className="mt-auto flex flex-wrap gap-x-4 gap-y-1 text-caption text-text-secondary">
                                    <span className="inline-flex items-center gap-1">
                                        <MapPin aria-hidden className="size-3.5" />
                                        {job.location ?? job.branch}
                                    </span>
                                    <span>{t(`workplace.${job.workplace}`)}</span>
                                    {job.department ? (
                                        <span className="inline-flex items-center gap-1">
                                            <Building2 aria-hidden className="size-3.5" />
                                            {job.department}
                                        </span>
                                    ) : null}
                                    {job.publishedAt ? <span>{formatDate(job.publishedAt, locale, { dateStyle: 'medium' })}</span> : null}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

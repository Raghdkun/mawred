import { Head, Link } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { StatusBadge } from '@/components/mw/status-badge';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import hiring from '@/routes/hiring';

type Row = {
    id: string;
    title: string;
    status: string;
    startsAt: string | null;
    endsAt: string | null;
    timezone: string;
    candidate: { id: string; name: string };
    application: string;
    job: string;
    scorecard: 'upcoming' | 'missing' | 'draft' | 'submitted';
};

/**
 * My interviews (D92): every interview you're on the panel of, whatever your role, with whether
 * your scorecard is in. Opening one goes to the candidate (CV, answers, your scorecard).
 */
export default function MyInterviews({ interviews }: { interviews: Row[] }) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();

    return (
        <>
            <Head title={t('mine.title')} />
            <PageHeader title={t('mine.title')} description={t('mine.description')} />
            {interviews.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-candidates"
                    title={t('mine.empty')}
                    description={t('mine.emptyHelp')}
                />
            ) : (
                <ul
                    className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card"
                    data-my-interviews
                >
                    {interviews.map((row) => (
                        <li
                            key={row.id}
                            className="relative flex flex-col gap-2 rounded-lg bg-card p-4 shadow-sm focus-within:bg-muted/60 hover:bg-muted/60 md:flex-row md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                        >
                            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                <Link
                                    href={hiring.candidates.show.url(row.candidate.id, {
                                        query: { application: row.application },
                                    })}
                                    className="truncate text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none"
                                >
                                    {row.candidate.name}
                                </Link>
                                <span className="text-caption text-text-secondary">
                                    {row.title} · {row.job}
                                </span>
                            </span>
                            <span className="flex items-center gap-1.5 text-small text-text-secondary md:w-64">
                                <CalendarClock aria-hidden className="size-4" />
                                {row.startsAt
                                    ? formatDate(row.startsAt, locale, {
                                          dateStyle: 'medium',
                                          timeStyle: 'short',
                                          timeZone: row.timezone,
                                      })
                                    : t(`interviewStatus.${row.status}`)}
                            </span>
                            <StatusBadge
                                status={
                                    row.scorecard === 'submitted'
                                        ? 'approved'
                                        : row.scorecard === 'draft'
                                          ? 'in-review'
                                          : row.scorecard === 'upcoming'
                                            ? 'draft'
                                            : 'pending'
                                }
                                label={t(`mine.scorecard.${row.scorecard}`)}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

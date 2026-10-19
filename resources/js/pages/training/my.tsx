import { Head, Link } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader, Section } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { ProgressRing } from '@/features/training/progress-ring';
import { CertificateList, ExternalList } from '@/features/training/records';
import { DueChip, EnrollmentStatusBadge } from '@/features/training/status';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import training from '@/routes/training';

type Enrollment = App.Domain.Training.Data.EnrollmentData;
type Props = {
    enrollments: Enrollment[];
    stats: { active: number; overdue: number; completed: number };
    paths: App.Domain.Training.Data.LearningPathData[];
    certificates: App.Domain.Training.Data.CertificateData[];
    external: App.Domain.Training.Data.ExternalTrainingData[];
    hasEmployee: boolean;
};

/**
 * My learning (handoff 34, mobile-first): counts at the top, then courses in progress (overdue
 * first by due date), waiting for approval, and finished; the learning paths joined.
 */
export default function MyLearning({
    enrollments,
    stats,
    paths,
    certificates,
    external,
    hasEmployee,
}: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const groups = [
        { key: 'active', rows: enrollments.filter((e) => e.status === 'enrolled') },
        { key: 'waiting', rows: enrollments.filter((e) => e.status === 'requested') },
        { key: 'completed', rows: enrollments.filter((e) => e.status === 'completed') },
    ].filter((group) => group.rows.length > 0);

    return (
        <>
            <Head title={t('my.title')} />
            <PageHeader
                title={t('my.title')}
                description={t('my.description')}
                actions={
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href={training.catalog.url()}>
                            <BookOpen aria-hidden />
                            {t('my.browse')}
                        </Link>
                    </Button>
                }
            />

            {!hasEmployee ||
            (enrollments.length === 0 && certificates.length === 0 && external.length === 0) ? (
                <PageState
                    kind="empty"
                    illustration="empty-trainings"
                    title={t('my.empty')}
                    description={t('my.emptyHelp')}
                    action={
                        <Button asChild className="min-h-11">
                            <Link href={training.catalog.url()}>{t('my.browse')}</Link>
                        </Button>
                    }
                />
            ) : (
                <div className="flex flex-col gap-8">
                    <dl className="grid grid-cols-3 gap-3" data-my-stats>
                        {(['active', 'overdue', 'completed'] as const).map((key) => (
                            <div
                                key={key}
                                className={cn(
                                    'flex flex-col gap-1 rounded-lg border bg-card p-4',
                                    key === 'overdue' && stats.overdue > 0
                                        ? 'border-status-rejected-border'
                                        : 'border-border',
                                )}
                            >
                                <dt className="text-caption text-text-secondary">
                                    {t(`my.stats.${key}`)}
                                </dt>
                                <dd
                                    className={cn(
                                        'text-h2 tabular-nums',
                                        key === 'overdue' && stats.overdue > 0 ? 'text-danger' : '',
                                    )}
                                >
                                    {formatNumber(stats[key], locale)}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    {groups.map((group) => (
                        <Section key={group.key} title={t(`my.groups.${group.key}`)}>
                            <ul className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card">
                                {group.rows.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex items-center gap-3 p-3"
                                        data-enrollment={row.id}
                                    >
                                        <ProgressRing
                                            value={row.progress}
                                            size={44}
                                            label={t('catalog.progress', { value: row.progress })}
                                        />
                                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                                            <Link
                                                href={training.courses.show.url(row.course.id)}
                                                className="truncate text-body font-medium hover:underline"
                                            >
                                                {row.course.title}
                                            </Link>
                                            <div className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                                                <EnrollmentStatusBadge status={row.status} />
                                                <span>{t(`source.${row.source}`)}</span>
                                                {row.dueOn && row.status === 'enrolled' ? (
                                                    <DueChip
                                                        dueOn={row.dueOn}
                                                        overdue={row.overdue}
                                                        label={
                                                            row.overdue
                                                                ? t('course.overdueOn', {
                                                                      date: formatDate(
                                                                          row.dueOn,
                                                                          locale,
                                                                          { dateStyle: 'medium' },
                                                                      ),
                                                                  })
                                                                : t('course.due', {
                                                                      date: formatDate(
                                                                          row.dueOn,
                                                                          locale,
                                                                          { dateStyle: 'medium' },
                                                                      ),
                                                                  })
                                                        }
                                                    />
                                                ) : null}
                                                {row.completedAt ? (
                                                    <span>
                                                        {t('course.completedOn', {
                                                            date: formatDate(
                                                                row.completedAt,
                                                                locale,
                                                                { dateStyle: 'medium' },
                                                            ),
                                                        })}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    ))}

                    {certificates.length > 0 ? (
                        <Section title={t('my.groups.certificates')}>
                            <CertificateList certificates={certificates} />
                        </Section>
                    ) : null}

                    {external.length > 0 ? (
                        <Section title={t('my.groups.external')} description={t('my.externalHelp')}>
                            <ExternalList records={external} canUpload />
                        </Section>
                    ) : null}

                    {paths.length > 0 ? (
                        <Section title={t('my.groups.paths')}>
                            <ul className="grid gap-3 sm:grid-cols-2">
                                {paths.map((path) => {
                                    const done = path.courses.filter(
                                        (c) => c.status === 'completed',
                                    ).length;
                                    const share =
                                        path.courses.length === 0
                                            ? 0
                                            : Math.round((done * 100) / path.courses.length);

                                    return (
                                        <li
                                            key={path.id}
                                            className="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
                                        >
                                            <ProgressRing
                                                value={share}
                                                size={44}
                                                label={t('path.progress', {
                                                    done,
                                                    total: path.courses.length,
                                                })}
                                            />
                                            <Link
                                                href={training.paths.show.url(path.id)}
                                                className="text-body font-medium hover:underline"
                                            >
                                                {path.title}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </Section>
                    ) : null}
                </div>
            )}
        </>
    );
}

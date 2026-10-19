import { Link } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { PageState } from '@/components/mw/page-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { api } from '@/lib/api';
import { formatDate } from '@/lib/format';
import training from '@/routes/training';
import { ProgressRing } from './progress-ring';
import { DueChip, EnrollmentStatusBadge } from './status';

type Enrollment = App.Domain.Training.Data.EnrollmentData;

/** Someone's training record on their profile (handoff 8 Training tab): every course and where they are. */
export function TrainingPanel({ employeeId }: { employeeId: string }) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const record = useQuery({
        queryKey: ['training-record', employeeId],
        queryFn: ({ signal }) =>
            api<{ data: { enrollments: Enrollment[] } }>(`employees/${employeeId}/training`, {
                signal,
            }),
    });

    if (record.isPending) {
        return (
            <div className="flex flex-col gap-2" aria-busy="true">
                <Skeleton className="h-16 w-full" />
                <Skeleton className="h-16 w-full" />
            </div>
        );
    }

    if (record.isError) {
        return <PageState kind="error" title={t('panel.error')} />;
    }

    const rows = record.data.data.enrollments;

    if (rows.length === 0) {
        return (
            <PageState
                kind="empty"
                illustration="empty-trainings"
                title={t('panel.empty')}
                description={t('panel.emptyHelp')}
            />
        );
    }

    return (
        <ul
            className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
            data-training-record
        >
            {rows.map((row) => (
                <li key={row.id} className="flex items-center gap-3 p-3">
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
                                                  date: formatDate(row.dueOn, locale, {
                                                      dateStyle: 'medium',
                                                  }),
                                              })
                                            : t('course.due', {
                                                  date: formatDate(row.dueOn, locale, {
                                                      dateStyle: 'medium',
                                                  }),
                                              })
                                    }
                                />
                            ) : null}
                            {row.completedAt ? (
                                <span>
                                    {t('course.completedOn', {
                                        date: formatDate(row.completedAt, locale, {
                                            dateStyle: 'medium',
                                        }),
                                    })}
                                </span>
                            ) : null}
                        </div>
                    </div>
                </li>
            ))}
        </ul>
    );
}

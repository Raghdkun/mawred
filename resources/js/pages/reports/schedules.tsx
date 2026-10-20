import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Pause, Play, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';
import { Button } from '@/components/ui/button';
import { hourName, weekdayName } from '@/features/reports/schedule-dialog';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';

type Schedule = App.Domain.Reports.Data.ReportScheduleData;

const STATUS: Record<App.Domain.Reports.Enums.RunStatus, WorkflowStatus> = {
    queued: 'pending',
    running: 'in-review',
    ready: 'approved',
    failed: 'rejected',
};

/** My scheduled reports (D120): when each runs next, how the last run went, pause or delete. */
export default function ReportSchedules({ schedules }: { schedules: Schedule[] }) {
    const { t } = useTranslation('reports');
    const { locale } = useLocale();
    const when = (s: Schedule) =>
        t(`schedules.every.${s.frequency}`, {
            time: hourName(s.hour, locale),
            weekday: s.weekday ? weekdayName(s.weekday, locale) : '',
            day: s.monthDay ?? 1,
        });

    return (
        <>
            <Head title={t('schedules.pageTitle')} />
            <Link
                href={reports.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('back')}
            </Link>
            <PageHeader title={t('schedules.title')} description={t('schedules.description')} />
            {schedules.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-reports"
                    title={t('schedules.empty.title')}
                    description={t('schedules.empty.description')}
                />
            ) : (
                <ul
                    className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
                    data-schedules
                >
                    {schedules.map((s) => (
                        <li
                            key={s.id}
                            className="flex flex-wrap items-center gap-3 p-3"
                            data-schedule={s.id}
                        >
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <span className="text-body font-medium">
                                    {s.title}{' '}
                                    <span className="text-small font-normal text-text-secondary">
                                        {s.format.toUpperCase()}
                                    </span>
                                </span>
                                <div className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                                    <span>{when(s)}</span>
                                    {s.paused ? (
                                        <StatusBadge
                                            status="cancelled"
                                            label={t('schedules.paused')}
                                        />
                                    ) : (
                                        <span>
                                            {t('schedules.next', {
                                                date: formatDate(s.nextRunAt, locale, {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                }),
                                            })}
                                        </span>
                                    )}
                                    {s.lastRunAt ? (
                                        <span>
                                            {t('schedules.last', {
                                                date: formatDate(s.lastRunAt, locale, {
                                                    dateStyle: 'medium',
                                                }),
                                            })}
                                        </span>
                                    ) : null}
                                    {s.lastRunStatus ? (
                                        <StatusBadge
                                            status={STATUS[s.lastRunStatus]}
                                            label={t(`schedules.lastStatus.${s.lastRunStatus}`)}
                                        />
                                    ) : null}
                                </div>
                                {s.pausedReason ? (
                                    <p className="text-small text-danger">
                                        {t('schedules.pausedBecause', { reason: s.pausedReason })}
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() =>
                                        router.post(
                                            reports.schedules.pause.url(s.id),
                                            { paused: !s.paused },
                                            { preserveScroll: true },
                                        )
                                    }
                                    data-toggle-schedule
                                >
                                    {s.paused ? <Play aria-hidden /> : <Pause aria-hidden />}
                                    {s.paused ? t('schedules.resume') : t('schedules.pause')}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    aria-label={t('schedules.deleteLabel', { title: s.title })}
                                    onClick={() =>
                                        router.delete(reports.schedules.destroy.url(s.id), {
                                            preserveScroll: true,
                                        })
                                    }
                                    data-delete-schedule
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

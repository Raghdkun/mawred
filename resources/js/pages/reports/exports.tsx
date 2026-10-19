import { Head, Link, usePoll } from '@inertiajs/react';
import { ChevronLeft, Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';

type Run = App.Domain.Reports.Data.ReportRunData;
type Props = { exports: Run[]; keepDays: number };

const STATUS: Record<App.Domain.Reports.Enums.RunStatus, WorkflowStatus> = {
    queued: 'pending',
    running: 'in-review',
    ready: 'approved',
    failed: 'rejected',
};

/** My exports (D114): files I asked for, while they're kept; refreshes while one is being made. */
export default function ReportExports({ exports, keepDays }: Props) {
    const { t } = useTranslation('reports');
    const { locale } = useLocale();
    const working = exports.some((run) => run.status === 'queued' || run.status === 'running');

    usePoll(4000, { only: ['exports'] }, { autoStart: working });

    return (
        <>
            <Head title={t('exports.pageTitle')} />
            <Link
                href={reports.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('back')}
            </Link>
            <PageHeader
                title={t('exports.title')}
                description={t('exports.description', { days: keepDays })}
            />
            {exports.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-reports"
                    title={t('exports.empty.title')}
                    description={t('exports.empty.description')}
                />
            ) : (
                <>
                    {working ? (
                        <p className="mb-3 text-small text-text-secondary">{t('exports.live')}</p>
                    ) : null}
                    <ul
                        className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
                        aria-live="polite"
                        data-exports
                    >
                        {exports.map((run) => (
                            <li
                                key={run.id}
                                className="flex flex-wrap items-center gap-3 p-3"
                                data-export-run={run.id}
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <span className="text-body font-medium">
                                        {run.title}{' '}
                                        <span className="text-small font-normal text-text-secondary uppercase">
                                            {run.format}
                                        </span>
                                    </span>
                                    <div className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                                        <StatusBadge
                                            status={STATUS[run.status]}
                                            label={t(`exports.status.${run.status}`)}
                                        />
                                        {run.period ? <bdi>{run.period}</bdi> : null}
                                        {run.rows !== null ? (
                                            <span>{t('exports.rows', { count: run.rows })}</span>
                                        ) : null}
                                        <span>
                                            {t('exports.requested', {
                                                date: formatDate(run.createdAt, locale, {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                }),
                                            })}
                                        </span>
                                        {run.expiresAt && run.status === 'ready' ? (
                                            <span>
                                                {t('exports.expires', {
                                                    date: formatDate(run.expiresAt, locale, {
                                                        dateStyle: 'medium',
                                                    }),
                                                })}
                                            </span>
                                        ) : null}
                                    </div>
                                    {run.error ? (
                                        <p className="text-small text-danger" data-export-error>
                                            {run.error}
                                        </p>
                                    ) : null}
                                </div>
                                {run.status === 'ready' ? (
                                    <Button asChild className="min-h-11">
                                        <a
                                            href={reports.exports.download.url(run.id)}
                                            download={run.fileName ?? undefined}
                                            aria-label={t('exports.download', {
                                                name: run.fileName ?? run.title,
                                            })}
                                            data-download
                                        >
                                            <Download aria-hidden />
                                            {t('exports.downloadShort')}
                                        </a>
                                    </Button>
                                ) : run.status === 'failed' ? (
                                    <Button asChild variant="outline" className="min-h-11">
                                        <Link href={reports.show.url(run.report)}>
                                            {t('exports.retry')}
                                        </Link>
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </>
    );
}

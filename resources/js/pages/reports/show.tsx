import { Deferred, Head, Link, router } from '@inertiajs/react';
import { CalendarClock, ChevronLeft, Pencil, Share2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { ScheduleDialog } from '@/features/reports/schedule-dialog';
import { ShareDialog } from '@/features/reports/share-dialog';
import { ExportMenu } from '@/features/reports/export-menu';
import { FilterBar, type FilterValues } from '@/features/reports/filter-bar';
import { KpiCards } from '@/features/reports/kpi-cards';
import { ReportChart } from '@/features/reports/report-chart';
import { ReportTable } from '@/features/reports/report-table';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import reports from '@/routes/reports';

type Props = {
    report: App.Domain.Reports.Data.ReportCardData;
    filters: App.Domain.Reports.Data.ReportFilterData[];
    values: FilterValues;
    selected: { branch: PickerOption | null; department: PickerOption | null };
    formats: App.Domain.Reports.Enums.ReportFormat[];
    filterErrors: Record<string, string>;
    result?: App.Domain.Reports.Data.ReportResultData | null;
    saved: {
        report: App.Domain.Reports.Data.SavedReportData;
        can: { edit: boolean; share: boolean; delete: boolean };
    } | null;
    roles: Array<{ name: string; label: string }>;
};

/**
 * The report viewer (handoff 36): filters, headline numbers, a chart and the table. The result
 * loads after the page (it can take a moment), behind a skeleton.
 */
export default function ReportShow(props: Props) {
    const { report, filters, values, selected, formats, filterErrors, result, saved, roles } =
        props;
    const { t } = useTranslation('reports');
    const [sharing, setSharing] = useState(false);
    const [scheduling, setScheduling] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const savedId = saved?.report.id ?? null;

    return (
        <>
            <Head title={report.title} />
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
                title={report.title}
                description={report.description}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        {saved?.can.edit && savedId ? (
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={reports.builder.edit.url(savedId)} data-edit-report>
                                    <Pencil aria-hidden />
                                    {t('saved.edit')}
                                </Link>
                            </Button>
                        ) : null}
                        {saved?.can.share ? (
                            <Button
                                variant="outline"
                                className="min-h-11"
                                onClick={() => setSharing(true)}
                                data-share-report
                            >
                                <Share2 aria-hidden />
                                {t('saved.share')}
                            </Button>
                        ) : null}
                        {saved?.can.delete ? (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={t('saved.delete')}
                                onClick={() => setDeleting(true)}
                                data-delete-report
                            >
                                <Trash2 aria-hidden />
                            </Button>
                        ) : null}
                        {report.canExport ? (
                            <Button
                                variant="outline"
                                className="min-h-11"
                                onClick={() => setScheduling(true)}
                                data-schedule-report
                            >
                                <CalendarClock aria-hidden />
                                {t('schedule.button')}
                            </Button>
                        ) : null}
                        {report.canExport && result !== null ? (
                            <ExportMenu reportKey={report.key} values={values} formats={formats} />
                        ) : null}
                    </div>
                }
            />
            <div className="flex flex-col gap-6">
                {filters.length > 0 ? (
                    <FilterBar
                        key={JSON.stringify(values)}
                        reportKey={report.key}
                        filters={filters}
                        values={values}
                        selected={selected}
                        errors={filterErrors}
                    />
                ) : null}
                {result === null ? (
                    <PageState
                        kind="empty"
                        illustration="empty-reports"
                        title={t('filters.chooseBranch')}
                        description={report.description}
                    />
                ) : (
                    <Deferred
                        data="result"
                        fallback={<ResultSkeleton label={t('result.loading')} />}
                        rescue={() => <PageState kind="error" description={t('result.error')} />}
                    >
                        {result ? (
                            <Result result={result} title={report.title} />
                        ) : (
                            <ResultSkeleton label={t('result.loading')} />
                        )}
                    </Deferred>
                )}
            </div>
            {saved?.can.share ? (
                <ShareDialog
                    saved={saved.report}
                    roles={roles}
                    open={sharing}
                    onOpenChange={setSharing}
                />
            ) : null}
            {report.canExport && scheduling ? (
                <ScheduleDialog
                    reportKey={report.key}
                    values={values}
                    hasPeriod={filters.some((f) => f.type === 'period')}
                    formats={formats}
                    open={scheduling}
                    onOpenChange={setScheduling}
                />
            ) : null}
            <AlertDialog open={deleting} onOpenChange={setDeleting}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('saved.deleteTitle')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('saved.deleteBody')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="min-h-11">
                            {t('builder.back')}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            className="min-h-11"
                            onClick={() =>
                                savedId && router.delete(reports.saved.destroy.url(savedId))
                            }
                            data-confirm-delete
                        >
                            {t('saved.deleteConfirm')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function Result({
    result,
    title,
}: {
    result: App.Domain.Reports.Data.ReportResultData;
    title: string;
}) {
    const { t } = useTranslation('reports');
    const { locale } = useLocale();

    return (
        <div className="flex flex-col gap-6" aria-live="polite" data-report-result>
            <KpiCards kpis={result.kpis} />
            {result.total === 0 ? (
                <PageState
                    kind="no-results"
                    illustration="empty-reports"
                    title={t('result.empty')}
                    description={t('result.emptyHelp')}
                />
            ) : (
                <>
                    {result.chart && result.chart.data.length > 0 ? (
                        <ReportChart chart={result.chart} title={title} />
                    ) : null}
                    {result.truncated ? (
                        <p
                            role="note"
                            className="rounded-md bg-info-bg p-3 text-small text-info"
                            data-truncated
                        >
                            {t('result.truncated', { count: result.total })}
                        </p>
                    ) : null}
                    <p className="text-small text-text-secondary">
                        {t('result.showing', {
                            shown: result.rows.length,
                            total: result.total,
                            totalFormatted: formatNumber(result.total, locale),
                        })}
                    </p>
                    <ReportTable result={result} title={title} />
                </>
            )}
        </div>
    );
}

function ResultSkeleton({ label }: { label: string }) {
    return (
        <div role="status" aria-label={label} className="flex flex-col gap-6" data-report-loading>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {[0, 1, 2, 3].map((n) => (
                    <Skeleton key={n} className="h-20 rounded-lg" />
                ))}
            </div>
            <Skeleton className="h-72 rounded-lg" />
            <Skeleton className="h-64 rounded-lg" />
        </div>
    );
}

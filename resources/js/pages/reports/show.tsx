import { Deferred, Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Skeleton } from '@/components/ui/skeleton';
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
};

/**
 * The report viewer (handoff 36): filters, headline numbers, a chart and the table. The result
 * loads after the page (it can take a moment), behind a skeleton.
 */
export default function ReportShow(props: Props) {
    const { report, filters, values, selected, formats, filterErrors, result } = props;
    const { t } = useTranslation('reports');

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
                    report.canExport && result !== null ? (
                        <ExportMenu reportKey={report.key} values={values} formats={formats} />
                    ) : null
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

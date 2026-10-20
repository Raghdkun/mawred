import { Head, Link } from '@inertiajs/react';
import { CalendarClock, FileDown, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ModuleIcon } from '@/components/mw/module-icon';
import { type ModuleIconName } from '@/components/mw/module-icons.generated';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import reports from '@/routes/reports';

type Props = {
    categories: App.Domain.Reports.Enums.ReportCategory[];
    reports: App.Domain.Reports.Data.ReportCardData[];
    pendingExports: boolean;
    saved: App.Domain.Reports.Data.SavedReportData[];
    can: { build: boolean; schedule: boolean };
};

const ICONS: Record<App.Domain.Reports.Enums.ReportCategory, ModuleIconName> = {
    people: 'people',
    leave: 'leave',
    time: 'attendance',
    requests: 'requests',
    hiring: 'hiring',
    training: 'training',
    payroll: 'reports',
    admin: 'settings',
};

/** The report library (handoff 36): the reports this person may open, by module. */
export default function ReportsIndex({
    categories,
    reports: list,
    pendingExports,
    saved,
    can,
}: Props) {
    const { t } = useTranslation('reports');
    const groups = categories
        .map((category) => ({ category, items: list.filter((r) => r.category === category) }))
        .filter((group) => group.items.length > 0);

    return (
        <>
            <Head title={t('pageTitle')} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        {can.build ? (
                            <Button asChild className="min-h-11">
                                <Link href={reports.builder.create.url()} data-new-report>
                                    <Plus aria-hidden />
                                    {t('custom.new')}
                                </Link>
                            </Button>
                        ) : null}
                        {can.schedule ? (
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={reports.schedules.index.url()} data-my-schedules>
                                    <CalendarClock aria-hidden />
                                    {t('custom.schedules')}
                                </Link>
                            </Button>
                        ) : null}
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={reports.exports.index.url()} data-my-exports>
                                <FileDown aria-hidden />
                                {t('myExports')}
                                {pendingExports ? (
                                    <span
                                        className="size-2 rounded-full bg-primary"
                                        aria-label={t('exportsPending')}
                                    />
                                ) : null}
                            </Link>
                        </Button>
                    </div>
                }
            />
            {saved.length > 0 || can.build ? (
                <section aria-labelledby="custom-reports" className="mb-8" data-custom-reports>
                    <h2 id="custom-reports" className="mb-1 flex items-center gap-2 text-h4">
                        <ModuleIcon name="reports" className="size-5 text-text-secondary" />
                        {t('custom.title')}
                    </h2>
                    <p className="mb-3 text-small text-text-secondary">{t('custom.help')}</p>
                    {saved.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-border p-4 text-small text-text-secondary">
                            {t('custom.empty')}
                        </p>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {saved.map((report) => (
                                <li key={report.id}>
                                    <Link
                                        href={reports.show.url(report.key)}
                                        className="flex h-full min-h-24 flex-col gap-1 rounded-lg border border-border bg-card p-4 transition-colors hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                                        data-report={report.key}
                                    >
                                        <span className="text-body font-medium text-foreground">
                                            {report.name}
                                        </span>
                                        <span className="text-small text-text-secondary">
                                            {report.description ?? report.datasetTitle}
                                        </span>
                                        <span className="mt-auto pt-1 text-caption text-text-secondary">
                                            {report.mine
                                                ? report.sharedWith &&
                                                  report.sharedWith.people.length +
                                                      report.sharedWith.roles.length >
                                                      0
                                                    ? t('custom.shared', {
                                                          count:
                                                              report.sharedWith.people.length +
                                                              report.sharedWith.roles.length,
                                                      })
                                                    : t('custom.yours')
                                                : t('custom.sharedBy', {
                                                      name: report.owner ?? '',
                                                  })}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ) : null}
            {groups.length === 0 && saved.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-reports"
                    title={t('empty.title')}
                    description={t('empty.description')}
                />
            ) : (
                <div className="flex flex-col gap-8">
                    {groups.map((group) => (
                        <section
                            key={group.category}
                            aria-labelledby={`category-${group.category}`}
                        >
                            <h2
                                id={`category-${group.category}`}
                                className="mb-3 flex items-center gap-2 text-h4"
                            >
                                <ModuleIcon
                                    name={ICONS[group.category]}
                                    className="size-5 text-text-secondary"
                                />
                                {t(`categories.${group.category}`)}
                            </h2>
                            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {group.items.map((report) => (
                                    <li key={report.key}>
                                        <Link
                                            href={reports.show.url(report.key)}
                                            className="flex h-full min-h-24 flex-col gap-1 rounded-lg border border-border bg-card p-4 transition-colors hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                                            data-report={report.key}
                                        >
                                            <span className="text-body font-medium text-foreground">
                                                {report.title}
                                            </span>
                                            <span className="text-small text-text-secondary">
                                                {report.description}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}
        </>
    );
}

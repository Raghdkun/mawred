import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { useTranslation } from 'react-i18next';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { HealthBadge, type IntegrationHealth } from '@/components/mw/status-badge';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import integrationRoutes from '@/routes/integrations';
import { KpiRow } from './kpi-card';

type Hr = App.Domain.Reports.Data.HrDashboardData;

const SERIES = ['present', 'late', 'away', 'absent', 'notIn'] as const;

function health(status: string): IntegrationHealth {
    return status === 'healthy' || status === 'degraded' || status === 'disconnected'
        ? status
        : 'not-configured';
}

/** The HR admin home (handoff 6, D123). */
export function HrDashboard({ hr }: { hr: Hr }) {
    const { t } = useTranslation('home');
    const disconnected = (hr.integrations ?? []).filter((i) => i.status === 'disconnected').length;

    return (
        <div className="flex min-w-0 flex-col gap-6" data-hr-dashboard>
            {disconnected > 0 ? (
                <div
                    role="alert"
                    className="flex flex-wrap items-center gap-3 rounded-lg border border-danger-border bg-danger-bg p-4 text-small text-danger"
                    data-integration-banner
                >
                    <AlertTriangle aria-hidden className="size-5 shrink-0" />
                    <span className="flex-1">{t('hr.banner', { count: disconnected })}</span>
                    <Link
                        href={integrationRoutes.index.url()}
                        className="inline-flex min-h-11 items-center font-medium underline"
                    >
                        {t('hr.bannerLink')}
                    </Link>
                </div>
            ) : null}
            {hr.kpis.length > 0 ? <KpiRow kpis={hr.kpis} /> : null}
            <div className="grid gap-6 lg:grid-cols-12">
                {hr.attendance !== null ? (
                    <div className="lg:col-span-7">
                        <AttendanceToday rows={hr.attendance} />
                    </div>
                ) : null}
                {hr.expiring !== null ? (
                    <div
                        className={cn(hr.attendance !== null ? 'lg:col-span-5' : 'lg:col-span-12')}
                    >
                        <ExpiringDocuments expiring={hr.expiring} />
                    </div>
                ) : null}
            </div>
            {hr.integrations !== null ? (
                <IntegrationHealthWidget integrations={hr.integrations} />
            ) : null}
        </div>
    );
}

function AttendanceToday({ rows }: { rows: NonNullable<Hr['attendance']> }) {
    const { t } = useTranslation('home');
    const { locale, isRtl } = useLocale();
    const reduced = useReducedMotion();

    return (
        <section
            aria-labelledby="attendance-today"
            className="h-full rounded-lg border border-border bg-card p-5 shadow-sm"
            data-attendance-today
        >
            <h2 id="attendance-today" className="text-h4">
                {t('hr.attendance.title')}
            </h2>
            <p className="mb-3 text-small text-text-secondary">{t('hr.attendance.help')}</p>
            {rows.length === 0 ? (
                <p className="rounded-md border border-dashed border-border p-6 text-center text-small text-text-secondary">
                    {t('hr.attendance.empty')}
                </p>
            ) : (
                <>
                    <div dir="ltr" className="h-64 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart
                                data={rows}
                                layout="vertical"
                                margin={{ top: 4, right: 8, left: 0, bottom: 4 }}
                            >
                                <CartesianGrid horizontal={false} stroke="var(--border)" />
                                <XAxis
                                    type="number"
                                    allowDecimals={false}
                                    tickFormatter={(v: number) => formatNumber(v, locale)}
                                    tick={{ fill: 'var(--text-secondary)', fontSize: 12 }}
                                />
                                <YAxis
                                    type="category"
                                    dataKey="branch"
                                    width={120}
                                    tick={{ fill: 'var(--text-secondary)', fontSize: 12 }}
                                />
                                <Tooltip
                                    formatter={(v) =>
                                        typeof v === 'number' ? formatNumber(v, locale) : String(v)
                                    }
                                    contentStyle={{
                                        direction: isRtl ? 'rtl' : 'ltr',
                                        background: 'var(--popover)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 8,
                                        color: 'var(--popover-foreground)',
                                    }}
                                    cursor={{ fill: 'var(--muted)' }}
                                />
                                <Legend
                                    itemSorter={null}
                                    formatter={(value) => (
                                        <span className="text-foreground">{String(value)}</span>
                                    )}
                                    align={isRtl ? 'right' : 'left'}
                                    verticalAlign="top"
                                    wrapperStyle={{
                                        direction: isRtl ? 'rtl' : 'ltr',
                                        paddingBottom: 8,
                                    }}
                                />
                                {SERIES.map((key, i) => (
                                    <Bar
                                        key={key}
                                        dataKey={key}
                                        name={t(`hr.attendance.${key}`)}
                                        stackId="today"
                                        fill={`var(--chart-${i + 1})`}
                                        maxBarSize={28}
                                        isAnimationActive={!reduced}
                                        animationDuration={600}
                                        animationBegin={i * 60}
                                        animationEasing="ease-out"
                                    />
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                    <div className="sr-only">
                        <table>
                            <caption>{t('hr.attendance.title')}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{t('reports:filters.branch')}</th>
                                    {SERIES.map((key) => (
                                        <th key={key} scope="col">
                                            {t(`hr.attendance.${key}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.branch}>
                                        <th scope="row">{row.branch}</th>
                                        {SERIES.map((key) => (
                                            <td key={key}>{formatNumber(row[key], locale)}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </section>
    );
}

function ExpiringDocuments({ expiring }: { expiring: NonNullable<Hr['expiring']> }) {
    const { t } = useTranslation('home');
    const { locale } = useLocale();

    return (
        <section
            aria-labelledby="expiring-docs"
            className="h-full rounded-lg border border-border bg-card p-5 shadow-sm"
            data-expiring-documents
        >
            <div className="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h2 id="expiring-docs" className="text-h4">
                        {t('hr.expiring.title')}
                    </h2>
                    <p className="text-small text-text-secondary">{t('hr.expiring.help')}</p>
                </div>
                {expiring.href && expiring.total > 0 ? (
                    <Link
                        href={expiring.href}
                        className="inline-flex min-h-11 shrink-0 items-center text-small text-link hover:underline"
                    >
                        {t('hr.expiring.all', { count: expiring.total })}
                    </Link>
                ) : null}
            </div>
            {expiring.rows.length === 0 ? (
                <p className="rounded-md border border-dashed border-border p-6 text-center text-small text-text-secondary">
                    {t('hr.expiring.empty')}
                </p>
            ) : (
                <ul className="flex flex-col divide-y divide-border">
                    {expiring.rows.map((row) => (
                        <li
                            key={`${row.number}-${row.document}-${row.expiresOn}`}
                            className="flex items-center gap-3 py-2"
                        >
                            <div className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate text-small font-medium">
                                    <bdi>{row.name}</bdi>
                                </span>
                                <span className="truncate text-caption text-text-secondary">
                                    {row.document} · <bdi>{row.branch}</bdi>
                                </span>
                            </div>
                            <span
                                className={cn(
                                    'shrink-0 rounded-full px-2.5 py-0.5 text-caption font-medium',
                                    row.daysLeft < 0
                                        ? 'border border-transparent bg-danger-bg text-danger'
                                        : 'border border-warning-border bg-warning-bg text-warning',
                                )}
                                title={formatDate(`${row.expiresOn}T00:00:00Z`, locale, {
                                    dateStyle: 'medium',
                                    timeZone: 'UTC',
                                })}
                            >
                                {row.daysLeft < 0
                                    ? t('hr.expiring.expired', { days: Math.abs(row.daysLeft) })
                                    : row.daysLeft === 0
                                      ? t('hr.expiring.today')
                                      : t('hr.expiring.daysLeft', { days: row.daysLeft })}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function IntegrationHealthWidget({
    integrations,
}: {
    integrations: NonNullable<Hr['integrations']>;
}) {
    const { t } = useTranslation('home');
    const { locale } = useLocale();

    return (
        <section
            aria-labelledby="integration-health"
            className="rounded-lg border border-border bg-card p-5 shadow-sm"
            data-integration-health
        >
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 id="integration-health" className="text-h4">
                    {t('hr.integrations.title')}
                </h2>
                <Link
                    href={integrationRoutes.index.url()}
                    className="inline-flex min-h-11 items-center text-small text-link hover:underline"
                >
                    {t('hr.integrations.manage')}
                </Link>
            </div>
            {integrations.length === 0 ? (
                <p className="text-small text-text-secondary">{t('hr.integrations.empty')}</p>
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {integrations.map((i) => (
                        <li
                            key={`${i.type}-${i.name}`}
                            className="flex flex-col gap-1 rounded-md border border-border p-3"
                        >
                            <span className="text-small font-medium">
                                <bdi>{i.name}</bdi>
                            </span>
                            <HealthBadge health={health(i.status)} className="self-start" />
                            <span className="text-caption text-text-secondary">
                                {i.checkedAt
                                    ? t('hr.integrations.checked', {
                                          date: formatDate(i.checkedAt, locale, {
                                              dateStyle: 'medium',
                                              timeStyle: 'short',
                                          }),
                                      })
                                    : t('hr.integrations.notChecked')}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

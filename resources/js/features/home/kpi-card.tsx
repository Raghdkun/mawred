import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Sparkline } from './sparkline';
import { useCountUp } from './use-count-up';

type Kpi = App.Domain.Reports.Data.DashboardKpiData;

/**
 * A KPI card (handoff 5, 6, DSData "KPI card with sparkline"): the number counts up, the
 * sparkline draws in, and the card links to the report behind it when there is one.
 */
export function KpiCard({ kpi }: { kpi: Kpi }) {
    const { t } = useTranslation('home');
    const { locale } = useLocale();
    const percent = kpi.format === 'percent';
    const shown = useCountUp(Number(kpi.value), percent ? 1 : 0);
    const label = t(`kpi.${kpi.key}`);
    const value = percent
        ? formatNumber(shown / 100, locale, { style: 'percent', maximumFractionDigits: 1 })
        : formatNumber(shown, locale);
    const data = kpi.trend;
    const delta = typeof kpi.delta === 'number' ? kpi.delta : null;

    const body = (
        <>
            <span className="text-small text-text-secondary">{label}</span>
            <span className="text-h2 text-foreground tabular-nums" aria-live="off">
                {value}
            </span>
            {delta !== null ? (
                <span
                    className={cn(
                        'text-caption',
                        delta === 0 ? 'text-text-secondary' : 'text-foreground',
                    )}
                >
                    {delta < 0
                        ? t('kpi.deltaDown', { delta: Math.abs(delta) })
                        : t('kpi.delta', { delta })}
                </span>
            ) : null}
            {data.length > 1 ? (
                <div dir="ltr" className="mt-auto w-full">
                    <Sparkline values={kpi.trend.map(Number)} />
                </div>
            ) : null}
            {data.length > 1 ? (
                <span className="sr-only">{t('kpi.trend', { count: data.length })}</span>
            ) : null}
        </>
    );

    const className =
        'relative flex h-full min-h-32 min-w-44 flex-col gap-1 rounded-lg border border-border bg-card p-4 shadow-sm';

    return kpi.href ? (
        <Link
            href={kpi.href}
            className={cn(
                className,
                'transition-colors hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none',
            )}
            aria-label={`${label}: ${value}. ${t('kpi.open', { label })}`}
            data-kpi={kpi.key}
        >
            {body}
        </Link>
    ) : (
        <div className={className} data-kpi={kpi.key}>
            {body}
        </div>
    );
}

/**
 * KPI cards: four in a row on desktop, 2×2 on tablets, a horizontal scroller on phones
 * (starting at the reading start).
 */
export function KpiRow({ kpis }: { kpis: Kpi[] }) {
    return (
        <ul
            className="-mx-4 flex w-[calc(100%+2rem)] min-w-0 snap-x gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:w-auto sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-4"
            data-kpi-row
        >
            {kpis.map((kpi) => (
                <li key={kpi.key} className="shrink-0 snap-start sm:shrink">
                    <KpiCard kpi={kpi} />
                </li>
            ))}
        </ul>
    );
}

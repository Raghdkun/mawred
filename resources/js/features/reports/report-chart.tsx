import { useTranslation } from 'react-i18next';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';

type Chart = NonNullable<App.Domain.Reports.Data.ReportResultData['chart']>;

const COLORS = [1, 2, 3, 4, 5, 6, 7, 8].map((n) => `var(--chart-${n})`);

/**
 * One report chart (handoff 36): the colourblind-safe palette in order, a legend, and figures in
 * the viewer's digits. Charts never mirror, so the plot stays left-to-right in Arabic, while the
 * legend follows the reading direction. The same numbers are in the table for screen readers.
 */
export function ReportChart({ chart, title }: { chart: Chart; title: string }) {
    const { t } = useTranslation('reports');
    const { locale, isRtl } = useLocale();
    const tick = (value: unknown) =>
        typeof value === 'number'
            ? formatNumber(value, locale, { notation: 'compact' })
            : String(value);
    const shortLabel = (value: unknown) => {
        const text = String(value);

        return text.length > 18 ? `${text.slice(0, 17)}…` : text;
    };
    const legend = (
        <Legend
            align={isRtl ? 'right' : 'left'}
            verticalAlign="top"
            wrapperStyle={{ direction: isRtl ? 'rtl' : 'ltr', paddingBottom: 8 }}
        />
    );
    const tooltip = (
        <Tooltip
            formatter={(value) =>
                typeof value === 'number' ? formatNumber(value, locale) : String(value)
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
    );
    const axes = (
        <>
            <CartesianGrid vertical={false} stroke="var(--border)" />
            <XAxis
                dataKey={chart.x}
                tickFormatter={shortLabel}
                tick={{ fill: 'var(--text-secondary)', fontSize: 12 }}
                interval={0}
                height={48}
                angle={chart.data.length > 6 ? -30 : 0}
                textAnchor={chart.data.length > 6 ? 'end' : 'middle'}
            />
            <YAxis
                tickFormatter={tick}
                tick={{ fill: 'var(--text-secondary)', fontSize: 12 }}
                width={48}
                allowDecimals={false}
            />
        </>
    );

    return (
        <figure className="rounded-lg border border-border bg-card p-4" data-report-chart>
            <figcaption className="sr-only">{t('result.chart', { title })}</figcaption>
            <div dir="ltr" className="h-72 w-full">
                <ResponsiveContainer width="100%" height="100%">
                    {chart.type === 'line' ? (
                        <LineChart
                            data={chart.data}
                            margin={{ top: 4, right: 8, left: 0, bottom: 4 }}
                        >
                            {axes}
                            {tooltip}
                            {legend}
                            {chart.series.map((s, i) => (
                                <Line
                                    key={s.key}
                                    dataKey={s.key}
                                    name={s.label}
                                    stroke={COLORS[i % COLORS.length]}
                                    strokeWidth={2}
                                    dot={false}
                                    isAnimationActive={false}
                                />
                            ))}
                        </LineChart>
                    ) : (
                        <BarChart
                            data={chart.data}
                            margin={{ top: 4, right: 8, left: 0, bottom: 4 }}
                        >
                            {axes}
                            {tooltip}
                            {legend}
                            {chart.series.map((s, i) => (
                                <Bar
                                    key={s.key}
                                    dataKey={s.key}
                                    name={s.label}
                                    fill={COLORS[i % COLORS.length]}
                                    stackId={chart.type === 'stacked' ? 'stack' : undefined}
                                    radius={chart.type === 'stacked' ? 0 : [4, 4, 0, 0]}
                                    maxBarSize={48}
                                    isAnimationActive={false}
                                />
                            ))}
                        </BarChart>
                    )}
                </ResponsiveContainer>
            </div>
        </figure>
    );
}

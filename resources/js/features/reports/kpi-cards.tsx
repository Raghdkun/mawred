import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { formatValue } from './format';

type Kpi = App.Domain.Reports.Data.ReportResultData['kpis'][number];

/** The report's headline numbers (handoff 36), two-up on phones and four-up on desktop. */
export function KpiCards({ kpis }: { kpis: Kpi[] }) {
    const { t } = useTranslation('reports');
    const { locale } = useLocale();

    if (kpis.length === 0) {
        return null;
    }

    return (
        <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4" data-kpis>
            {kpis.map((kpi) => (
                <div
                    key={kpi.label}
                    className="flex flex-col gap-1 rounded-lg border border-border bg-card p-4"
                >
                    <dt className="text-small text-text-secondary">{kpi.label}</dt>
                    <dd className="text-h3 text-foreground tabular-nums">
                        {formatValue(kpi.value, kpi.format, locale, t)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

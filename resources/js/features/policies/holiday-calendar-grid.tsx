import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { type Locale } from '@/i18n';
import { formatDate, formatNumber, intlLocale } from '@/lib/format';
import { localized } from '@/lib/localized';
import { weekdayName } from '@/lib/regions';
import { cn } from '@/lib/utils';
import { type HolidayDay } from './types';
import { weekFrom } from './values';

/** ISO date for a UTC calendar day. */
function iso(year: number, month: number, day: number): string {
    return new Date(Date.UTC(year, month, day)).toISOString().slice(0, 10);
}

/**
 * Twelve month grids for a branch year (handoff 21): holiday cells use the selection tint, the
 * branch's weekend is shaded. Weeks start the day after the weekend, in the reading direction.
 */
export function HolidayCalendarGrid({
    year,
    days,
    weekend,
}: {
    year: number;
    days: HolidayDay[];
    weekend: number[];
}) {
    const { locale } = useLocale();
    const byDate = new Map<string, HolidayDay[]>();

    for (const day of days) {
        byDate.set(day.observedOn, [...(byDate.get(day.observedOn) ?? []), day]);
    }

    const start = weekend.length > 0 ? (Math.max(...weekend) % 7) + 1 : 1;
    const week = weekFrom(start);

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {Array.from({ length: 12 }, (_, month) => {
                const first = new Date(Date.UTC(year, month, 1));
                const length = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
                const firstIso = ((first.getUTCDay() + 6) % 7) + 1;
                const offset = week.indexOf(firstIso);
                const cells: Array<number | null> = [
                    ...Array.from({ length: offset }, () => null),
                    ...Array.from({ length }, (_, i) => i + 1),
                ];
                const rows = Array.from({ length: Math.ceil(cells.length / 7) }, (_, r) =>
                    cells.slice(r * 7, r * 7 + 7),
                );

                return (
                    <table
                        key={month}
                        className="w-full table-fixed rounded-lg bg-card text-center text-caption shadow-sm"
                    >
                        <caption className="px-3 pt-3 pb-2 text-start text-sm font-semibold">
                            {formatDate(first, locale, { month: 'long', timeZone: 'UTC' })}
                        </caption>
                        <thead>
                            <tr>
                                {week.map((day) => (
                                    <th
                                        key={day}
                                        scope="col"
                                        abbr={weekdayName(day, locale, 'long')}
                                        className="pb-1 font-medium text-muted-foreground"
                                    >
                                        {weekdayName(day, locale, 'short').slice(
                                            0,
                                            locale === 'ar' ? 3 : 2,
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, r) => (
                                // Week rows have no identity beyond their position in the month.
                                <tr key={r}>
                                    {row.map((day, c) => {
                                        if (day === null) {
                                            return <td key={`empty-${c}`} />;
                                        }

                                        const date = iso(year, month, day);
                                        const holidays = byDate.get(date) ?? [];
                                        const isWeekend = weekend.includes(week[c] ?? 0);
                                        const label = holidays
                                            .map((h) => localized(h.name, locale))
                                            .join(', ');

                                        return (
                                            <td key={date} className="p-0.5">
                                                <span
                                                    title={label || undefined}
                                                    className={cn(
                                                        'mx-auto flex size-8 items-center justify-center rounded-md tabular-nums',
                                                        isWeekend &&
                                                            'bg-muted text-muted-foreground',
                                                        holidays.length > 0 &&
                                                            'bg-primary font-semibold text-primary-foreground',
                                                    )}
                                                >
                                                    {formatNumber(day, locale)}
                                                    {label ? (
                                                        <span className="sr-only">: {label}</span>
                                                    ) : null}
                                                </span>
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                );
            })}
        </div>
    );
}

/** The year's holidays as a list (all screen sizes; the only view on phones). */
export function HolidayList({ days }: { days: HolidayDay[] }) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();

    return (
        <ul className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
            {days.map((day) => (
                <li
                    key={`${day.ruleId}-${day.day}-${day.date}`}
                    className="flex flex-wrap items-baseline gap-x-4 gap-y-1 border-b border-border px-4 py-3 last:border-b-0"
                >
                    <span className="w-40 shrink-0 text-sm font-medium tabular-nums">
                        {formatDate(day.observedOn, locale, {
                            weekday: 'short',
                            day: 'numeric',
                            month: 'short',
                            timeZone: 'UTC',
                        })}
                    </span>
                    <span className="flex min-w-0 flex-1 flex-col">
                        <span className="text-sm">{localized(day.name, locale)}</span>
                        <span className="text-caption text-muted-foreground">
                            {[
                                day.days > 1
                                    ? t('holidays.dayOf', {
                                          day: formatNumber(day.day, locale),
                                          days: formatNumber(day.days, locale),
                                      })
                                    : null,
                                day.date !== day.observedOn
                                    ? t('holidays.movedFrom', {
                                          date: formatDate(day.date, locale, {
                                              day: 'numeric',
                                              month: 'short',
                                              timeZone: 'UTC',
                                          }),
                                      })
                                    : null,
                                day.hijriBased ? hijri(day.date, locale) : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    </span>
                </li>
            ))}
        </ul>
    );
}

function hijri(date: string, locale: Locale): string {
    return new Intl.DateTimeFormat(intlLocale(locale, { calendar: 'islamic-umalqura' }), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

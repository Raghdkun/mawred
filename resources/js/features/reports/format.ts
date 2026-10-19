import { type TFunction } from 'i18next';
import { type Locale } from '@/i18n';
import { formatDate, formatNumber } from '@/lib/format';

type ColumnType = App.Domain.Reports.Enums.ColumnType;

/**
 * A report value for the screen: counts and decimals in the viewer's digits, percentages with a
 * sign, minutes as hours and minutes, dates as calendar dates (never shifted by time zone).
 */
export function formatValue(
    value: string | number | null | undefined,
    type: ColumnType | string,
    locale: Locale,
    t: TFunction,
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'string' && type !== 'date') {
        return value;
    }

    switch (type) {
        case 'number':
            return formatNumber(Number(value), locale);
        case 'decimal':
            return formatNumber(Number(value), locale, { maximumFractionDigits: 2 });
        case 'percent':
            return formatNumber(Number(value) / 100, locale, {
                style: 'percent',
                maximumFractionDigits: 1,
            });
        case 'minutes': {
            const total = Math.round(Number(value));
            const sign = total < 0 ? '-' : '';
            const hours = Math.floor(Math.abs(total) / 60);
            const minutes = Math.abs(total) % 60;

            if (hours === 0) {
                return sign + t('minutesOnly', { minutes: formatNumber(minutes, locale) });
            }

            return (
                sign +
                t(minutes === 0 ? 'hoursOnly' : 'minutes', {
                    hours: formatNumber(hours, locale),
                    minutes: formatNumber(minutes, locale),
                })
            );
        }
        case 'date':
            return /^\d{4}-\d{2}-\d{2}$/.test(String(value))
                ? formatDate(`${value}T00:00:00Z`, locale, {
                      dateStyle: 'medium',
                      timeZone: 'UTC',
                  })
                : String(value);
        default:
            return String(value);
    }
}

/** True for columns that line up at the end of a cell (numbers). */
export function isNumeric(type: ColumnType | string): boolean {
    return type !== 'text' && type !== 'date';
}

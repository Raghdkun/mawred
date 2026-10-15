import { type TFunction } from 'i18next';
import { type Locale } from '@/i18n';
import { formatDate, formatNumber } from '@/lib/format';

export type LeaveUnit = 'days' | 'hours' | 'minutes';
export type LeaveBalance = App.Domain.Leave.Data.LeaveBalanceData;
export type LeaveEvent = App.Domain.Leave.Data.LeaveEventData;
export type LedgerEntry = App.Domain.Leave.Data.LedgerEntryData;
export type LeaveQuote = App.Domain.Leave.Data.LeaveQuoteData;

export function asUnit(unit: string): LeaveUnit {
    return unit === 'hours' || unit === 'minutes' ? unit : 'days';
}

/** "2.5 days", "١ يوم" — ICU plural with the user's digits. */
export function amountLabel(t: TFunction, amount: number, unit: string, locale: Locale): string {
    return t(`leave:units.${asUnit(unit)}`, {
        count: amount,
        formatted: formatNumber(amount, locale, { maximumFractionDigits: 2 }),
    });
}

/** A signed change with a real minus sign (never colour alone, handoff 20). */
export function signed(amount: number, locale: Locale): string {
    const value = formatNumber(Math.abs(amount), locale, { maximumFractionDigits: 2 });

    return amount < 0 ? `−${value}` : amount > 0 ? `+${value}` : value;
}

/** A calendar date (Y-m-d) without timezone drift. */
export function dayLabel(
    date: string,
    locale: Locale,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
    return formatDate(`${date}T12:00:00`, locale, options);
}

export function rangeLabel(from: string, to: string, locale: Locale): string {
    if (from === to) {
        return dayLabel(from, locale);
    }

    const sameYear = from.slice(0, 4) === to.slice(0, 4);
    const end = { day: 'numeric', month: 'short', year: 'numeric' } as const;

    return `${dayLabel(from, locale, sameYear ? { day: 'numeric', month: 'short' } : end)} – ${dayLabel(to, locale, end)}`;
}

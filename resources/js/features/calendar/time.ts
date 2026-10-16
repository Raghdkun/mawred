import { TZDate } from '@date-fns/tz';
import { type Locale } from '@/i18n';
import { formatDate } from '@/lib/format';

export type CalendarEvent = App.Domain.Calendar.Data.EventData;

/** The instant for a local date and time in a timezone, as ISO 8601 (UTC). */
export function toInstant(date: string, time: string, timezone: string): string {
    const [y, m, d] = date.split('-').map(Number);
    const [h, mi] = time.split(':').map(Number);

    return new TZDate(y ?? 1970, (m ?? 1) - 1, d ?? 1, h ?? 0, mi ?? 0, 0, timezone).toISOString();
}

/** Local date (Y-m-d) and minutes from midnight of an instant in a timezone. */
export function localParts(
    iso: string,
    timezone: string,
): { date: string; minutes: number; time: string } {
    const t = new TZDate(iso, timezone);
    const date = `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
    const time = `${String(t.getHours()).padStart(2, '0')}:${String(t.getMinutes()).padStart(2, '0')}`;

    return { date, minutes: t.getHours() * 60 + t.getMinutes(), time };
}

export function timeLabel(iso: string, timezone: string, locale: Locale): string {
    return formatDate(iso, locale, { hour: '2-digit', minute: '2-digit', timeZone: timezone });
}

export function rangeTimeLabel(
    start: string,
    end: string,
    timezone: string,
    locale: Locale,
): string {
    return `${timeLabel(start, timezone, locale)}–${timeLabel(end, timezone, locale)}`;
}
